<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],
        'textures' => [
            'driver' => 'local',
            'root' => storage_path('textures'),
            'throw' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
        'webdav' => [
            'driver' => 'webdav',
            'baseUri' => env('WEBDAV_URL'),
            'userName' => env('WEBDAV_USERNAME'),
            'password' => env('WEBDAV_PASSWORD'),
            'throw' => false,
        ],
    ],
];
