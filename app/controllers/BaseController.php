<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Csrf;
use App\core\Response;

abstract class BaseController
{
    protected function isAjaxRequest(): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return str_contains($accept, 'application/json');
    }

    protected function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? null;
        if (!Csrf::check(is_string($token) ? $token : null)) {
            if ($this->isAjaxRequest()) {
                Response::json([
                    'ok' => false,
                    'message' => 'Token CSRF invalido.',
                ], 419);
            }

            flash('error', 'Token CSRF invalido.');
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }
}
