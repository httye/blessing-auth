<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Plugins\Hook;
use App\Rules\AllowedEmailDomain;
use App\Services\EmailVerification;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        abort_unless(option_bool('allow_register'), 403, '本站已关闭注册。');

        return view('auth.register');
    }

    /** 发送注册验证码（AJAX） */
    public function sendCode(Request $request, EmailVerification $verification): JsonResponse
    {
        abort_unless(option_bool('allow_register'), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email', new AllowedEmailDomain],
        ], [
            'email.unique' => '该邮箱已被注册。',
        ]);

        if (! option_bool('email_verification')) {
            return response()->json(['message' => '本站未开启邮箱验证。'], 400);
        }

        try {
            $wait = $verification->send($data['email']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('验证码发送失败：'.$e->getMessage());

            return response()->json(['message' => '验证码发送失败，请稍后再试或联系管理员。'], 500);
        }

        if ($wait > 0) {
            return response()->json(['message' => "发送过于频繁，请 {$wait} 秒后再试。"], 429);
        }

        return response()->json(['message' => '验证码已发送，请查收邮件（含垃圾箱）。']);
    }

    public function register(Request $request, ScoreService $score, EmailVerification $verification): RedirectResponse
    {
        abort_unless(option_bool('allow_register'), 403, '本站已关闭注册。');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email', new AllowedEmailDomain],
            'nickname' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'captcha' => [option_bool('email_verification') ? 'required' : 'nullable', 'string'],
        ], [
            'email.unique' => '该邮箱已被注册。',
            'password.confirmed' => '两次输入的密码不一致。',
            'captcha.required' => '请填写邮箱验证码。',
        ]);

        if (option_bool('email_verification')
            && ! $verification->verify($data['email'], $data['captcha'] ?? '')) {
            throw ValidationException::withMessages([
                'captcha' => '验证码错误或已过期。',
            ]);
        }

        $user = User::create([
            'email' => $data['email'],
            'nickname' => $data['nickname'],
            'password' => $data['password'],
            'score' => 0,
            'permission' => User::query()->count() === 0
                ? User::PERMISSION_SUPER   // 第一个注册的用户自动成为超级管理员
                : User::PERMISSION_NORMAL,
        ]);

        if (($initial = option_int('initial_score')) > 0) {
            $score->grant($user, $initial, '注册奖励');
        }

        Hook::fire('auth.registered', $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('user.home');
    }
}
