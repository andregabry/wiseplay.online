<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\View;
use App\models\AuditLog;
use App\models\DashboardStats;

abstract class AdminBaseController extends BaseController
{
    private const PAGE_META = [
        'dashboard' => [
            'title' => 'Dashboard',
            'description' => 'Executive overview of users, catalog, subscriptions, and recent platform activity.',
            'icon' => 'ri-dashboard-line',
            'eyebrow' => 'Overview',
        ],
        'producer_titles' => [
            'title' => 'Producer Titles',
            'description' => 'Marketplace tenant dashboard with title isolation by producer_id.',
            'icon' => 'ri-store-3-line',
            'eyebrow' => 'Marketplace',
        ],
        'producer_offers' => [
            'title' => 'Producer Offers',
            'description' => 'Manage paid offers per title with producer-scoped visibility and availability.',
            'icon' => 'ri-price-tag-3-line',
            'eyebrow' => 'Marketplace',
        ],
        'users' => [
            'title' => 'Users',
            'description' => 'Manage accounts, roles, statuses, plans, and administrative actions in one place.',
            'icon' => 'ri-user-line',
            'eyebrow' => 'Accounts',
        ],
        'profiles' => [
            'title' => 'Profiles',
            'description' => 'Review public profiles, kids accounts, and profile maintenance operations.',
            'icon' => 'ri-team-line',
            'eyebrow' => 'Profiles',
        ],
        'titles' => [
            'title' => 'Titles',
            'description' => 'Organize movies, series, and courses with editorial status and visual assets.',
            'icon' => 'ri-movie-2-line',
            'eyebrow' => 'Catalog',
        ],
        'media' => [
            'title' => 'Media',
            'description' => 'Control uploads, OneDrive sources, episode hierarchy, and publication state.',
            'icon' => 'ri-film-line',
            'eyebrow' => 'Library',
        ],
        'billing' => [
            'title' => 'Billing',
            'description' => 'Configure plans, limits, subscription billing, and commercial availability.',
            'icon' => 'ri-bank-card-line',
            'eyebrow' => 'Revenue',
        ],
        'settings' => [
            'title' => 'Settings',
            'description' => 'Centralize general settings, OneDrive, SMTP, upload, and runtime controls.',
            'icon' => 'ri-settings-4-line',
            'eyebrow' => 'Configuration',
        ],
        'me' => [
            'title' => 'My Profile',
            'description' => 'Update personal details, account security, and admin preferences.',
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

    protected function requireProducerContext(): array
    {
        $this->authorize(['PRODUCER']);

        $producerId = Auth::producerId();
        if ($producerId === null || $producerId <= 0) {
            flash('error', 'Nenhum tenant de produtor ativo foi encontrado para este usuario.');
            Response::redirect('/admin/login');
        }

        return [
            'id' => $producerId,
            'brand_name' => (string) (Auth::user()['producer_brand_name'] ?? 'Producer'),
            'slug' => (string) (Auth::user()['producer_slug'] ?? 'producer'),
        ];
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
            'description' => 'Manage this WisePlay administrative area.',
            'icon' => 'ri-layout-grid-line',
            'eyebrow' => 'Admin',
        ];

        $notifications = [];
        try {
            $notifications = (new DashboardStats())->recentAudits(6);
        } catch (\Throwable) {
            $notifications = [];
        }

        View::render($view, array_merge($data, [
            'currentPath' => current_path(),
            'adminUser' => Auth::user(),
            'adminPage' => $viewName,
            'pageTitle' => $data['pageTitle'] ?? $pageMeta['title'],
            'pageDescription' => $data['pageDescription'] ?? $pageMeta['description'],
            'pageIcon' => $data['pageIcon'] ?? $pageMeta['icon'],
            'pageEyebrow' => $data['pageEyebrow'] ?? $pageMeta['eyebrow'],
            'adminNotifications' => $notifications,
        ]), 'admin');
    }
}
