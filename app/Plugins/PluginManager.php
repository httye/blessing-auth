<?php

namespace App\Plugins;

use App\Models\Option;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * 插件管理器：发现、启停、引导插件。
 *
 * 插件 = plugins/<name>/ 目录：
 *   package.json   元信息 {name, title, description, version, author, require: []}
 *   bootstrap.php  入口文件，return function () { ... };
 *   views/         （可选）Blade 视图，命名空间为插件名
 *
 * 已启用插件名单存 options 表（plugins_enabled，逗号分隔）。
 */
class PluginManager
{
    /** @var array<string, Plugin>|null */
    protected ?array $plugins = null;

    protected bool $booted = false;

    public function pluginsDir(): string
    {
        return base_path('plugins');
    }

    /** @return array<string, Plugin> */
    public function all(): array
    {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $this->plugins = [];
        $dir = $this->pluginsDir();

        if (! is_dir($dir)) {
            return $this->plugins;
        }

        $enabled = $this->enabledNames();

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            $manifestFile = $path.DIRECTORY_SEPARATOR.'package.json';

            if (! is_dir($path) || ! is_file($manifestFile)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestFile), true);

            if (! is_array($manifest)) {
                Log::warning("插件 {$entry} 的 package.json 解析失败，已跳过。");

                continue;
            }

            $name = $manifest['name'] ?? $entry;

            $this->plugins[$name] = new Plugin(
                name: $name,
                path: $path,
                manifest: $manifest,
                enabled: in_array($name, $enabled, true),
            );
        }

        ksort($this->plugins);

        return $this->plugins;
    }

    public function get(string $name): ?Plugin
    {
        return $this->all()[$name] ?? null;
    }

    /** @return array<string, Plugin> 仅已启用的插件 */
    public function enabled(): array
    {
        return array_filter($this->all(), fn (Plugin $p) => $p->enabled);
    }

    /**
     * 引导所有已启用插件（在 AppServiceProvider::boot 调用）。
     * 单个插件抛异常只记日志，不拖垮整站。
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->enabled() as $plugin) {
            $file = $plugin->bootstrapFile();

            if (! is_file($file)) {
                continue;
            }

            try {
                if ($views = $plugin->viewsPath()) {
                    View::addNamespace($plugin->name, $views);
                }

                $bootstrap = require $file;

                if (is_callable($bootstrap)) {
                    app()->call($bootstrap, ['plugin' => $plugin]);
                }
            } catch (Throwable $e) {
                Log::error("插件 {$plugin->name} 引导失败：{$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    public function enable(string $name): bool
    {
        $plugin = $this->get($name);

        if ($plugin === null) {
            return false;
        }

        // 检查依赖
        foreach ($plugin->dependencies() as $dep) {
            $depPlugin = $this->get($dep);
            if ($depPlugin === null || ! $depPlugin->enabled) {
                throw new \RuntimeException("依赖插件 {$dep} 未启用。");
            }
        }

        $names = $this->enabledNames();

        if (! in_array($name, $names, true)) {
            $names[] = $name;
            $this->saveEnabledNames($names);
        }

        $plugin->enabled = true;

        return true;
    }

    public function disable(string $name): bool
    {
        $plugin = $this->get($name);

        if ($plugin === null) {
            return false;
        }

        // 有其他已启用插件依赖它时禁止停用
        foreach ($this->enabled() as $other) {
            if ($other->name !== $name && in_array($name, $other->dependencies(), true)) {
                throw new \RuntimeException("插件 {$other->name} 依赖该插件，请先停用它。");
            }
        }

        $this->saveEnabledNames(array_values(array_diff($this->enabledNames(), [$name])));
        $plugin->enabled = false;

        return true;
    }

    /** @return string[] */
    protected function enabledNames(): array
    {
        $raw = (string) Option::get('plugins_enabled', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    protected function saveEnabledNames(array $names): void
    {
        Option::set('plugins_enabled', implode(',', $names));
    }
}
