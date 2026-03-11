<?php

declare(strict_types=1);

namespace App\models;

final class PaymentMethod extends BaseModel
{
    /**
     * Return all payment methods for the account ordered by default first.
     */
    public function listByUser(int $userId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM payment_methods WHERE user_id = :user_id ORDER BY is_default DESC, created_at DESC, id DESC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM payment_methods WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM payment_methods WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO payment_methods
                (user_id, provider, token_ref, brand, last4, exp_month, exp_year, is_default, created_at, updated_at)
             VALUES
                (:user_id, :provider, :token_ref, :brand, :last4, :exp_month, :exp_year, :is_default, NOW(), NOW())'
        );

        $stmt->execute([
            ':user_id' => (int) ($data['user_id'] ?? 0),
            ':provider' => (string) ($data['provider'] ?? 'CARD'),
            ':token_ref' => (string) ($data['token_ref'] ?? ''),
            ':brand' => ((string) ($data['brand'] ?? '')) !== '' ? (string) $data['brand'] : null,
            ':last4' => ((string) ($data['last4'] ?? '')) !== '' ? (string) $data['last4'] : null,
            ':exp_month' => (int) ($data['exp_month'] ?? 0) > 0 ? (int) $data['exp_month'] : null,
            ':exp_year' => (int) ($data['exp_year'] ?? 0) > 0 ? (int) $data['exp_year'] : null,
            ':is_default' => (int) ($data['is_default'] ?? 0) === 1 ? 1 : 0,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * Keep a single default method per user.
     */
    public function setDefault(int $id, int $userId): void
    {
        $clearStmt = $this->db()->prepare('UPDATE payment_methods SET is_default = 0, updated_at = NOW() WHERE user_id = :user_id');
        $clearStmt->execute([':user_id' => $userId]);

        $defaultStmt = $this->db()->prepare('UPDATE payment_methods SET is_default = 1, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
        $defaultStmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);
    }

    /**
     * Remove the method and promote the oldest remaining one when needed.
     */
    public function delete(int $id, int $userId): void
    {
        $stmt = $this->db()->prepare('DELETE FROM payment_methods WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        $firstStmt = $this->db()->prepare('SELECT id FROM payment_methods WHERE user_id = :user_id ORDER BY created_at ASC, id ASC LIMIT 1');
        $firstStmt->execute([':user_id' => $userId]);
        $first = $firstStmt->fetch();
        if ($first && (int) ($first['id'] ?? 0) > 0) {
            $hasDefaultStmt = $this->db()->prepare('SELECT COUNT(*) FROM payment_methods WHERE user_id = :user_id AND is_default = 1');
            $hasDefaultStmt->execute([':user_id' => $userId]);
            $hasDefault = (int) $hasDefaultStmt->fetchColumn() > 0;
            if (!$hasDefault) {
                $this->setDefault((int) $first['id'], $userId);
            }
        }
    }
}
