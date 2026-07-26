<?php

namespace App\Services;

use App\Models\ScoreLog;
use App\Models\User;
use App\Plugins\Hook;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 积分服务：所有积分变动的唯一入口。
 * 行级锁防并发双花，每笔变动写 score_logs 流水。
 */
class ScoreService
{
    /**
     * 增加积分。
     */
    public function grant(User $user, int $amount, string $reason): ScoreLog
    {
        if ($amount <= 0) {
            throw new RuntimeException('增加的积分必须为正数。');
        }

        return $this->apply($user, $amount, $reason);
    }

    /**
     * 扣除积分。余额不足抛 InsufficientScoreException。
     */
    public function deduct(User $user, int $amount, string $reason): ScoreLog
    {
        if ($amount <= 0) {
            throw new RuntimeException('扣除的积分必须为正数。');
        }

        return $this->apply($user, -$amount, $reason);
    }

    /**
     * 管理员直接设定余额（差额记流水）。
     */
    public function setBalance(User $user, int $balance, string $reason): ?ScoreLog
    {
        return DB::transaction(function () use ($user, $balance, $reason) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $delta = $balance - $locked->score;

            if ($delta === 0) {
                return null;
            }

            return $this->write($locked, $delta, $reason, $user);
        });
    }

    protected function apply(User $user, int $delta, string $reason): ScoreLog
    {
        return DB::transaction(function () use ($user, $delta, $reason) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->score + $delta < 0) {
                throw new InsufficientScoreException(
                    "积分不足：当前 {$locked->score}，需要 ".abs($delta).'。'
                );
            }

            return $this->write($locked, $delta, $reason, $user);
        });
    }

    protected function write(User $locked, int $delta, string $reason, User $original): ScoreLog
    {
        $locked->score += $delta;
        $locked->save();

        // 同步内存中的原模型，避免调用方拿到旧余额
        $original->score = $locked->score;

        $log = ScoreLog::create([
            'uid' => $locked->id,
            'delta' => $delta,
            'balance_after' => $locked->score,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        Hook::fire('score.changed', $locked, $log);

        return $log;
    }
}
