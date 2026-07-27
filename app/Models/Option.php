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
        static::loadCache();

        return static::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['name' => $key],
            ['value' => (string) $value],
        );

        static::loadCache();
        static::$cache[$key] = (string) $value;
    }

    /** 请求级缓存，避免每次读取都查库。注意：方法名不能取 load()，会与 Eloquent Model::load() 冲突。 */
    protected static function loadCache(): void
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
