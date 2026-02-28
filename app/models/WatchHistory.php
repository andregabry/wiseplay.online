<?php

declare(strict_types=1);

namespace App\models;

final class WatchHistory extends BaseModel
{
    public function upsertProgress(int $profileId, int $titleId, int $mediaItemId, int $progressPercent, int $lastPositionSeconds): void
    {
        $progressPercent = max(0, min(100, $progressPercent));
        $lastPositionSeconds = max(0, $lastPositionSeconds);

        $sql = 'INSERT INTO watch_history
                    (profile_id, title_id, media_item_id, progress_percent, last_position_seconds, last_watched_at, created_at, updated_at)
                VALUES
                    (:profile_id, :title_id, :media_item_id, :progress_percent, :last_position_seconds, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    title_id = VALUES(title_id),
                    progress_percent = VALUES(progress_percent),
                    last_position_seconds = VALUES(last_position_seconds),
                    last_watched_at = NOW(),
                    updated_at = NOW()';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':profile_id' => $profileId,
            ':title_id' => $titleId,
            ':media_item_id' => $mediaItemId,
            ':progress_percent' => $progressPercent,
            ':last_position_seconds' => $lastPositionSeconds,
        ]);
    }

    public function recentByProfile(int $profileId, int $limit = 12): array
    {
        $sql = "SELECT
                    w.*,
                    t.title_name,
                    t.slug,
                    t.cover_image,
                    t.backdrop_image,
                    t.type,
                    t.maturity_rating,
                    m.item_label,
                    m.season_number,
                    m.episode_number
                FROM watch_history w
                INNER JOIN titles t ON t.id = w.title_id
                INNER JOIN media_items m ON m.id = w.media_item_id
                WHERE w.profile_id = :profile_id
                ORDER BY w.last_watched_at DESC
                LIMIT :limit";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':profile_id', $profileId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
