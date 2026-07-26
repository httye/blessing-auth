<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'site:backup {--keep=10 : 保留最近几份备份}';

    protected $description = '创建站点备份（数据库 + 材质 + 密钥），可配合系统 cron 定时执行';

    public function handle(BackupService $backup): int
    {
        $this->info('正在创建备份……');

        $result = $backup->create();

        $this->info('备份完成：'.basename($result['file']).'（'.round($result['size'] / 1048576, 2).' MB）');

        $removed = $backup->prune((int) $this->option('keep'));

        if ($removed > 0) {
            $this->line("已清理 {$removed} 份过期备份。");
        }

        return self::SUCCESS;
    }
}
