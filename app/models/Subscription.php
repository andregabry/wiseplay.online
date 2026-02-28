<?php

declare(strict_types=1);

namespace App\models;

final class Subscription extends BaseModel
{
    public const STATUSES = ['PENDING', 'TRIALING', 'ACTIVE', 'PAUSED', 'CANCELED', 'EXPIRED', 'PAYMENT_FAILED'];

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $planId = (int) ($filters['plan_id'] ?? 0);

        if ($q !== '') {
            $where[] = '(u.name LIKE :q OR u.email LIKE :q OR bp.plan_name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if ($status !== '') {
            $where[] = 's.status = :status';
            $params[':status'] = $status;
        }

        if ($planId > 0) {
            $where[] = 's.plan_id = :plan_id';
            $params[':plan_id'] = $planId;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*)
                                              FROM subscriptions s
                                              INNER JOIN users u ON u.id = s.user_id
                                              INNER JOIN billing_plans bp ON bp.id = s.plan_id
                                             WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    s.*,
                    u.name AS user_name,
                    u.email AS user_email,
                    bp.plan_name,
                    bp.slug AS plan_slug,
                    bp.billing_cycle,
                    bp.price,
                    bp.currency,
                    bp.requires_payment_method
                FROM subscriptions s
                INNER JOIN users u ON u.id = s.user_id
                INNER JOIN billing_plans bp ON bp.id = s.plan_id
                WHERE {$whereSql}
                ORDER BY s.updated_at DESC, s.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function assignOrUpdate(
        int $userId,
        int $planId,
        string $status,
        ?string $endAt,
        bool $autoRenew,
        bool $paymentRequired = false,
        string $paymentStatus = 'NOT_REQUIRED',
        ?string $paymentProvider = null,
        ?string $paymentReference = null
    ): int {
        $stmt = $this->db()->prepare('SELECT id FROM subscriptions WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $id = (int) $existing['id'];
            $sql = 'UPDATE subscriptions
                       SET plan_id = :plan_id,
                           status = :status,
                           end_at = :end_at,
                           auto_renew = :auto_renew,
                           payment_required = :payment_required,
                           payment_status = :payment_status,
                           payment_provider = :payment_provider,
                           payment_reference = :payment_reference,
                           updated_at = NOW()
                     WHERE id = :id';

            $update = $this->db()->prepare($sql);
            $update->execute([
                ':id' => $id,
                ':plan_id' => $planId,
                ':status' => $status,
                ':end_at' => $endAt,
                ':auto_renew' => $autoRenew ? 1 : 0,
                ':payment_required' => $paymentRequired ? 1 : 0,
                ':payment_status' => $paymentStatus,
                ':payment_provider' => $paymentProvider,
                ':payment_reference' => $paymentReference,
            ]);

            return $id;
        }

        $insert = $this->db()->prepare('INSERT INTO subscriptions
            (user_id, plan_id, status, start_at, end_at, auto_renew, payment_required, payment_status, payment_provider, payment_reference, created_at, updated_at)
            VALUES
            (:user_id, :plan_id, :status, NOW(), :end_at, :auto_renew, :payment_required, :payment_status, :payment_provider, :payment_reference, NOW(), NOW())');

        $insert->execute([
            ':user_id' => $userId,
            ':plan_id' => $planId,
            ':status' => $status,
            ':end_at' => $endAt,
            ':auto_renew' => $autoRenew ? 1 : 0,
            ':payment_required' => $paymentRequired ? 1 : 0,
            ':payment_status' => $paymentStatus,
            ':payment_provider' => $paymentProvider,
            ':payment_reference' => $paymentReference,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findActiveByUser(int $userId): ?array
    {
        $sql = "SELECT s.*, bp.plan_name, bp.slug AS plan_slug, bp.profile_limit, bp.max_streams, bp.quality_level, bp.requires_payment_method
                  FROM subscriptions s
                  INNER JOIN billing_plans bp ON bp.id = s.plan_id
                 WHERE s.user_id = :user_id
                   AND s.status IN ('ACTIVE','TRIALING')
                 ORDER BY s.updated_at DESC, s.id DESC
                 LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function latestByUser(int $userId): ?array
    {
        $sql = "SELECT s.*, bp.plan_name, bp.slug AS plan_slug, bp.profile_limit, bp.max_streams, bp.quality_level, bp.requires_payment_method, bp.active AS plan_active, bp.price, bp.currency, bp.billing_cycle
                  FROM subscriptions s
                  INNER JOIN billing_plans bp ON bp.id = s.plan_id
                 WHERE s.user_id = :user_id
                 ORDER BY s.updated_at DESC, s.id DESC
                 LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markPaymentState(
        int $subscriptionId,
        string $status,
        string $paymentStatus,
        ?string $provider = null,
        ?string $reference = null
    ): void {
        $stmt = $this->db()->prepare(
            'UPDATE subscriptions
                SET status = :status,
                    payment_status = :payment_status,
                    payment_provider = :payment_provider,
                    payment_reference = :payment_reference,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $subscriptionId,
            ':status' => $status,
            ':payment_status' => $paymentStatus,
            ':payment_provider' => $provider,
            ':payment_reference' => $reference,
        ]);
    }
}
