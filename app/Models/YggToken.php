<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YggToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'access_token',
        'client_token',
        'owner',
        'player_uuid',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner');
    }

    /** 令牌是否仍可用于 validate / join */
    public function isValid(): bool
    {
        return $this->created_at->addHours(option_int('token_valid_hours'))->isFuture();
    }

    /** 令牌是否仍可 refresh（暂存期内） */
    public function isRefreshable(): bool
    {
        return $this->created_at->addHours(option_int('token_expire_hours'))->isFuture();
    }
}
