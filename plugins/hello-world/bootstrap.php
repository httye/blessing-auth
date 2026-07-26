<?php

use App\Plugins\Hook;
use App\Plugins\Plugin;
use Illuminate\Support\Facades\Log;

/**
 * 插件入口。返回的闭包在应用 boot 阶段被调用，
 * 参数支持依赖注入，$plugin 为当前插件实例。
 */
return function (Plugin $plugin) {

    // 1. 监听事件：用户注册后写日志
    Hook::listen('auth.registered', function ($user) {
        Log::info("[hello-world] 新用户注册：{$user->email}");
    });

    // 2. 过滤器：给每个角色档案的 SKIN 附加 slim 模型示例
    //    （实际用途：按角色偏好注入 metadata）
    Hook::filter('ygg.profile.textures', function (array $textures, $player) {
        // 示例：角色名以下划线结尾时使用纤细手臂模型
        if (isset($textures['SKIN']) && str_ends_with($player->name, '_')) {
            $textures['SKIN']['metadata'] = ['model' => 'slim'];
        }

        return $textures;
    });

    // 3. 注册路由：/p/hello-world
    Hook::addRoute(function ($router) {
        $router->get('p/hello-world', function () {
            return view('hello-world::index');
        })->name('plugin.hello-world');
    });

    // 4. 导航菜单项（user 侧）
    Hook::addMenuItem('user', 'Hello World', '/p/hello-world');

    // 5. head 注入
    Hook::addHeadHtml('<!-- hello-world plugin v'.$plugin->version().' -->');
};
