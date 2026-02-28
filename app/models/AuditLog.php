<?php

declare(strict_types=1);

namespace App\models;

final class AuditLog extends BaseModel
{
    public function log(?int $actorUserId, string $action, string $entityType, ?string $entityId, ?array $metadata = null): void
    {
        $sql = 'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, diff_summary, ip, user_agent, created_at)
                VALUES (:actor_user_id, :action, :entity_type, :entity_id, :diff_summary, :ip, :user_agent, NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':actor_user_id' => $actorUserId,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':diff_summary' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }
}
