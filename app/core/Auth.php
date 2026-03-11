<?php

declare(strict_types=1);

namespace App\core;

final class Auth
{
    private const KEY = 'auth_user';
    private const PLATFORM_ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'EDITOR', 'SUPPORT', 'BILLING_MANAGER'];

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
            'producer_id' => isset($user['producer_id']) ? (int) $user['producer_id'] : null,
            'producer_brand_name' => (string) ($user['producer_brand_name'] ?? ''),
            'producer_slug' => (string) ($user['producer_slug'] ?? ''),
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

    public static function isPlatformAdmin(): bool
    {
        $role = self::user()['role'] ?? null;
        return in_array($role, self::PLATFORM_ADMIN_ROLES, true);
    }

    public static function isProducer(): bool
    {
        return (self::user()['role'] ?? null) === 'PRODUCER';
    }

    public static function isAdmin(): bool
    {
        return self::isPlatformAdmin() || self::isProducer();
    }

    public static function producerId(): ?int
    {
        $producerId = self::user()['producer_id'] ?? null;
        if ($producerId === null || $producerId === '') {
            return null;
        }

        return (int) $producerId;
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
