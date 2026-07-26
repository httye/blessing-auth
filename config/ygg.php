<?php

return [
    // 站点设置
    'allow_register' => (bool) env('SITE_ALLOW_REGISTER', true),
    'initial_score' => (int) env('SITE_INITIAL_SCORE', 1000),
    'player_cost' => (int) env('SITE_PLAYER_COST', 100),
    'sign_score' => (int) env('SITE_SIGN_SCORE', 50),

    // Yggdrasil 令牌
    'token_valid_hours' => (int) env('YGG_TOKEN_VALID_HOURS', 72),
    'token_expire_hours' => (int) env('YGG_TOKEN_EXPIRE_HOURS', 360),
    'tokens_limit' => (int) env('YGG_TOKENS_LIMIT', 10),

    // 材质域名白名单
    'skin_domains' => env('YGG_SKIN_DOMAINS', ''),

    // RSA 签名密钥路径
    'private_key_path' => storage_path('yggdrasil/private.pem'),
    'public_key_path' => storage_path('yggdrasil/public.pem'),
];
