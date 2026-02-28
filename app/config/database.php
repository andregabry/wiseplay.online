<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'wiseplay_php'),
    'username' => env('DB_USERNAME', 'wiseplay_user'),
    'password' => env('DB_PASSWORD', 'wiseplay_pass'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];
