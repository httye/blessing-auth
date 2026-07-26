<?php

namespace App\Plugins;

/**
 * 一个已发现的插件。
 * 对应 plugins/<name>/ 目录，元信息来自其中的 package.json。
 */
class Plugin
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array $manifest,
        public bool $enabled = false,
    ) {
    }

    public function title(): string
    {
        return $this->manifest['title'] ?? $this->name;
    }

    public function description(): string
    {
        return $this->manifest['description'] ?? '';
    }

    public function version(): string
    {
        return $this->manifest['version'] ?? '0.0.0';
    }

    public function author(): string
    {
        return $this->manifest['author'] ?? 'unknown';
    }

    /** 声明的依赖插件名列表 */
    public function dependencies(): array
    {
        return $this->manifest['require'] ?? [];
    }

    public function bootstrapFile(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'bootstrap.php';
    }

    /** 插件自带视图目录（可选） */
    public function viewsPath(): ?string
    {
        $dir = $this->path.DIRECTORY_SEPARATOR.'views';

        return is_dir($dir) ? $dir : null;
    }
}
