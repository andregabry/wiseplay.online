<?php

declare(strict_types=1);

namespace App\models;

use PDOException;

final class Entitlement extends BaseModel
{
    public function hasActiveForUserTitle(int $userId, int $titleId): bool
    {
        try {
            $stmt = $this->db()->prepare("SELECT id
                FROM entitlements
                WHERE user_id = :user_id
                  AND title_id = :title_id
                  AND status = 'ACTIVE'
                  AND (end_at IS NULL OR end_at >= NOW())
                LIMIT 1");
            $stmt->execute([
                ':user_id' => $userId,
                ':title_id' => $titleId,
            ]);

            return (bool) $stmt->fetch();
        } catch (PDOException $exception) {
            if ($this->isMissingMarketplaceSchema($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    public function activeTitleIdsForUser(int $userId, array $titleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $titleIds), static fn (int $id): bool => $id > 0)));
        if ($userId <= 0 || $ids === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT title_id
                      FROM entitlements
                     WHERE user_id = ?
                       AND title_id IN ({$placeholders})
                       AND status = 'ACTIVE'
                       AND (end_at IS NULL OR end_at >= NOW())";

            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 2, $id, \PDO::PARAM_INT);
            }
            $stmt->execute();

            return array_map('intval', array_column($stmt->fetchAll(), 'title_id'));
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
