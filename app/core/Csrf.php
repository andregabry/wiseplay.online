<?php

declare(strict_types=1);

namespace App\core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function check(?string $token): bool
    {
        $stored = $_SESSION['_csrf'] ?? '';
        if (!$token || !$stored) {
            return false;
        }
        return hash_equals((string) $stored, (string) $token);
    }
}
