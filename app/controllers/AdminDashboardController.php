<?php

declare(strict_types=1);

namespace App\controllers;

use App\models\DashboardStats;
use App\services\OneDriveService;

final class AdminDashboardController extends AdminBaseController
{
    private const MANAGE_ONEDRIVE_ROLES = ['SUPER_ADMIN', 'ADMIN'];

    public function index(): void
    {
        $this->guard();

        try {
            $stats = (new DashboardStats())->summary();
            $recentAudits = (new DashboardStats())->recentAudits(8);
        } catch (\Throwable) {
            $stats = [
                'users_total' => 0,
                'users_active' => 0,
                'titles_published' => 0,
                'media_published' => 0,
                'subscriptions_active' => 0,
                'profiles_total' => 0,
            ];
            $recentAudits = [];
        }

        try {
            $oneDriveService = new OneDriveService();
            $oneDriveQuota = $oneDriveService->presentQuota($oneDriveService->fetchDriveQuota(false));
        } catch (\Throwable) {
            $oneDriveQuota = [
                'connected' => false,
                'has_quota' => false,
                'message' => 'Nao foi possivel carregar quota OneDrive agora.',
                'total_human' => '-',
                'used_human' => '-',
                'remaining_human' => '-',
                'percentage' => 0.0,
                'percentage_label' => '0.0%',
                'state' => 'unknown',
                'state_label' => 'Unknown',
                'state_badge_class' => 'bg-label-secondary',
                'progress_bar_class' => 'bg-secondary',
                'last_updated' => '-',
                'plan_hint' => '',
                'from_cache' => false,
                'is_stale' => false,
            ];
        }

        $this->render('admin/dashboard', [
            'title' => 'Dashboard | WisePlay Admin',
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'recentAudits' => $recentAudits,
            'oneDriveQuota' => $oneDriveQuota,
            'canManageOneDrive' => in_array((string) (($_SESSION['auth_user']['role'] ?? '')), self::MANAGE_ONEDRIVE_ROLES, true),
        ]);
    }
}
