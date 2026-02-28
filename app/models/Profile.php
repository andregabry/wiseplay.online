<?php

declare(strict_types=1);

namespace App\models;

final class Profile extends BaseModel
{
    public const MATURITY_OPTIONS = ['Livre', '10', '12', '14', '16', '18'];

    public function maturityOptions(): array
    {
        return self::MATURITY_OPTIONS;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM profiles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM profiles WHERE user_id = :user_id ORDER BY profile_name ASC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM profiles WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function planByUser(int $userId): ?array
    {
        $sql = "SELECT
                    bp.id,
                    bp.plan_name,
                    bp.slug,
                    bp.profile_limit,
                    s.status AS subscription_status
                FROM subscriptions s
                INNER JOIN billing_plans bp ON bp.id = s.plan_id
                WHERE s.user_id = :user_id
                  AND bp.active = 1
                ORDER BY
                    CASE WHEN s.status IN ('ACTIVE','TRIALING') THEN 0 ELSE 1 END,
                    s.updated_at DESC,
                    s.id DESC
                LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function profileLimitByUser(int $userId): int
    {
        $plan = $this->planByUser($userId);
        if (!$plan) {
            return 0;
        }

        return max(0, (int) ($plan['profile_limit'] ?? 0));
    }

    public function ensureDefaultAdultProfile(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if ($this->countByUser($userId) > 0) {
            return;
        }

        $profileLimit = $this->profileLimitByUser($userId);
        if ($profileLimit <= 0) {
            return;
        }

        $stmt = $this->db()->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        $userName = trim((string) ($user['name'] ?? ''));

        $profileName = 'Perfil Principal';
        if ($userName !== '') {
            $parts = preg_split('/\s+/', $userName) ?: [];
            $profileName = (string) ($parts[0] ?? $profileName);
        }

        $this->create([
            'user_id' => $userId,
            'profile_name' => $profileName,
            'avatar_path' => '/assets/img/avatar-library/avatar-01.svg',
            'is_kids' => 0,
            'maturity_rating' => '18',
            'language' => 'pt-BR',
            'autoplay' => 1,
        ]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $userId = (int) ($filters['user_id'] ?? 0);
        $kids = trim((string) ($filters['is_kids'] ?? ''));

        if ($search !== '') {
            $where[] = '(p.profile_name LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($userId > 0) {
            $where[] = 'p.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($kids === '1' || $kids === '0') {
            $where[] = 'p.is_kids = :is_kids';
            $params[':is_kids'] = (int) $kids;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM profiles p INNER JOIN users u ON u.id = p.user_id WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    p.id,
                    p.user_id,
                    p.profile_name,
                    p.avatar_path,
                    p.is_kids,
                    p.maturity_rating,
                    p.language,
                    p.autoplay,
                    p.created_at,
                    u.name AS user_name,
                    u.email AS user_email
                FROM profiles p
                INNER JOIN users u ON u.id = p.user_id
                WHERE {$whereSql}
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO profiles (user_id, profile_name, avatar_path, is_kids, maturity_rating, language, autoplay, created_at, updated_at)
                VALUES (:user_id, :profile_name, :avatar_path, :is_kids, :maturity_rating, :language, :autoplay, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':profile_name' => $data['profile_name'],
            ':avatar_path' => $data['avatar_path'] ?: null,
            ':is_kids' => $data['is_kids'],
            ':maturity_rating' => $data['maturity_rating'],
            ':language' => $data['language'],
            ':autoplay' => $data['autoplay'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE profiles
                   SET user_id = :user_id,
                       profile_name = :profile_name,
                       avatar_path = :avatar_path,
                       is_kids = :is_kids,
                       maturity_rating = :maturity_rating,
                       language = :language,
                       autoplay = :autoplay,
                       updated_at = NOW()
                 WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $data['user_id'],
            ':profile_name' => $data['profile_name'],
            ':avatar_path' => $data['avatar_path'] ?: null,
            ':is_kids' => $data['is_kids'],
            ':maturity_rating' => $data['maturity_rating'],
            ':language' => $data['language'],
            ':autoplay' => $data['autoplay'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
