# 插件开发指南

Blessing Auth 内置轻量插件系统：把插件目录放进 `plugins/`，后台启用即可，无需改核心代码、无需 composer。

## 目录结构

```
plugins/
└── my-plugin/
    ├── package.json     # 必需：元信息
    ├── bootstrap.php    # 必需：入口，return 一个闭包
    └── views/           # 可选：Blade 视图，命名空间 = 插件名
        └── index.blade.php
```

### package.json

```json
{
  "name": "my-plugin",
  "title": "我的插件",
  "description": "一句话描述",
  "version": "1.0.0",
  "author": "you",
  "require": ["another-plugin"]
}
```

| 字段 | 必需 | 说明 |
|---|---|---|
| `name` | ✔ | 唯一标识，建议与目录名一致 |
| `title` / `description` / `version` / `author` | — | 后台插件列表展示 |
| `require` | — | 依赖的插件名数组；依赖未启用时无法启用本插件，被依赖时无法停用 |

### bootstrap.php

```php
<?php

use App\Plugins\Hook;
use App\Plugins\Plugin;

return function (Plugin $plugin) {
    // 在这里注册钩子。闭包参数支持 Laravel 容器注入，
    // $plugin 为当前插件实例（name/path/version() 等）。
};
```

引导时机：应用 `boot` 阶段，每次请求都会执行（保持轻量，重活放到路由/队列里）。
单个插件抛异常只会记录日志（`storage/logs/`），不影响站点其他部分。

## 生命周期

1. **发现**：`PluginManager` 扫描 `plugins/*/package.json`
2. **启用**：后台「插件管理」启用，名单存数据库 `options.plugins_enabled`
3. **引导**：每次请求 boot 阶段，按目录名顺序执行已启用插件的 `bootstrap.php`
4. **停用/删除**：后台停用后可直接删目录

## Hook API

全部通过 `App\Plugins\Hook` 静态方法。

### 事件（Actions）

```php
Hook::listen(string $event, callable $callback, int $priority = 10): void
```

`$priority` 越小越先执行。核心事件列表：

| 事件 | 参数 | 触发时机 |
|---|---|---|
| `auth.registered` | `User $user` | 用户注册成功后（登录态建立前） |
| `auth.login` | `User $user` | 网页登录成功后 |
| `player.created` | `Player $player` | 角色创建（事务内） |
| `player.renamed` | `Player $player, string $oldName` | 角色改名后 |
| `player.deleted` | `Player $player` | 角色删除后（模型已删除） |
| `texture.uploaded` | `Texture $texture` | 材质上传成功后 |
| `user.password.changed` | `User $user` | 网站密码修改后（用户自改或管理员重置，令牌已吊销） |
| `ygg.authenticated` | `User $user, YggToken $token` | Yggdrasil 登录成功、令牌签发后 |
| `ygg.auth.failed` | `string $username` | Yggdrasil 登录失败（可做告警/风控） |
| `ygg.joined` | `Player $player, string $serverId` | 服务端 hasJoined 校验通过（玩家实际进服） |
| `score.changed` | `User $user, ScoreLog $log` | 积分变动后（`$log->delta` 正负表收支，`$log->reason` 为原因） |
| `user.banned` | `User $user, User $operator` | 用户被封禁后（令牌已吊销） |
| `user.unbanned` | `User $user, User $operator` | 用户被手动解封后 |

示例——注册欢迎邮件：

```php
Hook::listen('auth.registered', function ($user) {
    Mail::to($user->email)->queue(new WelcomeMail($user));
});
```

### 过滤器（Filters）

```php
Hook::filter(string $name, callable $callback, int $priority = 10): void
```

回调第一个参数是待过滤的值（上一个过滤器的返回值），必须返回处理后的值。

| 过滤器 | 值 | 额外参数 | 用途 |
|---|---|---|---|
| `ygg.profile.textures` | `array $textures`（`SKIN`/`CAPE` 键） | `Player $player` | 修改角色档案的材质集 |

示例——为角色注入 slim（Alex）模型：

```php
Hook::filter('ygg.profile.textures', function (array $textures, $player) {
    if (isset($textures['SKIN']) && /* 你的判断逻辑 */ true) {
        $textures['SKIN']['metadata'] = ['model' => 'slim'];
    }
    return $textures;
});
```

### 路由

```php
Hook::addRoute(function ($router) {
    $router->get('p/my-plugin', [MyController::class, 'index']);
    $router->middleware('auth')->post('p/my-plugin/save', ...);
});
```

- 自动套 `web` 中间件组（session、CSRF）
- 建议统一使用 `p/<插件名>` 前缀避免冲突
- 需要鉴权自行链 `middleware('auth')` / `middleware(['auth', 'admin'])`

### 菜单与页面注入

```php
// 导航菜单：side 为 'user'（所有登录用户）或 'admin'（仅管理员可见区域）
Hook::addMenuItem('user', '我的页面', '/p/my-plugin');

// 向所有页面 <head> 注入 HTML（自定义 CSS/JS/统计代码）
Hook::addHeadHtml('<link rel="stylesheet" href="/plugins-assets/my.css">');
```

### 视图

`views/` 目录自动注册为与插件同名的命名空间：

```php
return view('my-plugin::index', ['data' => $data]);
```

插件视图可以 `@extends('layouts.app')` 复用主站布局。

## 读写站点配置

插件可复用 options 表存自己的配置（建议加前缀防冲突）：

```php
use App\Models\Option;

Option::set('my-plugin.api_key', 'xxx');
$key = Option::get('my-plugin.api_key', 'default');
```

## 操作积分

不要直接改 `users.score` 字段——统一走 `ScoreService`，自动带行级锁和流水记录：

```php
use App\Services\ScoreService;
use App\Services\InsufficientScoreException;

Hook::listen('ygg.joined', function ($player, $serverId) {
    // 玩家每次进服奖励 1 积分
    app(ScoreService::class)->grant($player->user, 1, '进服奖励');
});

// 扣积分要处理余额不足
try {
    app(ScoreService::class)->deduct($user, 50, '兑换披风');
} catch (InsufficientScoreException $e) {
    // 余额不足
}
```

## 数据库

插件如需自建表，在 bootstrap 里判断并执行一次性建表（或提供 artisan 命令）：

```php
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('my_plugin_data')) {
    Schema::create('my_plugin_data', function ($table) {
        $table->id();
        $table->foreignId('uid')->constrained('users')->cascadeOnDelete();
        $table->json('payload');
        $table->timestamps();
    });
}
```

> 注意：每次请求都会执行 bootstrap，`Schema::hasTable` 有查询开销；
> 更好的做法是用 `Option::get('my-plugin.installed')` 做安装标记。

## 注册 OAuth 提供商

内置微软/GitHub/Gitee 之外，插件可注册新的第三方登录：

```php
use App\Services\OAuth\Provider;
use App\Services\OAuth\ProviderRegistry;

ProviderRegistry::register(new Provider(
    name: 'gitlab',
    title: 'GitLab',
    authUrl: 'https://gitlab.com/oauth/authorize',
    tokenUrl: 'https://gitlab.com/oauth/token',
    userUrl: 'https://gitlab.com/api/v4/user',
    scope: 'read_user',
    mapUser: fn (array $u) => [
        'id' => (string) $u['id'],
        'email' => $u['email'] ?? null,
        'nickname' => $u['name'] ?? null,
    ],
));
```

注册后自动出现在后台设置页和登录页（管理员配置启用后）。

## 完整示例

- `plugins/hello-world/` —— 覆盖全部扩展点：事件、过滤器、路由、菜单、视图、head 注入
- `plugins/authme-sync/` —— 实战插件：监听角色/密码事件同步 AuthMe 数据表、注册 artisan 命令、`require_once` 加载自带类、options 存安装标记

## 约定与限制

- 插件在**同一进程**内运行，拥有与核心代码相同的权限——只安装可信插件
- `bootstrap.php` 返回值不是闭包时静默跳过；文件不存在也不报错
- 引导顺序 = 目录名字母序；跨插件依赖用 `require` 声明，不要依赖加载顺序
- 卸载：后台停用 → 删除目录。插件自建的表/配置需自行清理
