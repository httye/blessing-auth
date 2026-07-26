<?php

use App\Plugins\Hook;
use App\Plugins\Plugin;
use AuthMeSync\Sync;
use Illuminate\Support\Facades\Artisan;

require_once __DIR__.'/src/Sync.php';

return function (Plugin $plugin) {

    // 角色创建/改名 → 写入 authme 表
    Hook::listen('player.created', fn ($player) => Sync::upsertPlayer($player));

    Hook::listen('player.renamed', function ($player, $oldName) {
        Sync::removePlayer($oldName);
        Sync::upsertPlayer($player);
    });

    // 角色删除 → 移除记录
    Hook::listen('player.deleted', fn ($player) => Sync::removePlayer($player->name));

    // 网站密码修改（用户自改或管理员重置）→ 更新该用户全部角色的哈希
    Hook::listen('user.password.changed', fn ($user) => Sync::syncUserPassword($user));

    // 全量同步命令：php artisan authme:sync
    if (app()->runningInConsole()) {
        Artisan::command('authme:sync', function () {
            $count = Sync::syncAll();
            $this->info("已同步 {$count} 个角色到 AuthMe 表。");
        })->purpose('全量同步角色与密码哈希到 AuthMe 数据表');
    }
};
