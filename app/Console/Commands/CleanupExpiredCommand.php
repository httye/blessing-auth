<?php

namespace App\Console\Commands;

use App\Models\YggToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredCommand extends Command
{
    protected $signature = 'site:cleanup
        {--dry-run : 仅统计不删除}
        {--older-than= : 清理早于 N 天的数据，默认按配置计算（token_expire_hours+72h）}';

    protected $description = '清理过期 Ygg 令牌和 OAuth 访问令牌';

    public function handle(): int
    {
        // 1. Yggdrasil 令牌：超过彻底失效期 + 3 天安全窗口后删除
        $yggExpire = max(option_int('token_expire_hours'), 360);
        $yggBefore = now()->subHours($yggExpire + 72);

        $yggCount = YggToken::query()->where('created_at', '<', $yggBefore)->count();

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将删除 {$yggCount} 条过期 Ygg 令牌");
        } else {
            $deleted = YggToken::query()->where('created_at', '<', $yggBefore)->delete();
            $this->info("已删除 {$deleted} 条过期 Ygg 令牌");
        }

        // 2. OAuth 访问令牌：超过 35 天（30+5 安全窗口）后删除
        $oauthBefore = now()->subDays(35);
        $oauthCount = DB::table('oauth_access_tokens')
            ->where('expires_at', '<', $oauthBefore)
            ->count();

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将删除 {$oauthCount} 条过期 OAuth 令牌");
        } else {
            $deleted = DB::table('oauth_access_tokens')
                ->where('expires_at', '<', $oauthBefore)
                ->delete();
            $this->info("已删除 {$deleted} 条过期 OAuth 令牌");
        }

        // 3. OAuth 刷新令牌：超过 95 天（90+5 安全窗口）后删除
        $refreshBefore = now()->subDays(95);
        $refreshCount = DB::table('oauth_refresh_tokens')
            ->where('expires_at', '<', $refreshBefore)
            ->count();

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将删除 {$refreshCount} 条过期 OAuth 刷新令牌");
        } else {
            DB::table('oauth_refresh_tokens')
                ->where('expires_at', '<', $refreshBefore)
                ->delete();
        }

        // 4. admin_audit_logs 保留 90 天
        $auditBefore = now()->subDays(90);
        $auditCount = DB::table('admin_audit_logs')
            ->where('created_at', '<', $auditBefore)
            ->count();

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将删除 {$auditCount} 条 90 天前的审计日志");
        } else {
            $deleted = DB::table('admin_audit_logs')
                ->where('created_at', '<', $auditBefore)
                ->delete();
            $this->line("已清理 {$deleted} 条 90 天前的审计日志");
        }

        return self::SUCCESS;
    }
}
