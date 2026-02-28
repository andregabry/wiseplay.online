<?php

declare(strict_types=1);

function load_env(string $filePath): void
{
    if (!is_file($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }

        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];

    if ($cache === []) {
        $cache = [
            'app' => require BASE_PATH . '/app/config/app.php',
            'database' => require BASE_PATH . '/app/config/database.php',
        ];
    }

    $segments = explode('.', $key);
    $value = $cache;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['_flash'])) {
        return null;
    }

    $data = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $data;
}
