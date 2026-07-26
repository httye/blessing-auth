<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateKeyPair extends Command
{
    protected $signature = 'ygg:generate-keys {--force : 覆盖已存在的密钥}';

    protected $description = '生成 Yggdrasil 材质签名用的 RSA-4096 密钥对';

    public function handle(): int
    {
        $privatePath = config('ygg.private_key_path');
        $publicPath = config('ygg.public_key_path');

        if (is_file($privatePath) && ! $this->option('force')) {
            $this->error('密钥已存在，如需覆盖请使用 --force（会使已有签名失效）。');

            return self::FAILURE;
        }

        $this->info('正在生成 RSA-4096 密钥对……');

        $key = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            $this->error('密钥生成失败：'.openssl_error_string());

            return self::FAILURE;
        }

        openssl_pkey_export($key, $privatePem);
        $publicPem = openssl_pkey_get_details($key)['key'];

        @mkdir(dirname($privatePath), 0700, true);
        file_put_contents($privatePath, $privatePem);
        @chmod($privatePath, 0600);
        file_put_contents($publicPath, $publicPem);

        $this->info('密钥对已生成：');
        $this->line('  私钥: '.$privatePath);
        $this->line('  公钥: '.$publicPath);

        return self::SUCCESS;
    }
}
