<?php

namespace App\Providers;

use App\Plugins\Hook;
use App\Plugins\PluginManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class);
    }

    public function boot(): void
    {
        // 运行 artisan package:discover 等无数据库场景时跳过插件引导
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $command = $_SERVER['argv'][1] ?? '';
            if (in_array($command, ['package:discover', 'key:generate'], true)) {
                return;
            }
        }

        $plugins = $this->app->make(PluginManager::class);
        $plugins->boot();

        // 注册插件路由（web 中间件组，前缀 /p/<插件自定路径>）
        foreach (Hook::routes() as $callback) {
            Route::middleware('web')->group(fn () => $callback(Route::getFacadeRoot()));
        }
    }
}
