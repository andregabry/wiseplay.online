<?php

declare(strict_types=1);

namespace App\models;

final class EmailVerificationCode extends BaseModel
{
    public function latestByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM email_verification_codes
             WHERE email = :email
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function latestActiveByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM email_verification_codes
             WHERE email = :email
               AND consumed_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function invalidateActiveByEmail(string $email): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE email_verification_codes
                SET consumed_at = NOW(),
                    updated_at = NOW()
              WHERE email = :email
                AND consumed_at IS NULL'
        );
        $stmt->execute([':email' => $email]);
    }

    public function createCode(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO email_verification_codes
                (user_id, email, code_hash, expires_at, attempts_count, max_attempts, resend_count, consumed_at, created_at, updated_at)
             VALUES
                (:user_id, :email, :code_hash, :expires_at, 0, :max_attempts, :resend_count, NULL, NOW(), NOW())'
        );
        $stmt->execute([
            ':user_id' => isset($data['user_id']) && (int) $data['user_id'] > 0 ? (int) $data['user_id'] : null,
            ':email' => (string) $data['email'],
            ':code_hash' => (string) $data['code_hash'],
            ':expires_at' => (string) $data['expires_at'],
            ':max_attempts' => max(1, (int) $data['max_attempts']),
            ':resend_count' => max(0, (int) $data['resend_count']),
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE email_verification_codes
                SET attempts_count = attempts_count + 1,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function consume(int $id): void
    {
        $stmt = $this->db()->prepare(
            'UPDATE email_verification_codes
                SET consumed_at = NOW(),
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}

