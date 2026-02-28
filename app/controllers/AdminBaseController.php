<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\View;
use App\models\AuditLog;

abstract class AdminBaseController extends BaseController
{
    protected function guard(): void
    {
        Auth::requireLogin(true);
    }

    protected function authorize(array $roles): void
    {
        $this->guard();
        $role = (string) (Auth::user()['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            if ($this->isAjaxRequest()) {
                Response::json(['ok' => false, 'message' => 'Sem permissao para esta acao.'], 403);
            }
            flash('error', 'Sem permissao para esta acao.');
            Response::redirect('/admin');
        }
    }

    protected function audit(string $action, string $entityType, ?string $entityId = null, ?array $metadata = null): void
    {
        try {
            (new AuditLog())->log(
                isset(Auth::user()['id']) ? (int) Auth::user()['id'] : null,
                $action,
                $entityType,
                $entityId,
                $metadata
            );
        } catch (\Throwable) {
            // Auditing must not break flow.
        }
    }

    protected function render(string $view, array $data = []): void
    {
        $viewName = basename($view);
        View::render($view, array_merge($data, [
            'currentPath' => current_path(),
            'adminUser' => Auth::user(),
            'adminPage' => $viewName,
        ]), 'admin');
    }
}
