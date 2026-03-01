<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\View;
use App\models\AuditLog;

abstract class AdminBaseController extends BaseController
{
    private const PAGE_META = [
        'dashboard' => [
            'title' => 'Dashboard',
            'description' => 'Visao executiva do WisePlay com indicadores de usuarios, catalogo, perfis, assinaturas e eventos recentes.',
            'icon' => 'ri-dashboard-line',
            'eyebrow' => 'Overview',
        ],
        'users' => [
            'title' => 'Users',
            'description' => 'Gerencie contas, papeis, status, planos e operacoes administrativas em uma unica visao.',
            'icon' => 'ri-user-line',
            'eyebrow' => 'Accounts',
        ],
        'profiles' => [
            'title' => 'Profiles',
            'description' => 'Acompanhe perfis criados no app publico, perfis kids e operacoes de manutencao.',
            'icon' => 'ri-team-line',
            'eyebrow' => 'Profiles',
        ],
        'titles' => [
            'title' => 'Titles',
            'description' => 'Organize o catalogo de filmes, series e cursos com status editorial e midias visuais.',
            'icon' => 'ri-movie-2-line',
            'eyebrow' => 'Catalog',
        ],
        'media' => [
            'title' => 'Media',
            'description' => 'Controle uploads, fontes OneDrive, hierarquia de episodios e publicacao de conteudo.',
            'icon' => 'ri-film-line',
            'eyebrow' => 'Library',
        ],
        'billing' => [
            'title' => 'Billing',
            'description' => 'Configure planos, limites, cobranca, assinaturas e disponibilidade comercial do produto.',
            'icon' => 'ri-bank-card-line',
            'eyebrow' => 'Revenue',
        ],
        'settings' => [
            'title' => 'Settings',
            'description' => 'Centralize configuracoes gerais, OneDrive, SMTP, upload e parametros operacionais.',
            'icon' => 'ri-settings-4-line',
            'eyebrow' => 'Configuration',
        ],
        'me' => [
            'title' => 'My Profile',
            'description' => 'Atualize seus dados pessoais, seguranca da conta e preferencias administrativas.',
            'icon' => 'ri-user-settings-line',
            'eyebrow' => 'Account',
        ],
    ];

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
        $pageMeta = self::PAGE_META[$viewName] ?? [
            'title' => $data['pageTitle'] ?? 'Admin',
            'description' => 'Gerencie esta area administrativa do WisePlay.',
            'icon' => 'ri-layout-grid-line',
            'eyebrow' => 'Admin',
        ];

        View::render($view, array_merge($data, [
            'currentPath' => current_path(),
            'adminUser' => Auth::user(),
            'adminPage' => $viewName,
            'pageTitle' => $data['pageTitle'] ?? $pageMeta['title'],
            'pageDescription' => $data['pageDescription'] ?? $pageMeta['description'],
            'pageIcon' => $data['pageIcon'] ?? $pageMeta['icon'],
            'pageEyebrow' => $data['pageEyebrow'] ?? $pageMeta['eyebrow'],
        ]), 'admin');
    }
}
