<?php

declare(strict_types=1);

namespace App\services;

use App\models\Media;

final class MediaSourceResolver
{
    public function resolve(array $mediaItem): array
    {
        $sourceType = strtoupper(trim((string) ($mediaItem['source_type'] ?? 'URL')));
        if ($sourceType !== 'ONEDRIVE') {
            $path = trim((string) ($mediaItem['source_path'] ?? ''));
            return [
                'ok' => $path !== '',
                'url' => $path,
                'message' => $path !== '' ? '' : 'Fonte de midia indisponivel.',
                'from_cache' => false,
                'mime_type' => trim((string) ($mediaItem['mime_type'] ?? '')),
            ];
        }

        $externalId = trim((string) ($mediaItem['external_id'] ?? ''));
        if ($externalId === '') {
            return [
                'ok' => false,
                'url' => '',
                'message' => 'Midia OneDrive sem external_id.',
                'from_cache' => false,
            ];
        }

        $cachedUrl = trim((string) ($mediaItem['stream_url'] ?? ''));
        $cachedExpiresAt = trim((string) ($mediaItem['stream_url_expires_at'] ?? ''));
        if ($cachedUrl !== '' && !$this->isExpired($cachedExpiresAt, 30)) {
            return [
                'ok' => true,
                'url' => $cachedUrl,
                'message' => '',
                'from_cache' => true,
                'mime_type' => trim((string) ($mediaItem['mime_type'] ?? '')),
            ];
        }

        $graph = new OneDriveGraphService();
        $fresh = $graph->fetchDriveItemById($externalId);
        if (($fresh['ok'] ?? false) === true && isset($fresh['item']) && is_array($fresh['item'])) {
            $item = $fresh['item'];
            $downloadUrl = trim((string) ($item['downloadUrl'] ?? ''));
            if ($downloadUrl !== '') {
                $expiresAt = date('Y-m-d H:i:s', time() + 55 * 60);
                try {
                    (new Media())->updateSourceSync((int) ($mediaItem['id'] ?? 0), [
                        'stream_url' => $downloadUrl,
                        'download_url' => $downloadUrl,
                        'mime_type' => (string) ($item['mimeType'] ?? ''),
                        'file_size_bytes' => (int) ($item['size'] ?? 0),
                        'drive_id' => (string) ($item['drive_id'] ?? ''),
                        'parent_item_id' => (string) ($item['parent_item_id'] ?? ''),
                        'source_path' => (string) (($item['source_path'] ?? '') ?: ($mediaItem['source_path'] ?? '')),
                        'last_source_sync_at' => date('Y-m-d H:i:s'),
                        'stream_url_expires_at' => $expiresAt,
                        'upload_status' => 'READY',
                    ]);
                } catch (\Throwable) {
                    // Non-fatal: URL already resolved for current request.
                }

                return [
                    'ok' => true,
                    'url' => $downloadUrl,
                    'message' => '',
                    'from_cache' => false,
                    'mime_type' => (string) ($item['mimeType'] ?? ''),
                ];
            }
        }

        if ($cachedUrl !== '') {
            return [
                'ok' => true,
                'url' => $cachedUrl,
                'message' => 'Playback usando URL em cache por indisponibilidade temporaria do Graph.',
                'from_cache' => true,
                'mime_type' => trim((string) ($mediaItem['mime_type'] ?? '')),
            ];
        }

        return [
            'ok' => false,
            'url' => '',
            'message' => (string) ($fresh['message'] ?? 'Nao foi possivel resolver URL OneDrive no momento.'),
            'from_cache' => false,
            'mime_type' => trim((string) ($mediaItem['mime_type'] ?? '')),
        ];
    }

    private function isExpired(string $timestamp, int $leewaySeconds = 0): bool
    {
        if ($timestamp === '') {
            return true;
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return true;
        }
        return ($ts - $leewaySeconds) <= time();
    }
}
