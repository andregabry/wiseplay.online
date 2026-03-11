<?php

declare(strict_types=1);

namespace App\models;

use PDOException;

final class Producer extends BaseModel
{
    public const STATUSES = ['PENDING', 'ACTIVE', 'DISABLED'];

    public function findPrimaryForUser(int $userId): ?array
    {
        try {
            $ownerStmt = $this->db()->prepare(
                'SELECT id, owner_user_id, brand_name, slug, status, created_at
                   FROM producers
                  WHERE owner_user_id = :user_id AND status = :status
                  ORDER BY id ASC
                  LIMIT 1'
            );
            $ownerStmt->execute([
                ':user_id' => $userId,
                ':status' => 'ACTIVE',
            ]);

            $ownerProducer = $ownerStmt->fetch();
            if ($ownerProducer) {
                return $ownerProducer;
            }

            $memberStmt = $this->db()->prepare(
                'SELECT p.id, p.owner_user_id, p.brand_name, p.slug, p.status, p.created_at
                   FROM producer_members pm
                   INNER JOIN producers p ON p.id = pm.producer_id
                  WHERE pm.user_id = :user_id
                    AND pm.status = :member_status
                    AND p.status = :producer_status
                  ORDER BY pm.id ASC
                  LIMIT 1'
            );
            $memberStmt->execute([
                ':user_id' => $userId,
                ':member_status' => 'ACTIVE',
                ':producer_status' => 'ACTIVE',
            ]);

            $memberProducer = $memberStmt->fetch();
            return $memberProducer ?: null;
        } catch (PDOException $exception) {
            if ($this->isMissingTenantSchema($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public function findById(int $producerId): ?array
    {
        try {
            $stmt = $this->db()->prepare(
                'SELECT id, owner_user_id, brand_name, slug, status, created_at
                   FROM producers
                  WHERE id = :id
                  LIMIT 1'
            );
            $stmt->execute([':id' => $producerId]);

            $producer = $stmt->fetch();
            return $producer ?: null;
        } catch (PDOException $exception) {
            if ($this->isMissingTenantSchema($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isMissingTenantSchema(PDOException $exception): bool
    {
        return $exception->getCode() === '42S02';
    }
}
