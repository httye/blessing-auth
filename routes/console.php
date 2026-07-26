<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment('Blessing Auth is running.');
})->purpose('Display an inspiring quote');

// ----- 定时调度（Laravel Schedule 自动注册，每分钟跑一次） -----
app()->booted(function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    // 每日凌晨 3 点清理过期数据
    $schedule->command('site:cleanup')
        ->dailyAt('03:00')
        ->onOneServer();

    // 可选：定时备份（需先在 .env 配置好备份设置）
    // $schedule->command('site:backup --keep=10')
    //     ->dailyAt('04:00')
    //     ->onOneServer();
});
