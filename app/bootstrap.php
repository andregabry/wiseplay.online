<?php

declare(strict_types=1);

if (defined('WISEPLAY_BOOTSTRAPPED')) {
    return;
}
define('WISEPLAY_BOOTSTRAPPED', true);
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/helpers/functions.php';

load_env(BASE_PATH . '/.env');

$sessionName = env('SESSION_NAME', 'WISEPLAYSESSID') ?: 'WISEPLAYSESSID';
session_name($sessionName);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = BASE_PATH . '/app/' . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
