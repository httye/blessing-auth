<?php

namespace App\Plugins;

/**
 * 插件钩子系统：actions（动作，无返回值）+ filters（过滤器，链式修改值）。
 *
 * 用法（插件 bootstrap.php 内）：
 *   Hook::listen('auth.registered', fn ($user) => ...);
 *   Hook::filter('texture.upload.validate', fn ($valid, $file, $type) => $valid);
 *   Hook::addRoute(fn ($router) => $router->get(...));
 *   Hook::addMenuItem('user', '标题', '/url');
 */
class Hook
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    protected static array $actions = [];

    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    protected static array $filters = [];

    /** @var callable[] */
    protected static array $routes = [];

    /** @var array<string, array<int, array{title: string, url: string}>> */
    protected static array $menu = ['user' => [], 'admin' => []];

    /** @var string[] 注入到布局 <head> 的 HTML 片段 */
    protected static array $head = [];

    // ---------- actions ----------

    /** 监听动作事件 */
    public static function listen(string $event, callable $callback, int $priority = 10): void
    {
        static::$actions[$event][] = ['callback' => $callback, 'priority' => $priority];
    }

    /** 触发动作事件（核心代码调用） */
    public static function fire(string $event, mixed ...$payload): void
    {
        $listeners = static::$actions[$event] ?? [];
        usort($listeners, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($listeners as $listener) {
            ($listener['callback'])(...$payload);
        }
    }

    // ---------- filters ----------

    /** 注册过滤器 */
    public static function filter(string $name, callable $callback, int $priority = 10): void
    {
        static::$filters[$name][] = ['callback' => $callback, 'priority' => $priority];
    }

    /** 应用过滤器链（核心代码调用），$value 依次经过所有回调 */
    public static function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        $filters = static::$filters[$name] ?? [];
        usort($filters, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($filters as $filter) {
            $value = ($filter['callback'])($value, ...$args);
        }

        return $value;
    }

    // ---------- 扩展点 ----------

    /** 注册插件路由（回调收到 Router 实例） */
    public static function addRoute(callable $callback): void
    {
        static::$routes[] = $callback;
    }

    /** @internal 核心调用 */
    public static function routes(): array
    {
        return static::$routes;
    }

    /** 向导航菜单添加条目，$side 为 user 或 admin */
    public static function addMenuItem(string $side, string $title, string $url): void
    {
        static::$menu[$side][] = ['title' => $title, 'url' => $url];
    }

    /** @internal 布局视图调用 */
    public static function menu(string $side): array
    {
        return static::$menu[$side] ?? [];
    }

    /** 向布局 <head> 注入 HTML（如自定义 CSS/JS） */
    public static function addHeadHtml(string $html): void
    {
        static::$head[] = $html;
    }

    /** @internal 布局视图调用 */
    public static function headHtml(): string
    {
        return implode("\n", static::$head);
    }

    /** @internal 测试用 */
    public static function flush(): void
    {
        static::$actions = [];
        static::$filters = [];
        static::$routes = [];
        static::$menu = ['user' => [], 'admin' => []];
        static::$head = [];
    }
}
