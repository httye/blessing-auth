<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 注册邮箱后缀限制。
 * 后台设置 email_domain_mode：
 *   off       不限制
 *   whitelist 仅允许 email_domains 列表内的后缀
 *   blacklist 拒绝 email_domains 列表内的后缀
 * email_domains：逗号分隔，如 "qq.com, 163.com, gmail.com"
 */
class AllowedEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $mode = (string) option('email_domain_mode', 'off');

        if ($mode === 'off' || ! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        $domains = array_values(array_filter(array_map(
            fn ($d) => mb_strtolower(trim($d)),
            explode(',', (string) option('email_domains', ''))
        )));

        if ($domains === []) {
            return;
        }

        $domain = mb_strtolower(substr(strrchr($value, '@'), 1));

        $matched = in_array($domain, $domains, true);

        if ($mode === 'whitelist' && ! $matched) {
            $fail('该邮箱后缀不允许注册，允许的后缀：'.implode('、', $domains).'。');
        }

        if ($mode === 'blacklist' && $matched) {
            $fail('该邮箱后缀不允许注册。');
        }
    }
}
