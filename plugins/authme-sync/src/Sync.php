<?php

/**
 * AuthMe 同步服务。
 *
 * 原理：AuthMeReloaded 支持 MySQL/SQLite 数据源 + BCRYPT 哈希。
 * 本站密码本就是 bcrypt，因此把「角色名 + 用户密码哈希」写入 authme 表，
 * AuthMe 直接读取即可 —— 玩家游戏内 /login 的密码 = 网站密码，全程无明文。
 *
 * 注意：PHP bcrypt 前缀为 $2y$，Java BCrypt 库要求 $2a$，两者算法相同，
 * 写入时替换前缀即可。
 */

namespace AuthMeSync;

use App\Models\Option;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Sync
{
    public static function tableName(): string
    {
        return (string) Option::get('authme-sync.table', 'authme');
    }

    /** 首次启用时建表（与 AuthMe 默认 MySQL 结构兼容的最小子集） */
    public static function ensureTable(): void
    {
        if (Option::get('authme-sync.installed')) {
            return;
        }

        $table = static::tableName();

        if (! Schema::hasTable($table)) {
            Schema::create($table, function ($t) {
                $t->integer('id', true);
                $t->string('username', 255)->unique();  // 小写角色名
                $t->string('realname', 255);            // 原始大小写
                $t->string('password', 255);
                $t->string('ip', 40)->nullable();
                $t->bigInteger('lastlogin')->nullable();
                $t->double('x')->default(0);
                $t->double('y')->default(0);
                $t->double('z')->default(0);
                $t->string('world', 255)->default('world');
                $t->bigInteger('regdate')->default(0);
                $t->string('regip', 40)->nullable();
                $t->string('email', 255)->nullable();
                $t->smallInteger('isLogged')->default(0);
                $t->smallInteger('hasSession')->default(0);
            });
        }

        Option::set('authme-sync.installed', '1');
    }

    /** PHP $2y$ → Java $2a$ */
    public static function toJavaBcrypt(string $hash): string
    {
        return preg_replace('/^\$2y\$/', '\$2a\$', $hash);
    }

    /** 写入/更新一个角色的 AuthMe 记录 */
    public static function upsertPlayer(Player $player, ?User $user = null): void
    {
        try {
            static::ensureTable();

            $user ??= $player->user;

            DB::table(static::tableName())->updateOrInsert(
                ['username' => mb_strtolower($player->name)],
                [
                    'realname' => $player->name,
                    'password' => static::toJavaBcrypt($user->password),
                    'email' => $user->email,
                    'regdate' => ($player->created_at ?? now())->getTimestampMs(),
                ],
            );
        } catch (\Throwable $e) {
            Log::error("[authme-sync] 同步角色 {$player->name} 失败：{$e->getMessage()}");
        }
    }

    public static function removePlayer(string $name): void
    {
        try {
            static::ensureTable();
            DB::table(static::tableName())->where('username', mb_strtolower($name))->delete();
        } catch (\Throwable $e) {
            Log::error("[authme-sync] 删除 {$name} 失败：{$e->getMessage()}");
        }
    }

    /** 用户密码变更 → 更新其全部角色 */
    public static function syncUserPassword(User $user): void
    {
        foreach ($user->players as $player) {
            static::upsertPlayer($player, $user);
        }
    }

    /** 全量重建（artisan authme:sync） */
    public static function syncAll(): int
    {
        static::ensureTable();
        $count = 0;

        Player::query()->with('user')->chunkById(100, function ($players) use (&$count) {
            foreach ($players as $player) {
                static::upsertPlayer($player, $player->user);
                $count++;
            }
        });

        return $count;
    }
}
