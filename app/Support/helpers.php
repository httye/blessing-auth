<?php

use App\Models\Option;

if (! function_exists('option')) {
    /**
     * 读取站点设置。优先级：数据库 options 表 > config/ygg.php（即 .env 默认值）。
     */
    function option(string $key, mixed $default = null): mixed
    {
        return Option::get($key, $default ?? config("ygg.$key"));
    }
}

if (! function_exists('option_bool')) {
    function option_bool(string $key): bool
    {
        return filter_var(option($key), FILTER_VALIDATE_BOOLEAN);
    }
}

if (! function_exists('option_int')) {
    function option_int(string $key): int
    {
        return (int) option($key);
    }
}
