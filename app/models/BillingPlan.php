<?php

declare(strict_types=1);

namespace App\models;

final class BillingPlan extends BaseModel
{
    public const CYCLES = ['MONTHLY', 'YEARLY', 'ONE_TIME'];

    public function cycles(): array
    {
        return self::CYCLES;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM billing_plans WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM billing_plans WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM billing_plans WHERE slug = :slug';
        $params = [':slug' => $slug];
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

        $q = trim((string) ($filters['q'] ?? ''));
        $active = trim((string) ($filters['active'] ?? ''));
        $cycle = trim((string) ($filters['billing_cycle'] ?? ''));

        if ($q !== '') {
            $where[] = '(plan_name LIKE :q OR slug LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if ($active === '1' || $active === '0') {
            $where[] = 'active = :active';
            $params[':active'] = (int) $active;
        }

        if ($cycle !== '') {
            $where[] = 'billing_cycle = :billing_cycle';
            $params[':billing_cycle'] = $cycle;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM billing_plans WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT
                    p.*,
                    (SELECT COUNT(*) FROM subscriptions s WHERE s.plan_id = p.id) AS subscribers_count
                FROM billing_plans p
                WHERE {$whereSql}
                ORDER BY p.active DESC, p.sort_order ASC, p.updated_at DESC, p.id DESC
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

    public function listActive(): array
    {
        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 ORDER BY sort_order ASC, price ASC, plan_name ASC");
        return $stmt->fetchAll();
    }

    public function listActiveSubscriptionPlans(): array
    {
        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 AND billing_cycle IN ('MONTHLY', 'YEARLY') ORDER BY sort_order ASC, price ASC, plan_name ASC");
        return $stmt->fetchAll();
    }

    public function listActiveTitlePlans(): array
    {
        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 ORDER BY sort_order ASC, price ASC, plan_name ASC");
        return $stmt->fetchAll();
    }

    public function listPublicPlans(): array
    {
        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 AND billing_cycle IN ('MONTHLY', 'YEARLY') ORDER BY sort_order ASC, price ASC, plan_name ASC");
        return $stmt->fetchAll();
    }

    public function findDefaultFreePlan(): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM billing_plans WHERE slug = 'free' AND active = 1 LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }

        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 ORDER BY CASE WHEN billing_cycle = 'ONE_TIME' THEN 1 ELSE 0 END ASC, price ASC, id ASC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findDefaultForRegistration(): ?array
    {
        $free = $this->findDefaultFreePlan();
        if ($free) {
            return $free;
        }

        $stmt = $this->db()->query("SELECT * FROM billing_plans WHERE active = 1 AND billing_cycle IN ('MONTHLY', 'YEARLY') ORDER BY price ASC, id ASC LIMIT 1");
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO billing_plans
                    (plan_name, slug, price, currency, billing_cycle, profile_limit, max_streams, quality_level, active, is_selectable, requires_payment_method, sort_order, created_at, updated_at)
                VALUES
                    (:plan_name, :slug, :price, :currency, :billing_cycle, :profile_limit, :max_streams, :quality_level, :active, :is_selectable, :requires_payment_method, :sort_order, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':plan_name' => $data['plan_name'],
            ':slug' => $data['slug'],
            ':price' => $data['price'],
            ':currency' => $data['currency'],
            ':billing_cycle' => $data['billing_cycle'],
            ':profile_limit' => $data['profile_limit'],
            ':max_streams' => $data['max_streams'],
            ':quality_level' => $data['quality_level'],
            ':active' => $data['active'],
            ':is_selectable' => $data['is_selectable'],
            ':requires_payment_method' => $data['requires_payment_method'],
            ':sort_order' => $data['sort_order'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE billing_plans
                   SET plan_name = :plan_name,
                       slug = :slug,
                       price = :price,
                       currency = :currency,
                       billing_cycle = :billing_cycle,
                       profile_limit = :profile_limit,
                       max_streams = :max_streams,
                       quality_level = :quality_level,
                       active = :active,
                       is_selectable = :is_selectable,
                       requires_payment_method = :requires_payment_method,
                       sort_order = :sort_order,
                       updated_at = NOW()
                 WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':plan_name' => $data['plan_name'],
            ':slug' => $data['slug'],
            ':price' => $data['price'],
            ':currency' => $data['currency'],
            ':billing_cycle' => $data['billing_cycle'],
            ':profile_limit' => $data['profile_limit'],
            ':max_streams' => $data['max_streams'],
            ':quality_level' => $data['quality_level'],
            ':active' => $data['active'],
            ':is_selectable' => $data['is_selectable'],
            ':requires_payment_method' => $data['requires_payment_method'],
            ':sort_order' => $data['sort_order'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM billing_plans WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
