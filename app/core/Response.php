<?php

declare(strict_types=1);

namespace App\core;

final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function notFound(): void
    {
        http_response_code(404);
        echo '404 - Not Found';
    }

    public static function serverError(string $message = 'Internal Server Error'): void
    {
        http_response_code(500);
        echo e($message);
    }
}
