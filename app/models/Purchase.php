<?php

declare(strict_types=1);

namespace App\models;

final class Purchase extends BaseModel
{
    public function createPending(array $data): int
    {
        $stmt = $this->db()->prepare('INSERT INTO purchases
            (user_id, producer_id, title_id, offer_id, status, provider, provider_reference, created_at)
            VALUES
            (:user_id, :producer_id, :title_id, :offer_id, :status, :provider, :provider_reference, NOW())');

        $stmt->execute([
            ':user_id' => (int) $data['user_id'],
            ':producer_id' => (int) $data['producer_id'],
            ':title_id' => (int) $data['title_id'],
            ':offer_id' => (int) $data['offer_id'],
            ':status' => (string) ($data['status'] ?? 'PENDING'),
            ':provider' => (string) ($data['provider'] ?? 'infinitepay'),
            ':provider_reference' => $data['provider_reference'] !== '' ? (string) $data['provider_reference'] : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }
}
