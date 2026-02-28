<?php

declare(strict_types=1);

namespace App\models;

final class DashboardStats extends BaseModel
{
    public function summary(): array
    {
        $queries = [
            'users_total' => 'SELECT COUNT(*) FROM users',
            'users_active' => "SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'",
            'titles_published' => "SELECT COUNT(*) FROM titles WHERE status = 'PUBLISHED'",
            'media_published' => 'SELECT COUNT(*) FROM media_items WHERE is_published = 1',
            'subscriptions_active' => "SELECT COUNT(*) FROM subscriptions WHERE status IN ('ACTIVE','TRIALING')",
            'profiles_total' => 'SELECT COUNT(*) FROM profiles',
        ];

        $result = [];
        foreach ($queries as $key => $sql) {
            $result[$key] = (int) $this->db()->query($sql)->fetchColumn();
        }

        return $result;
    }

    public function recentAudits(int $limit = 8): array
    {
        $stmt = $this->db()->prepare('SELECT a.*, u.name AS actor_name
                                        FROM audit_logs a
                                        LEFT JOIN users u ON u.id = a.actor_user_id
                                       ORDER BY a.id DESC
                                       LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
