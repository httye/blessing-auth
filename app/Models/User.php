<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const PERMISSION_BANNED = -1;
    public const PERMISSION_NORMAL = 0;
    public const PERMISSION_ADMIN = 1;
    public const PERMISSION_SUPER = 2;

    protected $fillable = [
        'email',
        'nickname',
        'password',
        'score',
        'permission',
        'current_title_id',
        'ban_reason',
        'ban_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_sign_at' => 'datetime',
            'ban_until' => 'datetime',
            'score' => 'integer',
            'permission' => 'integer',
            'verified' => 'boolean',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'uid');
    }

    public function textures(): HasMany
    {
        return $this->hasMany(Texture::class, 'uploader');
    }

    public function yggTokens(): HasMany
    {
        return $this->hasMany(YggToken::class, 'owner');
    }

    public function scoreLogs(): HasMany
    {
        return $this->hasMany(ScoreLog::class, 'uid');
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class, 'uid');
    }

    public function titles(): BelongsToMany
    {
        return $this->belongsToMany(Title::class, 'user_titles', 'uid', 'title_id')
            ->withPivot('acquired_at');
    }

    public function currentTitle(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'current_title_id');
    }

    public function isAdmin(): bool
    {
        return $this->permission >= self::PERMISSION_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->permission >= self::PERMISSION_SUPER;
    }

    /**
     * 是否处于封禁状态。临时封禁到期时自动解封（惰性）。
     */
    public function isBanned(): bool
    {
        if ($this->permission > self::PERMISSION_BANNED) {
            return false;
        }

        // 临时封禁已到期 → 自动解封
        if ($this->ban_until !== null && $this->ban_until->isPast()) {
            $this->forceFill([
                'permission' => self::PERMISSION_NORMAL,
                'ban_reason' => null,
                'ban_until' => null,
            ])->save();

            return false;
        }

        return true;
    }

    /** 封禁提示文本（登录页展示） */
    public function banMessage(): string
    {
        $msg = '该账号已被封禁';

        if ($this->ban_until !== null) {
            $msg .= '，解封时间：'.$this->ban_until->format('Y-m-d H:i');
        }

        if ($this->ban_reason) {
            $msg .= '。原因：'.$this->ban_reason;
        }

        return $msg.'。';
    }

    public function canSignToday(): bool
    {
        return $this->last_sign_at === null
            || ! $this->last_sign_at->isToday();
    }
}
