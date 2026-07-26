<?php

namespace App\Services\OAuth;

/**
 * OAuth 提供商注册表。内置微软 / GitHub / Gitee；
 * 插件可在 bootstrap 中调用 register() 增加提供商。
 */
class ProviderRegistry
{
    /** @var array<string, Provider>|null */
    protected static ?array $providers = null;

    /** @return array<string, Provider> 全部已注册提供商 */
    public static function all(): array
    {
        if (static::$providers === null) {
            static::$providers = [];
            foreach (static::builtin() as $p) {
                static::$providers[$p->name] = $p;
            }
        }

        return static::$providers;
    }

    /** @return array<string, Provider> 管理员已配置启用的提供商 */
    public static function enabled(): array
    {
        return array_filter(static::all(), fn (Provider $p) => $p->configured());
    }

    public static function get(string $name): ?Provider
    {
        return static::all()[$name] ?? null;
    }

    /** 插件扩展点 */
    public static function register(Provider $provider): void
    {
        static::all();
        static::$providers[$provider->name] = $provider;
    }

    /** @return Provider[] */
    protected static function builtin(): array
    {
        return [
            new Provider(
                name: 'microsoft',
                title: '微软账号',
                authUrl: 'https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize',
                tokenUrl: 'https://login.microsoftonline.com/consumers/oauth2/v2.0/token',
                userUrl: 'https://graph.microsoft.com/v1.0/me',
                scope: 'User.Read',
                mapUser: fn (array $u) => [
                    'id' => $u['id'],
                    'email' => $u['mail'] ?? $u['userPrincipalName'] ?? null,
                    'nickname' => $u['displayName'] ?? null,
                ],
            ),
            new Provider(
                name: 'github',
                title: 'GitHub',
                authUrl: 'https://github.com/login/oauth/authorize',
                tokenUrl: 'https://github.com/login/oauth/access_token',
                userUrl: 'https://api.github.com/user',
                scope: 'read:user user:email',
                mapUser: fn (array $u) => [
                    'id' => (string) $u['id'],
                    'email' => $u['email'] ?? null,
                    'nickname' => $u['name'] ?? $u['login'] ?? null,
                ],
            ),
            new Provider(
                name: 'gitee',
                title: 'Gitee',
                authUrl: 'https://gitee.com/oauth/authorize',
                tokenUrl: 'https://gitee.com/oauth/token',
                userUrl: 'https://gitee.com/api/v5/user',
                scope: 'user_info',
                mapUser: fn (array $u) => [
                    'id' => (string) $u['id'],
                    'email' => $u['email'] ?? null,
                    'nickname' => $u['name'] ?? $u['login'] ?? null,
                ],
                extraTokenParams: ['grant_type' => 'authorization_code'],
            ),
        ];
    }
}
