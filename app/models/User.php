<?php

declare(strict_types=1);

namespace App\models;

final class User extends BaseModel
{
    public const ROLES = ['SUPER_ADMIN', 'ADMIN', 'EDITOR', 'SUPPORT', 'BILLING_MANAGER', 'VIEWER'];
    public const ADMIN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'EDITOR', 'SUPPORT', 'BILLING_MANAGER'];
    public const STATUSES = ['ACTIVE', 'DISABLED'];

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function roles(): array
    {
        return self::ROLES;
    }

    public function adminRoles(): array
    {
        return self::ADMIN_ROLES;
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($search !== '') {
            $where[] = '(u.name LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($role !== '') {
            $where[] = 'u.role = :role';
            $params[':role'] = $role;
        }

        if ($status !== '') {
            $where[] = 'u.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

                $sql = "SELECT
                    u.id,
                    u.name,
                    u.email,
                    u.account_avatar,
                    u.role,
                    u.status,
                    u.email_verified_at,
                    u.password_set_at,
                    u.created_at,
                    u.last_login_at,
                    (SELECT COUNT(*) FROM profiles p WHERE p.user_id = u.id) AS profiles_count,
                    (SELECT s.status FROM subscriptions s WHERE s.user_id = u.id ORDER BY s.updated_at DESC, s.id DESC LIMIT 1) AS subscription_status,
                    (SELECT bp.id
                        FROM subscriptions s3
                        INNER JOIN billing_plans bp ON bp.id = s3.plan_id
                       WHERE s3.user_id = u.id
                       ORDER BY s3.updated_at DESC, s3.id DESC
                       LIMIT 1) AS subscription_plan_id,
                    (SELECT bp.plan_name
                        FROM subscriptions s2
                        INNER JOIN billing_plans bp ON bp.id = s2.plan_id
                       WHERE s2.user_id = u.id
                       ORDER BY s2.updated_at DESC, s2.id DESC
                       LIMIT 1) AS subscription_plan
                FROM users u
                WHERE {$whereSql}
                ORDER BY u.created_at DESC, u.id DESC
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
        $emailVerifiedAt = array_key_exists('email_verified_at', $data)
            ? $data['email_verified_at']
            : date('Y-m-d H:i:s');
        $passwordSetAt = array_key_exists('password_set_at', $data)
            ? $data['password_set_at']
            : date('Y-m-d H:i:s');
        $onboardingCompletedAt = array_key_exists('onboarding_completed_at', $data)
            ? $data['onboarding_completed_at']
            : null;

        $sql = 'INSERT INTO users
                    (name, email, password_hash, account_avatar, role, status, email_verified_at, password_set_at, onboarding_completed_at, created_at, updated_at, last_login_at)
                VALUES
                    (:name, :email, :password_hash, :account_avatar, :role, :status, :email_verified_at, :password_set_at, :onboarding_completed_at, NOW(), NOW(), NULL)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':account_avatar' => $data['account_avatar'] ?: null,
            ':role' => $data['role'],
            ':status' => $data['status'],
            ':email_verified_at' => $emailVerifiedAt,
            ':password_set_at' => $passwordSetAt,
            ':onboarding_completed_at' => $onboardingCompletedAt,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'name = :name',
            'email = :email',
            'account_avatar = :account_avatar',
            'role = :role',
            'status = :status',
            'updated_at = NOW()',
        ];

        $params = [
            ':id' => $id,
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':account_avatar' => $data['account_avatar'] ?: null,
            ':role' => $data['role'],
            ':status' => $data['status'],
        ];

        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $data['password_hash'];
            $fields[] = 'password_set_at = :password_set_at';
            $params[':password_set_at'] = $data['password_set_at'] ?? date('Y-m-d H:i:s');
        }

        if (array_key_exists('email_verified_at', $data)) {
            $fields[] = 'email_verified_at = :email_verified_at';
            $params[':email_verified_at'] = $data['email_verified_at'];
        }

        if (array_key_exists('onboarding_completed_at', $data)) {
            $fields[] = 'onboarding_completed_at = :onboarding_completed_at';
            $params[':onboarding_completed_at'] = $data['onboarding_completed_at'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->db()->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function listForSelect(bool $onlyActive = true): array
    {
        $sql = 'SELECT id, name, email, role, status FROM users';
        if ($onlyActive) {
            $sql .= " WHERE status = 'ACTIVE'";
        }
        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll();
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db()->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
