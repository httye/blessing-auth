<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Player extends Model
{
    protected $fillable = [
        'uid',
        'name',
        'uuid',
        'tid_skin',
        'tid_cape',
        'last_modified',
    ];

    protected function casts(): array
    {
        return [
            'last_modified' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function skin(): BelongsTo
    {
        return $this->belongsTo(Texture::class, 'tid_skin');
    }

    public function cape(): BelongsTo
    {
        return $this->belongsTo(Texture::class, 'tid_cape');
    }

    /**
     * 生成与离线模式一致的 UUIDv3:
     * md5("OfflinePlayer:" + name)，并设置版本位。
     */
    public static function offlineUuid(string $name): string
    {
        $data = md5('OfflinePlayer:'.$name, true);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x30); // version 3
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

        return Str::lower(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
    }

    /** 无连字符 UUID（Yggdrasil 协议使用） */
    public function undashedUuid(): string
    {
        return str_replace('-', '', $this->uuid);
    }

    public function touchLastModified(): void
    {
        $this->last_modified = now();
        $this->save();
    }
}
