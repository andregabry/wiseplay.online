<?php

declare(strict_types=1);

namespace App\models;

final class PaymentEvent extends BaseModel
{
    public function create(?int $transactionId, string $eventType, string $provider, ?string $payloadJson = null): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO payment_events
                (transaction_id, event_type, provider, payload_json, created_at)
             VALUES
                (:transaction_id, :event_type, :provider, :payload_json, NOW())'
        );
        $stmt->execute([
            ':transaction_id' => $transactionId,
            ':event_type' => $eventType,
            ':provider' => $provider,
            ':payload_json' => $payloadJson,
        ]);

        return (int) $this->db()->lastInsertId();
    }
}
