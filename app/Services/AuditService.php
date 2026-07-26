<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;

class AuditService
{
    public const ACTION_MODIFY_PERMISSION = 'modify_permission';
    public const ACTION_BAN_USER = 'ban_user';
    public const ACTION_UNBAN_USER = 'unban_user';
    public const ACTION_DELETE_USER = 'delete_user';
    public const ACTION_RESET_PASSWORD = 'reset_password';
    public const ACTION_MODIFY_SCORE = 'modify_score';
    public const ACTION_DELETE_TEXTURE = 'delete_texture';
    public const ACTION_DELETE_PLAYER = 'delete_player';
    public const ACTION_LOGIN_AS = 'login_as';
    public const ACTION_UPDATE_OPTION = 'update_option';

    public function log(
        string $action,
        ?User $targetUser = null,
        ?string $detail = null,
        ?int $operatorId = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'operator_id' => $operatorId ?? (auth()->id() ?: null),
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'detail' => $detail,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function logWithTarget(string $action, ?User $targetUser, ?string $detail = null): AdminAuditLog
    {
        return $this->log($action, $targetUser, $detail);
    }

    public function logSimple(string $action, string $detail): AdminAuditLog
    {
        return $this->log($action, null, $detail);
    }
}