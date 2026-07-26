<?php

namespace App\Http\Controllers;

use App\Plugins\Hook;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function home(Request $request)
    {
        return view('user.home', [
            'user' => $request->user(),
            'players' => $request->user()->players()->with(['skin', 'cape'])->get(),
        ]);
    }

    public function profile(Request $request)
    {
        return view('user.profile', ['user' => $request->user()]);
    }

    public function sign(Request $request, ScoreService $score): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canSignToday()) {
            return back()->with('error', '今天已经签到过了。');
        }

        $user->last_sign_at = now();
        $user->save();

        $amount = option_int('sign_score');
        $score->grant($user, $amount, '每日签到');

        return back()->with('success', "签到成功，获得 {$amount} 积分！");
    }

    /** 积分明细 */
    public function scoreLogs(Request $request)
    {
        return view('user.score', [
            'user' => $request->user(),
            'logs' => $request->user()->scoreLogs()->latest('id')->paginate(20),
        ]);
    }

    public function updateNickname(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => ['required', 'string', 'max:50'],
        ]);

        $request->user()->update($data);

        return back()->with('success', '昵称已更新。');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => '当前密码错误。',
            ]);
        }

        $request->user()->update(['password' => $data['password']]);

        // 修改密码后吊销所有 Yggdrasil 令牌
        $request->user()->yggTokens()->delete();

        Hook::fire('user.password.changed', $request->user());

        return back()->with('success', '密码已更新，所有游戏登录令牌已失效。');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => '当前密码错误。',
            ]);
        }

        $request->user()->update(['email' => $data['email']]);

        return back()->with('success', '邮箱已更新。');
    }
}
