<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class TextureStorage
{
    /** 存储后端，由 TEXTURE_STORAGE 环境变量控制 */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $driver = (string) option('texture_storage_driver', 'local');

        return Storage::disk($driver === 's3' ? 's3' : ($driver === 'webdav' ? 'webdav' : 'textures'));
    }
    /**
     * 校验并保存材质 PNG，按内容 SHA-256 去重。
     *
     * @return array{hash: string, size: int} hash 与文件大小（KB）
     */
    public function store(UploadedFile $file, string $type): array
    {
        $content = $file->get();

        // 必须是合法 PNG
        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new InvalidArgumentException('无法解析图片文件。');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        if ($type === 'skin') {
            // 64x32（旧版）或 64x64（新版）及其整数倍高清皮肤
            $valid = ($width % 64 === 0)
                && ($height === $width || $height === $width / 2)
                && $width >= 64 && $width <= 1024;
        } else {
            // 披风 64x32 比例（含 22x17 旧格式放宽为 2:1 或 64:32 系）
            $valid = ($width >= 22 && $width <= 1024)
                && ($height * 2 === $width || ($width === 22 && $height === 17));
        }

        if (! $valid) {
            throw new InvalidArgumentException(
                $type === 'skin'
                    ? '皮肤尺寸不合法，应为 64x32、64x64 或其整数倍。'
                    : '披风尺寸不合法，应为 2:1 比例（如 64x32）。'
            );
        }

        $hash = hash('sha256', $content);
        $disk = $this->disk();

        if (! $disk->exists($hash)) {
            $disk->put($hash, $content);
        }

        return [
            'hash' => $hash,
            'size' => (int) ceil(strlen($content) / 1024),
        ];
    }

    /** 若无其他材质记录引用该 hash，删除物理文件 */
    public function deleteIfUnused(string $hash): void
    {
        $stillUsed = \App\Models\Texture::query()->where('hash', $hash)->exists();

        if (! $stillUsed) {
            $this->disk()->delete($hash);
        }
    }

    public function delete(string $hash): void
    {
        $this->disk()->delete($hash);
    }
}
