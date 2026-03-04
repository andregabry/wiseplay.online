<?php

declare(strict_types=1);

namespace App\models;

final class Media extends BaseModel
{
    public const SOURCE_TYPES = ['ONEDRIVE', 'LOCAL', 'URL'];
    public const UPLOAD_STATUSES = ['PENDING', 'UPLOADING', 'READY', 'FAILED'];

    public function sourceTypes(): array
    {
        return self::SOURCE_TYPES;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM media_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPlayableById(int $id): ?array
    {
        $sql = "SELECT m.*, t.title_name, t.type, t.slug, t.maturity_rating, t.cover_image
                  FROM media_items m
                  INNER JOIN titles t ON t.id = m.title_id
                 WHERE m.id = :id
                   AND m.is_published = 1
                   AND (m.upload_status IS NULL OR m.upload_status IN ('READY'))
                   AND t.status = 'PUBLISHED'
                 LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $titleId = (int) ($filters['title_id'] ?? 0);
        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        $published = trim((string) ($filters['is_published'] ?? ''));

        if ($search !== '') {
            $where[] = '(m.item_label LIKE :search OR t.title_name LIKE :search OR m.source_path LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($titleId > 0) {
            $where[] = 'm.title_id = :title_id';
            $params[':title_id'] = $titleId;
        }

        if ($sourceType !== '') {
            $where[] = 'm.source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        if ($published === '1' || $published === '0') {
            $where[] = 'm.is_published = :is_published';
            $params[':is_published'] = (int) $published;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM media_items m INNER JOIN titles t ON t.id = m.title_id WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    m.*,
                    t.title_name,
                    t.type AS title_type,
                    t.status AS title_status
                FROM media_items m
                INNER JOIN titles t ON t.id = m.title_id
                WHERE {$whereSql}
                ORDER BY m.updated_at DESC, m.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO media_items
                    (title_id, season_number, episode_number, item_label, duration_minutes, source_type, source_path, external_id, stream_ref, stream_url, stream_url_expires_at, download_url, drive_id, parent_item_id, mime_type, file_size_bytes, upload_status, subtitles, subtitles_json, audio_lang, is_published, thumbnail_url, poster_url, backdrop_url, last_source_sync_at, created_at, updated_at)
                VALUES
                    (:title_id, :season_number, :episode_number, :item_label, :duration_minutes, :source_type, :source_path, :external_id, :stream_ref, :stream_url, :stream_url_expires_at, :download_url, :drive_id, :parent_item_id, :mime_type, :file_size_bytes, :upload_status, :subtitles, :subtitles_json, :audio_lang, :is_published, :thumbnail_url, :poster_url, :backdrop_url, :last_source_sync_at, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':title_id' => $data['title_id'],
            ':season_number' => $data['season_number'] > 0 ? $data['season_number'] : null,
            ':episode_number' => $data['episode_number'] > 0 ? $data['episode_number'] : null,
            ':item_label' => $data['item_label'],
            ':duration_minutes' => $data['duration_minutes'] > 0 ? $data['duration_minutes'] : null,
            ':source_type' => $data['source_type'],
            ':source_path' => $data['source_path'] ?: null,
            ':external_id' => $data['external_id'] ?: null,
            ':stream_ref' => $data['stream_ref'] ?: null,
            ':stream_url' => $data['stream_url'] ?: null,
            ':stream_url_expires_at' => $data['stream_url_expires_at'] ?: null,
            ':download_url' => $data['download_url'] ?: null,
            ':drive_id' => $data['drive_id'] ?: null,
            ':parent_item_id' => $data['parent_item_id'] ?: null,
            ':mime_type' => $data['mime_type'] ?: null,
            ':file_size_bytes' => (int) ($data['file_size_bytes'] ?? 0) > 0 ? (int) $data['file_size_bytes'] : null,
            ':upload_status' => $data['upload_status'] ?: 'READY',
            ':subtitles' => $data['subtitles'] ?: null,
            ':subtitles_json' => $data['subtitles_json'] ?: null,
            ':audio_lang' => $data['audio_lang'] ?: null,
            ':is_published' => $data['is_published'],
            ':thumbnail_url' => $data['thumbnail_url'] ?: null,
            ':poster_url' => $data['poster_url'] ?: null,
            ':backdrop_url' => $data['backdrop_url'] ?: null,
            ':last_source_sync_at' => $data['last_source_sync_at'] ?: null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE media_items
                   SET title_id = :title_id,
                       season_number = :season_number,
                       episode_number = :episode_number,
                       item_label = :item_label,
                       duration_minutes = :duration_minutes,
                       source_type = :source_type,
                       source_path = :source_path,
                       external_id = :external_id,
                       stream_ref = :stream_ref,
                       stream_url = :stream_url,
                       stream_url_expires_at = :stream_url_expires_at,
                       download_url = :download_url,
                       drive_id = :drive_id,
                       parent_item_id = :parent_item_id,
                       mime_type = :mime_type,
                       file_size_bytes = :file_size_bytes,
                       upload_status = :upload_status,
                       subtitles = :subtitles,
                       subtitles_json = :subtitles_json,
                       audio_lang = :audio_lang,
                       is_published = :is_published,
                       thumbnail_url = :thumbnail_url,
                       poster_url = :poster_url,
                       backdrop_url = :backdrop_url,
                       last_source_sync_at = :last_source_sync_at,
                       updated_at = NOW()
                 WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':title_id' => $data['title_id'],
            ':season_number' => $data['season_number'] > 0 ? $data['season_number'] : null,
            ':episode_number' => $data['episode_number'] > 0 ? $data['episode_number'] : null,
            ':item_label' => $data['item_label'],
            ':duration_minutes' => $data['duration_minutes'] > 0 ? $data['duration_minutes'] : null,
            ':source_type' => $data['source_type'],
            ':source_path' => $data['source_path'] ?: null,
            ':external_id' => $data['external_id'] ?: null,
            ':stream_ref' => $data['stream_ref'] ?: null,
            ':stream_url' => $data['stream_url'] ?: null,
            ':stream_url_expires_at' => $data['stream_url_expires_at'] ?: null,
            ':download_url' => $data['download_url'] ?: null,
            ':drive_id' => $data['drive_id'] ?: null,
            ':parent_item_id' => $data['parent_item_id'] ?: null,
            ':mime_type' => $data['mime_type'] ?: null,
            ':file_size_bytes' => (int) ($data['file_size_bytes'] ?? 0) > 0 ? (int) $data['file_size_bytes'] : null,
            ':upload_status' => $data['upload_status'] ?: 'READY',
            ':subtitles' => $data['subtitles'] ?: null,
            ':subtitles_json' => $data['subtitles_json'] ?: null,
            ':audio_lang' => $data['audio_lang'] ?: null,
            ':is_published' => $data['is_published'],
            ':thumbnail_url' => $data['thumbnail_url'] ?: null,
            ':poster_url' => $data['poster_url'] ?: null,
            ':backdrop_url' => $data['backdrop_url'] ?: null,
            ':last_source_sync_at' => $data['last_source_sync_at'] ?: null,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM media_items WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function existsDuplicate(array $data, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id
                  FROM media_items
                 WHERE title_id = :title_id
                   AND COALESCE(season_number, 0) = :season_number
                   AND COALESCE(episode_number, 0) = :episode_number
                   AND source_type = :source_type
                 LIMIT 1';

        $params = [
            ':title_id' => (int) ($data['title_id'] ?? 0),
            ':season_number' => max(0, (int) ($data['season_number'] ?? 0)),
            ':episode_number' => max(0, (int) ($data['episode_number'] ?? 0)),
            ':source_type' => (string) ($data['source_type'] ?? 'ONEDRIVE'),
        ];

        if ($ignoreId !== null && $ignoreId > 0) {
            $sql = 'SELECT id
                      FROM media_items
                     WHERE title_id = :title_id
                       AND COALESCE(season_number, 0) = :season_number
                       AND COALESCE(episode_number, 0) = :episode_number
                       AND source_type = :source_type
                       AND id <> :ignore_id
                     LIMIT 1';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function updateSourceSync(int $id, array $data): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->db()->prepare('UPDATE media_items
                                         SET source_path = :source_path,
                                             stream_url = :stream_url,
                                             download_url = :download_url,
                                             mime_type = :mime_type,
                                             file_size_bytes = :file_size_bytes,
                                             drive_id = :drive_id,
                                             parent_item_id = :parent_item_id,
                                             upload_status = :upload_status,
                                             stream_url_expires_at = :stream_url_expires_at,
                                             last_source_sync_at = :last_source_sync_at,
                                             updated_at = NOW()
                                       WHERE id = :id');

        $stmt->execute([
            ':id' => $id,
            ':source_path' => ((string) ($data['source_path'] ?? '')) !== '' ? (string) $data['source_path'] : null,
            ':stream_url' => ((string) ($data['stream_url'] ?? '')) !== '' ? (string) $data['stream_url'] : null,
            ':download_url' => ((string) ($data['download_url'] ?? '')) !== '' ? (string) $data['download_url'] : null,
            ':mime_type' => ((string) ($data['mime_type'] ?? '')) !== '' ? (string) $data['mime_type'] : null,
            ':file_size_bytes' => (int) ($data['file_size_bytes'] ?? 0) > 0 ? (int) $data['file_size_bytes'] : null,
            ':drive_id' => ((string) ($data['drive_id'] ?? '')) !== '' ? (string) $data['drive_id'] : null,
            ':parent_item_id' => ((string) ($data['parent_item_id'] ?? '')) !== '' ? (string) $data['parent_item_id'] : null,
            ':upload_status' => ((string) ($data['upload_status'] ?? '')) !== '' ? (string) $data['upload_status'] : 'READY',
            ':stream_url_expires_at' => ((string) ($data['stream_url_expires_at'] ?? '')) !== '' ? (string) $data['stream_url_expires_at'] : null,
            ':last_source_sync_at' => ((string) ($data['last_source_sync_at'] ?? '')) !== '' ? (string) $data['last_source_sync_at'] : null,
        ]);
    }

    public function listForTitle(int $titleId, bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM media_items WHERE title_id = :title_id';
        if ($publishedOnly) {
            $sql .= " AND is_published = 1 AND (upload_status IS NULL OR upload_status IN ('READY'))";
        }
        $sql .= ' ORDER BY COALESCE(season_number, 1), COALESCE(episode_number, 1), id';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':title_id' => $titleId]);
        return $stmt->fetchAll();
    }

    public function hierarchyForTitle(int $titleId, bool $publishedOnly = true): array
    {
        $items = $this->listForTitle($titleId, $publishedOnly);
        $grouped = [];

        foreach ($items as $item) {
            $season = (int) ($item['season_number'] ?? 0);
            $episode = (int) ($item['episode_number'] ?? 0);

            if ($season <= 0) {
                $season = 1;
            }

            $grouped[$season] ??= [];
            $grouped[$season][] = array_merge($item, [
                'episode_number' => $episode > 0 ? $episode : 1,
            ]);
        }

        ksort($grouped);
        foreach ($grouped as &$episodes) {
            usort($episodes, static function (array $a, array $b): int {
                return ((int) $a['episode_number']) <=> ((int) $b['episode_number']);
            });
        }

        return $grouped;
    }

    public function firstPlayableByTitle(int $titleId): ?array
    {
        $sql = "SELECT
                    m.id,
                    m.title_id,
                    m.item_label,
                    m.season_number,
                    m.episode_number,
                    m.duration_minutes,
                    m.source_type,
                    m.source_path,
                    m.mime_type,
                    m.thumbnail_url,
                    m.backdrop_url
                  FROM media_items m
                  INNER JOIN titles t ON t.id = m.title_id
                 WHERE m.title_id = :title_id
                   AND m.is_published = 1
                   AND (m.upload_status IS NULL OR m.upload_status IN ('READY'))
                   AND t.status = 'PUBLISHED'
                 ORDER BY COALESCE(m.season_number, 1), COALESCE(m.episode_number, 1), m.id
                 LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':title_id' => $titleId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function firstPlayableByTitleIds(array $titleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $titleIds), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT
                    m.id,
                    m.title_id,
                    m.item_label,
                    m.season_number,
                    m.episode_number,
                    m.duration_minutes,
                    m.source_type,
                    m.source_path,
                    m.mime_type,
                    m.thumbnail_url,
                    m.backdrop_url
                FROM media_items m
                INNER JOIN titles t ON t.id = m.title_id
                INNER JOIN (
                    SELECT
                        title_id,
                        MIN(
                            COALESCE(season_number, 1) * 100000
                            + COALESCE(episode_number, 1) * 100
                            + id
                        ) AS min_sort
                    FROM media_items
                    WHERE is_published = 1
                      AND (upload_status IS NULL OR upload_status IN ('READY'))
                      AND title_id IN ({$placeholders})
                    GROUP BY title_id
                ) pick
                  ON pick.title_id = m.title_id
                 AND (
                    COALESCE(m.season_number, 1) * 100000
                    + COALESCE(m.episode_number, 1) * 100
                    + m.id
                 ) = pick.min_sort
                WHERE m.is_published = 1
                  AND (m.upload_status IS NULL OR m.upload_status IN ('READY'))
                  AND t.status = 'PUBLISHED'";

        $stmt = $this->db()->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['title_id']] = $row;
        }

        return $map;
    }

    public function suggestionsForTitle(int $titleId, int $excludeMediaId, int $limit = 8): array
    {
        $sql = "SELECT m.*, t.title_name, t.slug
                  FROM media_items m
                  INNER JOIN titles t ON t.id = m.title_id
                 WHERE m.title_id = :title_id
                   AND m.id <> :exclude_media_id
                   AND m.is_published = 1
                   AND (m.upload_status IS NULL OR m.upload_status IN ('READY'))
                 ORDER BY COALESCE(m.season_number, 1), COALESCE(m.episode_number, 1)
                 LIMIT :limit";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':title_id', $titleId, \PDO::PARAM_INT);
        $stmt->bindValue(':exclude_media_id', $excludeMediaId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
