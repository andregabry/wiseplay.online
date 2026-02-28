<?php

declare(strict_types=1);

namespace App\models;

final class MediaMaterial extends BaseModel
{
    public const SOURCE_TYPES = ['ONEDRIVE', 'URL', 'LOCAL'];

    public function sourceTypes(): array
    {
        return self::SOURCE_TYPES;
    }

    public function listByMedia(int $mediaId, bool $downloadableOnly = true): array
    {
        $sql = 'SELECT * FROM media_materials WHERE media_item_id = :media_item_id';
        if ($downloadableOnly) {
            $sql .= ' AND is_downloadable = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':media_item_id' => $mediaId]);
        return $stmt->fetchAll();
    }

    public function mapByMediaIds(array $mediaIds, bool $downloadableOnly = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM media_materials WHERE media_item_id IN ({$placeholders})";
        if ($downloadableOnly) {
            $sql .= ' AND is_downloadable = 1';
        }
        $sql .= ' ORDER BY media_item_id ASC, sort_order ASC, id ASC';

        $stmt = $this->db()->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $mediaId = (int) ($row['media_item_id'] ?? 0);
            if ($mediaId <= 0) {
                continue;
            }
            $map[$mediaId] ??= [];
            $map[$mediaId][] = $row;
        }

        return $map;
    }

    public function replaceForMedia(int $mediaId, array $materials): void
    {
        $this->db()->beginTransaction();

        try {
            $delete = $this->db()->prepare('DELETE FROM media_materials WHERE media_item_id = :media_item_id');
            $delete->execute([':media_item_id' => $mediaId]);

            if ($materials !== []) {
                $insert = $this->db()->prepare(
                    'INSERT INTO media_materials
                        (media_item_id, title, file_name, source_type, one_drive_item_id, one_drive_path, download_url, mime_type, file_size_bytes, sort_order, is_downloadable, created_at, updated_at)
                     VALUES
                        (:media_item_id, :title, :file_name, :source_type, :one_drive_item_id, :one_drive_path, :download_url, :mime_type, :file_size_bytes, :sort_order, :is_downloadable, NOW(), NOW())'
                );

                $sortOrder = 10;
                foreach ($materials as $material) {
                    $title = trim((string) ($material['title'] ?? ''));
                    $downloadUrl = trim((string) ($material['download_url'] ?? ''));
                    if ($title === '' || $downloadUrl === '') {
                        continue;
                    }

                    $sourceType = strtoupper(trim((string) ($material['source_type'] ?? 'URL')));
                    if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
                        $sourceType = 'URL';
                    }

                    $fileName = trim((string) ($material['file_name'] ?? ''));
                    if ($fileName === '') {
                        $parts = parse_url($downloadUrl);
                        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
                        $fileName = $path !== '' ? basename($path) : 'material-' . $sortOrder;
                    }

                    $insert->execute([
                        ':media_item_id' => $mediaId,
                        ':title' => substr($title, 0, 190),
                        ':file_name' => substr($fileName, 0, 255),
                        ':source_type' => $sourceType,
                        ':one_drive_item_id' => ($material['one_drive_item_id'] ?? null) ?: null,
                        ':one_drive_path' => ($material['one_drive_path'] ?? null) ?: null,
                        ':download_url' => $downloadUrl,
                        ':mime_type' => ($material['mime_type'] ?? null) ?: null,
                        ':file_size_bytes' => (int) ($material['file_size_bytes'] ?? 0) > 0 ? (int) $material['file_size_bytes'] : null,
                        ':sort_order' => max(1, (int) ($material['sort_order'] ?? $sortOrder)),
                        ':is_downloadable' => (int) ($material['is_downloadable'] ?? 1) === 1 ? 1 : 0,
                    ]);

                    $sortOrder += 10;
                }
            }

            $this->db()->commit();
        } catch (\Throwable $exception) {
            $this->db()->rollBack();
            throw $exception;
        }
    }
}
