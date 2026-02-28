<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'WisePlay'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://wiseplay.local:8080'),
    'key' => env('APP_KEY', 'change_this_with_a_random_32_char_key'),
    'session_name' => env('SESSION_NAME', 'WISEPLAYSESSID'),
];
