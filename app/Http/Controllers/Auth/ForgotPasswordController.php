<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerification;
use App\Plugins\Hook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot');
    }

    /** 发送重置验证码（AJAX） */
    public function sendCode(Request $request, EmailVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'exists:users,email'],
        ], [
            'email.exists' => '该邮箱未注册。',
        ]);

        try {
            $wait = $verification->send($data['email'], 'password_reset');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('重置验证码发送失败：'.$e->getMessage());

            return response()->json(['message' => '验证码发送失败，请稍后再试。'], 500);
        }

        if ($wait > 0) {
            return response()->json(['message' => "发送过于频繁，请 {$wait} 秒后再试。"], 429);
        }

        return response()->json(['message' => '验证码已发送，请查收邮件。']);
    }

    public function reset(Request $request, EmailVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'exists:users,email'],
            'captcha' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'email.exists' => '该邮箱未注册。',
            'password.confirmed' => '两次输入的密码不一致。',
        ]);

        if (! $verification->verify($data['email'], $data['captcha'], 'password_reset')) {
            throw ValidationException::withMessages([
                'captcha' => '验证码错误或已过期。',
            ]);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        $user->update(['password' => $data['password']]);

        // 管理令牌防止原有客户端持续登录
        Hook::fire('user.password.changed', $user);

        // 直接登录
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('user.home')->with('success', '密码已重置并已自动登录。');
    }
}
