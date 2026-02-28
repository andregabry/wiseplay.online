<?php

declare(strict_types=1);

namespace App\core;

final class Auth
{
    private const KEY = 'auth_user';

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::KEY] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'account_avatar' => (string) ($user['account_avatar'] ?? ''),
            'role' => (string) $user['role'],
            'status' => (string) $user['status'],
            'email_verified_at' => (string) ($user['email_verified_at'] ?? ''),
            'password_set_at' => (string) ($user['password_set_at'] ?? ''),
            'created_at' => (string) ($user['created_at'] ?? ''),
            'last_login_at' => (string) ($user['last_login_at'] ?? ''),
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION[self::KEY]);
        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        return $_SESSION[self::KEY] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $role = self::user()['role'] ?? null;
        return in_array($role, ['SUPER_ADMIN', 'ADMIN', 'EDITOR', 'SUPPORT', 'BILLING_MANAGER'], true);
    }

    public static function requireLogin(bool $admin = false): void
    {
        if (!self::check()) {
            Response::redirect($admin ? '/admin/login' : '/login');
        }

        if ($admin && !self::isAdmin()) {
            flash('error', 'Sem permissao para acessar o admin.');
            Response::redirect('/browse');
        }
    }
}
