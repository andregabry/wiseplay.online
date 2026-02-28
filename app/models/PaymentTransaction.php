<?php

declare(strict_types=1);

namespace App\models;

final class PaymentTransaction extends BaseModel
{
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO payment_transactions
                (user_id, subscription_id, plan_id, provider, provider_txn_id, status, amount, currency, metadata_json, created_at, updated_at)
             VALUES
                (:user_id, :subscription_id, :plan_id, :provider, :provider_txn_id, :status, :amount, :currency, :metadata_json, NOW(), NOW())'
        );
        $stmt->execute([
            ':user_id' => (int) $data['user_id'],
            ':subscription_id' => isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            ':plan_id' => isset($data['plan_id']) ? (int) $data['plan_id'] : null,
            ':provider' => (string) ($data['provider'] ?? 'mock'),
            ':provider_txn_id' => $data['provider_txn_id'] ?? null,
            ':status' => (string) ($data['status'] ?? 'PENDING'),
            ':amount' => (float) ($data['amount'] ?? 0),
            ':currency' => (string) ($data['currency'] ?? 'BRL'),
            ':metadata_json' => $data['metadata_json'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $providerTxnId = null, ?string $metadataJson = null): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE payment_transactions
                SET status = :status,
                    provider_txn_id = COALESCE(:provider_txn_id, provider_txn_id),
                    metadata_json = COALESCE(:metadata_json, metadata_json),
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':provider_txn_id' => $providerTxnId,
            ':metadata_json' => $metadataJson,
        ]);
    }
}
