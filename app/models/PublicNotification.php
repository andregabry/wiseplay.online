<?php

declare(strict_types=1);

namespace App\models;

final class PublicNotification extends BaseModel
{
    /**
     * Store a broadcast notification that can later be marked as read per user.
     */
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO public_notifications
                (entity_type, entity_id, title_text, message_text, link_url, created_at)
             VALUES
                (:entity_type, :entity_id, :title_text, :message_text, :link_url, NOW())'
        );

        $stmt->execute([
            ':entity_type' => strtoupper((string) ($data['entity_type'] ?? 'TITLE')),
            ':entity_id' => (int) ($data['entity_id'] ?? 0) > 0 ? (int) $data['entity_id'] : null,
            ':title_text' => (string) ($data['title_text'] ?? 'Atualizacao de catalogo'),
            ':message_text' => (string) ($data['message_text'] ?? ''),
            ':link_url' => ((string) ($data['link_url'] ?? '')) !== '' ? (string) $data['link_url'] : '/browse',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /**
     * Return the latest notifications plus the current user's read flag.
     */
    public function listForUser(int $userId, int $limit = 15): array
    {
        $stmt = $this->db()->prepare(
            'SELECT
                n.id,
                n.entity_type,
                n.entity_id,
                n.title_text,
                n.message_text,
                n.link_url,
                n.created_at,
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
             FROM public_notifications n
             LEFT JOIN public_notification_reads r
               ON r.notification_id = n.id
              AND r.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function unreadCountForUser(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
               FROM public_notifications n
          LEFT JOIN public_notification_reads r
                 ON r.notification_id = n.id
                AND r.user_id = :user_id
              WHERE r.id IS NULL'
        );
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mark every unseen notification as read for the current user.
     */
    public function markAllAsRead(int $userId): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO public_notification_reads (notification_id, user_id, read_at)
             SELECT n.id, :user_id, NOW()
               FROM public_notifications n
               LEFT JOIN public_notification_reads r
                 ON r.notification_id = n.id
                AND r.user_id = :user_id
              WHERE r.id IS NULL'
        );
        $stmt->execute([':user_id' => $userId]);

        return (int) $stmt->rowCount();
    }
}
