<?php

namespace App\Services;

use App\Models\Player;
use App\Plugins\Hook;
use RuntimeException;

class ProfileSerializer
{
    /**
     * 序列化角色为 Yggdrasil profile JSON。
     *
     * @return array{id: string, name: string, properties?: array}
     */
    public function serialize(Player $player, bool $signed = false): array
    {
        $profile = [
            'id' => $player->undashedUuid(),
            'name' => $player->name,
        ];

        $textures = [];

        if ($player->skin) {
            $textures['SKIN'] = ['url' => $player->skin->url()];
        }

        if ($player->cape) {
            $textures['CAPE'] = ['url' => $player->cape->url()];
        }

        // 插件可修改材质集（如注入 slim 模型 metadata）
        $textures = Hook::apply('ygg.profile.textures', $textures, $player);

        $texturesValue = base64_encode(json_encode([
            'timestamp' => now()->getTimestampMs(),
            'profileId' => $player->undashedUuid(),
            'profileName' => $player->name,
            'textures' => $textures,
        ], JSON_UNESCAPED_SLASHES));

        $property = [
            'name' => 'textures',
            'value' => $texturesValue,
        ];

        if ($signed) {
            $property['signature'] = $this->sign($texturesValue);
        }

        $profile['properties'] = [$property];

        return $profile;
    }

    /** 使用 RSA-SHA1 私钥签名（Minecraft 客户端要求 SHA1withRSA） */
    public function sign(string $data): string
    {
        $privateKey = $this->privateKey();

        if (! openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
            throw new RuntimeException('材质签名失败：'.openssl_error_string());
        }

        return base64_encode($signature);
    }

    public function publicKeyPem(): string
    {
        $path = config('ygg.public_key_path');

        if (! is_file($path)) {
            throw new RuntimeException('缺少签名公钥，请先运行 php artisan ygg:generate-keys');
        }

        return trim(file_get_contents($path));
    }

    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $path = config('ygg.private_key_path');

        if (! is_file($path)) {
            throw new RuntimeException('缺少签名私钥，请先运行 php artisan ygg:generate-keys');
        }

        $key = openssl_pkey_get_private(file_get_contents($path));

        if ($key === false) {
            throw new RuntimeException('私钥加载失败：'.openssl_error_string());
        }

        return $key;
    }
}
