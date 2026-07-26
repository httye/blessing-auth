<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class Option extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'value'];

    /** 请求级缓存，避免每次读取都查库 */
    protected static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        static::load();

        return static::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['name' => $key],
            ['value' => (string) $value],
        );

        static::load();
        static::$cache[$key] = (string) $value;
    }

    protected static function load(): void
    {
        if (static::$cache !== null) {
            return;
        }

        try {
            static::$cache = static::query()->pluck('value', 'name')->all();
        } catch (Throwable) {
            // 迁移执行前表不存在时静默回退
            static::$cache = [];
        }
    }
}
