<?php

namespace App\Services\OAuth;

/**
 * OAuth 提供商定义。内置提供商见 ProviderRegistry；
 * 插件可通过 ProviderRegistry::register() 添加新提供商。
 */
class Provider
{
    /**
     * @param string   $name         标识（microsoft/github/gitee）
     * @param string   $title        显示名
     * @param string   $authUrl      授权端点
     * @param string   $tokenUrl     换取 access_token 端点
     * @param string   $userUrl      用户信息端点
     * @param string   $scope        请求的 scope
     * @param callable $mapUser      fn(array $raw): array{id, email?, nickname?}
     * @param array    $extraTokenParams 换 token 时的额外参数
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $authUrl,
        public readonly string $tokenUrl,
        public readonly string $userUrl,
        public readonly string $scope,
        public readonly mixed $mapUser,
        public readonly array $extraTokenParams = [],
    ) {
    }

    /** 该提供商是否已由管理员配置并启用 */
    public function configured(): bool
    {
        return option_bool("oauth.{$this->name}.enabled")
            && option("oauth.{$this->name}.client_id")
            && option("oauth.{$this->name}.client_secret");
    }

    public function clientId(): string
    {
        return (string) option("oauth.{$this->name}.client_id");
    }

    public function clientSecret(): string
    {
        return (string) option("oauth.{$this->name}.client_secret");
    }
}
