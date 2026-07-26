<?php

namespace App\Services;

use App\Models\Option;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * 邮箱验证码：生成、发送、校验。
 * 验证码存 Cache（数据库驱动），10 分钟有效，校验成功即销毁。
 */
class EmailVerification
{
    public const TTL_MINUTES = 10;
    public const CODE_LENGTH = 6;
    public const MAX_ATTEMPTS = 5;

    /**
     * 发送验证码。返回冷却剩余秒数（0 表示已发送）。
     *
     * @throws RuntimeException 发送失败
     */
    public function send(string $email, string $scene = 'register'): int
    {
        // 同邮箱 60 秒冷却
        $coolKey = "vcode:cool:{$scene}:{$email}";
        if (Cache::has($coolKey)) {
            return (int) max(1, Cache::get($coolKey) - time());
        }

        // 同 IP 每小时最多 10 封（防轰炸他人邮箱）
        $ipKey = 'vcode:ip:'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            throw new RuntimeException('发送过于频繁，请稍后再试。');
        }
        RateLimiter::hit($ipKey, 3600);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        Cache::put("vcode:{$scene}:{$email}", [
            'code' => $code,
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        $this->applySmtpOptions();

        $siteName = option('site_name', config('app.name'));

        Mail::raw(
            "您的验证码是：{$code}\n\n"
            .self::TTL_MINUTES." 分钟内有效。如非本人操作请忽略本邮件。\n\n"
            ."—— {$siteName}",
            function ($message) use ($email, $siteName) {
                $message->to($email)->subject("[{$siteName}] 邮箱验证码");
            }
        );

        Cache::put($coolKey, time() + 60, now()->addSeconds(60));

        return 0;
    }

    /** 校验并销毁验证码 */
    public function verify(string $email, string $code, string $scene = 'register'): bool
    {
        $key = "vcode:{$scene}:{$email}";
        $record = Cache::get($key);

        if ($record === null) {
            return false;
        }

        // 防爆破：错 5 次作废
        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! hash_equals($record['code'], trim($code))) {
            $record['attempts']++;
            Cache::put($key, $record, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        Cache::forget($key);

        return true;
    }

    /** 后台 SMTP 设置（options 表）覆盖 .env */
    protected function applySmtpOptions(): void
    {
        if (! Option::get('mail.host')) {
            return; // 后台未配置，使用 .env
        }

        Config::set('mail.mailers.smtp.host', Option::get('mail.host'));
        Config::set('mail.mailers.smtp.port', (int) Option::get('mail.port', 465));
        Config::set('mail.mailers.smtp.encryption', Option::get('mail.encryption', 'ssl'));
        Config::set('mail.mailers.smtp.username', Option::get('mail.username'));

        if ($password = Option::get('mail.password')) {
            Config::set('mail.mailers.smtp.password', $password);
        }

        Config::set('mail.from.address', Option::get('mail.from', Option::get('mail.username')));
        Config::set('mail.from.name', option('site_name', config('app.name')));

        // 清除已实例化的 mailer 使新配置生效
        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstances();
    }
}
