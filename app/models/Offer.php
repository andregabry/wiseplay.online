<?php

declare(strict_types=1);

namespace App\models;

use PDOException;

final class Offer extends BaseModel
{
    public const TYPES = ['ONE_TIME', 'SUBSCRIPTION'];
    public const INTERVALS = ['MONTH', 'YEAR'];
    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function types(): array
    {
        return self::TYPES;
    }

    public function intervals(): array
    {
        return self::INTERVALS;
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function titleOptionsForProducer(int $producerId): array
    {
        try {
            $stmt = $this->db()->prepare('SELECT id, title_name, type, status FROM titles WHERE producer_id = :producer_id ORDER BY updated_at DESC, id DESC');
            $stmt->execute([':producer_id' => $producerId]);
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function findByIdForProducer(int $id, int $producerId): ?array
    {
        try {
            $stmt = $this->db()->prepare('SELECT * FROM offers WHERE id = :id AND producer_id = :producer_id LIMIT 1');
            $stmt->execute([
                ':id' => $id,
                ':producer_id' => $producerId,
            ]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function paginateForProducer(int $producerId, array $filters, int $page, int $perPage): array
    {
        try {
            $where = ['o.producer_id = :producer_id'];
            $params = [':producer_id' => $producerId];

            $search = trim((string) ($filters['q'] ?? ''));
            $status = trim((string) ($filters['status'] ?? ''));
            $type = trim((string) ($filters['offer_type'] ?? ''));
            $titleId = (int) ($filters['title_id'] ?? 0);

            if ($search !== '') {
                $where[] = '(t.title_name LIKE :search OR o.currency LIKE :search)';
                $params[':search'] = '%' . $search . '%';
            }

            if ($status !== '') {
                $where[] = 'o.status = :status';
                $params[':status'] = $status;
            }

            if ($type !== '') {
                $where[] = 'o.offer_type = :offer_type';
                $params[':offer_type'] = $type;
            }

            if ($titleId > 0) {
                $where[] = 'o.title_id = :title_id';
                $params[':title_id'] = $titleId;
            }

            $whereSql = implode(' AND ', $where);

            $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM offers o INNER JOIN titles t ON t.id = o.title_id WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = max(0, ($page - 1) * $perPage);
            $sql = "SELECT
                        o.*,
                        t.title_name,
                        t.slug AS title_slug,
                        t.type AS title_type
                    FROM offers o
                    INNER JOIN titles t ON t.id = o.title_id
                    WHERE {$whereSql}
                    ORDER BY o.created_at DESC, o.id DESC
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
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return [
                    'items' => [],
                    'total' => 0,
                ];
            }

            throw $exception;
        }
    }

    public function create(array $data): int
    {
        try {
            $stmt = $this->db()->prepare('INSERT INTO offers
                (producer_id, title_id, offer_type, billing_interval, currency, price, status, is_selectable, created_at, updated_at)
                VALUES
                (:producer_id, :title_id, :offer_type, :billing_interval, :currency, :price, :status, :is_selectable, NOW(), NOW())');

            $stmt->execute([
                ':producer_id' => (int) $data['producer_id'],
                ':title_id' => (int) $data['title_id'],
                ':offer_type' => (string) $data['offer_type'],
                ':billing_interval' => $data['billing_interval'] !== '' ? (string) $data['billing_interval'] : null,
                ':currency' => strtoupper((string) $data['currency']),
                ':price' => (float) $data['price'],
                ':status' => (string) $data['status'],
                ':is_selectable' => (int) $data['is_selectable'],
            ]);

            return (int) $this->db()->lastInsertId();
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return 0;
            }

            throw $exception;
        }
    }

    public function update(int $id, int $producerId, array $data): void
    {
        try {
            $stmt = $this->db()->prepare('UPDATE offers
                SET title_id = :title_id,
                    offer_type = :offer_type,
                    billing_interval = :billing_interval,
                    currency = :currency,
                    price = :price,
                    status = :status,
                    is_selectable = :is_selectable,
                    updated_at = NOW()
                WHERE id = :id AND producer_id = :producer_id');

            $stmt->execute([
                ':id' => $id,
                ':producer_id' => $producerId,
                ':title_id' => (int) $data['title_id'],
                ':offer_type' => (string) $data['offer_type'],
                ':billing_interval' => $data['billing_interval'] !== '' ? (string) $data['billing_interval'] : null,
                ':currency' => strtoupper((string) $data['currency']),
                ':price' => (float) $data['price'],
                ':status' => (string) $data['status'],
                ':is_selectable' => (int) $data['is_selectable'],
            ]);
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return;
            }

            throw $exception;
        }
    }

    public function delete(int $id, int $producerId): void
    {
        try {
            $stmt = $this->db()->prepare('DELETE FROM offers WHERE id = :id AND producer_id = :producer_id');
            $stmt->execute([
                ':id' => $id,
                ':producer_id' => $producerId,
            ]);
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return;
            }

            throw $exception;
        }
    }

    public function publicActiveForTitle(int $titleId): array
    {
        try {
            $stmt = $this->db()->prepare("SELECT * FROM offers WHERE title_id = :title_id AND status = 'ACTIVE' ORDER BY price ASC, id ASC");
            $stmt->execute([':title_id' => $titleId]);
            return $stmt->fetchAll();
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    public function publicActiveGroupedByTitleIds(array $titleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $titleIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db()->prepare("SELECT * FROM offers WHERE title_id IN ({$placeholders}) AND status = 'ACTIVE' ORDER BY title_id ASC, price ASC, id ASC");
            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 1, $id, \PDO::PARAM_INT);
            }
            $stmt->execute();

            $grouped = [];
            foreach ($stmt->fetchAll() as $row) {
                $grouped[(int) $row['title_id']][] = $row;
            }

            return $grouped;
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    private function isMissingMarketplaceSchema(PDOException $exception): bool
    {
        return $exception->getCode() === '42S02';
    }
}
