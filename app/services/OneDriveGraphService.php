<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class OneDriveGraphService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const DEFAULT_CHUNK_BYTES = 10 * 1024 * 1024; // 10MB
    private const MIN_CHUNK_BYTES = 5 * 1024 * 1024; // 5MB
    private const MAX_CHUNK_BYTES = 20 * 1024 * 1024; // 20MB
    private const DEFAULT_MAX_RETRIES = 3;
    private const MIN_MAX_RETRIES = 2;
    private const MAX_MAX_RETRIES = 6;
    private const DEFAULT_RETRY_BACKOFF_MS = 500;
    private const MIN_RETRY_BACKOFF_MS = 200;
    private const MAX_RETRY_BACKOFF_MS = 10000;
    private const DEFAULT_MAX_VIDEO_GB = 10;
    private const MIN_MAX_VIDEO_GB = 1;
    private const MAX_MAX_VIDEO_GB = 20;
    private const MAX_SUBTITLE_BYTES = 8 * 1024 * 1024; // 8MB
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024; // 4MB

    private Setting $settings;
    private OneDriveService $oneDrive;
    private UploadDebugLogger $logger;

    public function __construct(?Setting $settings = null, ?OneDriveService $oneDrive = null)
    {
        $this->settings = $settings ?? new Setting();
        $this->oneDrive = $oneDrive ?? new OneDriveService();
        $this->logger = new UploadDebugLogger($this->settings);
    }

    public function debugEnabled(): bool
    {
        return $this->logger->enabled();
    }

    public function isConnected(): bool
    {
        $refreshToken = (string) $this->settings->value('onedrive.refresh_token', '');
        $accessToken = (string) $this->settings->value('onedrive.access_token', '');
        return $refreshToken !== '' || $accessToken !== '';
    }

    public function rootFolder(): string
    {
        $raw = trim((string) $this->settings->value('onedrive.root_folder', 'WisePlay'));
        if ($raw === '') {
            return 'WisePlay';
        }
        return trim($raw, '/');
    }

    public function chunkSizeBytes(): int
    {
        $chunkMb = $this->boundedIntegerSetting(
            'onedrive.upload_chunk_mb',
            (int) (env('ONEDRIVE_UPLOAD_CHUNK_MB', '10') ?? '10'),
            5,
            20
        );

        $bytes = $chunkMb * 1024 * 1024;
        return max(self::MIN_CHUNK_BYTES, min(self::MAX_CHUNK_BYTES, $bytes));
    }

    public function uploadMaxRetries(): int
    {
        return $this->boundedIntegerSetting(
            'onedrive.upload_max_retries',
            (int) (env('ONEDRIVE_UPLOAD_MAX_RETRIES', (string) self::DEFAULT_MAX_RETRIES) ?? (string) self::DEFAULT_MAX_RETRIES),
            self::MIN_MAX_RETRIES,
            self::MAX_MAX_RETRIES
        );
    }

    public function retryBackoffMs(): int
    {
        return $this->boundedIntegerSetting(
            'onedrive.upload_retry_backoff_ms',
            (int) (env('ONEDRIVE_UPLOAD_RETRY_BACKOFF_MS', (string) self::DEFAULT_RETRY_BACKOFF_MS) ?? (string) self::DEFAULT_RETRY_BACKOFF_MS),
            self::MIN_RETRY_BACKOFF_MS,
            self::MAX_RETRY_BACKOFF_MS
        );
    }

    public function uploadRuntimeConfig(): array
    {
        return [
            'chunk_bytes' => $this->chunkSizeBytes(),
            'max_retries' => $this->uploadMaxRetries(),
            'retry_backoff_ms' => $this->retryBackoffMs(),
            'max_video_gb' => $this->maxVideoGb(),
            'max_video_bytes' => $this->maxVideoBytes(),
        ];
    }

    public function maxVideoGb(): int
    {
        return $this->boundedIntegerSetting(
            'onedrive.upload_max_video_gb',
            (int) (env('ONEDRIVE_UPLOAD_MAX_VIDEO_GB', (string) self::DEFAULT_MAX_VIDEO_GB) ?? (string) self::DEFAULT_MAX_VIDEO_GB),
            self::MIN_MAX_VIDEO_GB,
            self::MAX_MAX_VIDEO_GB
        );
    }

    public function maxVideoBytes(): int
    {
        return $this->maxVideoGb() * 1024 * 1024 * 1024;
    }

    public function createVideoUploadSession(array $context, array $fileMeta): array
    {
        if (!$this->isConnected()) {
            return ['ok' => false, 'message' => 'OneDrive nao conectado. Acesse Settings > OneDrive para conectar.'];
        }

        $this->logger->log('session.start.request', [
            'title_type' => (string) ($context['title_type'] ?? ''),
            'title_slug' => (string) ($context['title_slug'] ?? ''),
            'season_number' => (int) ($context['season_number'] ?? 0),
            'file_name' => (string) ($fileMeta['name'] ?? ''),
            'file_size' => (int) ($fileMeta['size'] ?? 0),
            'mime' => (string) ($fileMeta['type'] ?? ''),
        ]);

        $validMeta = $this->validateVideoFileMeta($fileMeta);
        if (($validMeta['ok'] ?? false) !== true) {
            return $validMeta;
        }

        $folders = $this->resolveDestinationFolders(
            (string) ($context['title_type'] ?? 'MOVIE'),
            (string) ($context['title_slug'] ?? ''),
            (int) ($context['season_number'] ?? 0)
        );

        $token = $this->resolveAccessToken(false);
        if (($token['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($token['message'] ?? 'Falha de autenticacao com OneDrive.')];
        }

        $accessToken = (string) $token['token'];
        foreach ([$folders['video'], $folders['subtitles'], $folders['images']] as $path) {
            $ensured = $this->ensureFolderPath($path, $accessToken);
            if (($ensured['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => (string) ($ensured['message'] ?? 'Falha ao preparar pasta no OneDrive.')];
            }
        }

        $filename = $this->sanitizeFileName((string) ($fileMeta['name'] ?? 'video.mp4'));
        $graphPath = trim($folders['video'], '/') . '/' . $filename;
        $endpoint = '/me/drive/root:/' . $this->encodeGraphPath($graphPath) . ':/createUploadSession';

        $createSession = $this->graphRequestWithRetry('POST', $endpoint, [
            'item' => [
                '@microsoft.graph.conflictBehavior' => 'replace',
                'name' => $filename,
            ],
        ], $accessToken);

        if (($createSession['ok'] ?? false) !== true) {
            $this->logger->log('session.start.failed', [
                'status' => (int) ($createSession['status'] ?? 0),
                'message' => (string) ($createSession['message'] ?? ''),
                'graph_path' => $graphPath,
            ]);
            return ['ok' => false, 'message' => 'Falha ao criar upload session OneDrive.'];
        }

        $body = is_array($createSession['body'] ?? null) ? $createSession['body'] : [];
        $uploadUrl = isset($body['uploadUrl']) && is_string($body['uploadUrl']) ? trim($body['uploadUrl']) : '';
        $expiresAt = isset($body['expirationDateTime']) && is_string($body['expirationDateTime'])
            ? trim($body['expirationDateTime'])
            : '';

        if ($uploadUrl === '') {
            $this->logger->log('session.start.missing_upload_url', [
                'graph_path' => $graphPath,
                'response_keys' => array_keys($body),
            ]);
            return ['ok' => false, 'message' => 'Upload session criada sem uploadUrl valido.'];
        }

        $this->logger->log('session.start.success', [
            'graph_path' => $graphPath,
            'expires_at' => $expiresAt,
            'upload_url_present' => $uploadUrl !== '',
        ]);

        $runtime = $this->uploadRuntimeConfig();

        return [
            'ok' => true,
            'upload_url' => $uploadUrl,
            'expires_at' => $expiresAt,
            'destination_path' => '/' . trim($graphPath, '/'),
            'folder_video' => (string) $folders['video'],
            'chunk_bytes' => (int) ($runtime['chunk_bytes'] ?? self::DEFAULT_CHUNK_BYTES),
            'max_video_bytes' => (int) ($runtime['max_video_bytes'] ?? (self::DEFAULT_MAX_VIDEO_GB * 1024 * 1024 * 1024)),
            'max_retries' => (int) ($runtime['max_retries'] ?? self::DEFAULT_MAX_RETRIES),
            'retry_backoff_ms' => (int) ($runtime['retry_backoff_ms'] ?? self::DEFAULT_RETRY_BACKOFF_MS),
            'file_name' => $filename,
        ];
    }

    public function resolveDestinationFolders(string $titleType, string $titleSlug, int $seasonNumber): array
    {
        $root = $this->slugPathPart($this->rootFolder(), 'wiseplay');
        $slug = $this->slugPathPart($titleSlug, 'untitled');
        $season = str_pad((string) max(1, $seasonNumber), 2, '0', STR_PAD_LEFT);

        $type = strtoupper(trim($titleType));
        if ($type === 'MOVIE') {
            return [
                'base' => '/' . $root . '/movies/' . $slug,
                'video' => '/' . $root . '/movies/' . $slug . '/video',
                'subtitles' => '/' . $root . '/movies/' . $slug . '/subtitles',
                'images' => '/' . $root . '/movies/' . $slug . '/images',
            ];
        }

        if ($type === 'COURSE') {
            return [
                'base' => '/' . $root . '/courses/' . $slug . '/module-' . $season,
                'video' => '/' . $root . '/courses/' . $slug . '/module-' . $season . '/lessons',
                'subtitles' => '/' . $root . '/courses/' . $slug . '/module-' . $season . '/subtitles',
                'images' => '/' . $root . '/courses/' . $slug . '/images',
            ];
        }

        return [
            'base' => '/' . $root . '/series/' . $slug . '/season-' . $season,
            'video' => '/' . $root . '/series/' . $slug . '/season-' . $season . '/episodes',
            'subtitles' => '/' . $root . '/series/' . $slug . '/season-' . $season . '/subtitles',
            'images' => '/' . $root . '/series/' . $slug . '/images',
        ];
    }

    public function uploadMediaPackage(array $context, array $files): array
    {
        if (!$this->isConnected()) {
            return ['ok' => false, 'message' => 'OneDrive nao conectado. Acesse Settings > OneDrive para conectar.'];
        }

        $type = strtoupper(trim((string) ($context['title_type'] ?? 'MOVIE')));
        $slug = (string) ($context['title_slug'] ?? '');
        $season = (int) ($context['season_number'] ?? 1);
        $folders = $this->resolveDestinationFolders($type, $slug, $season);

        $videoFile = $this->extractSingleFile($files['video_file'] ?? null);
        if (($videoFile['exists'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Selecione um arquivo de video para upload OneDrive.'];
        }

        $this->logger->log('upload.package.start', [
            'title_type' => $type,
            'title_slug' => $slug,
            'season_number' => $season,
            'video_name' => (string) ($videoFile['name'] ?? ''),
            'video_size' => (int) ($videoFile['size'] ?? 0),
        ]);

        $validVideo = $this->validateVideoFile($videoFile);
        if (($validVideo['ok'] ?? false) !== true) {
            return $validVideo;
        }

        $token = $this->resolveAccessToken(false);
        if (($token['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($token['message'] ?? 'Falha de autenticacao com OneDrive.')];
        }

        $accessToken = (string) $token['token'];
        foreach ([$folders['video'], $folders['subtitles'], $folders['images']] as $path) {
            $ensured = $this->ensureFolderPath($path, $accessToken);
            if (($ensured['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => (string) ($ensured['message'] ?? 'Falha ao preparar pasta no OneDrive.')];
            }
        }

        $uploadedItems = [];
        $videoMeta = $this->uploadVideoWithSession($videoFile, $folders['video'], $accessToken);
        if (($videoMeta['ok'] ?? false) !== true) {
            $this->logger->log('upload.package.video.failed', [
                'message' => (string) ($videoMeta['message'] ?? ''),
            ]);
            return $videoMeta;
        }
        $uploadedItems[] = (string) ($videoMeta['item']['item_id'] ?? '');

        $subtitleFiles = $this->extractMultipleFiles($files['subtitle_files'] ?? null);
        $subtitleMetadata = [];
        foreach ($subtitleFiles as $index => $subtitleFile) {
            $validSubtitle = $this->validateSubtitleFile($subtitleFile);
            if (($validSubtitle['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $validSubtitle;
            }

            $upload = $this->uploadSmallFile($subtitleFile, $folders['subtitles'], $accessToken);
            if (($upload['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $upload;
            }

            $uploadedItems[] = (string) ($upload['item']['item_id'] ?? '');
            $subtitleMetadata[] = array_merge($upload['item'], [
                'language' => $this->inferSubtitleLanguage($subtitleFile['name'] ?? '', $index),
            ]);
        }

        $thumbnailMeta = null;
        $posterMeta = null;
        $backdropMeta = null;

        $thumbnail = $this->extractSingleFile($files['thumbnail_file'] ?? null);
        if (($thumbnail['exists'] ?? false) === true) {
            $validImage = $this->validateImageFile($thumbnail);
            if (($validImage['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $validImage;
            }
            $upload = $this->uploadSmallFile($thumbnail, $folders['images'], $accessToken);
            if (($upload['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $upload;
            }
            $uploadedItems[] = (string) ($upload['item']['item_id'] ?? '');
            $thumbnailMeta = $upload['item'];
        }

        $poster = $this->extractSingleFile($files['poster_file'] ?? null);
        if (($poster['exists'] ?? false) === true) {
            $validImage = $this->validateImageFile($poster);
            if (($validImage['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $validImage;
            }
            $upload = $this->uploadSmallFile($poster, $folders['images'], $accessToken);
            if (($upload['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $upload;
            }
            $uploadedItems[] = (string) ($upload['item']['item_id'] ?? '');
            $posterMeta = $upload['item'];
        }

        $backdrop = $this->extractSingleFile($files['backdrop_file'] ?? null);
        if (($backdrop['exists'] ?? false) === true) {
            $validImage = $this->validateImageFile($backdrop);
            if (($validImage['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $validImage;
            }
            $upload = $this->uploadSmallFile($backdrop, $folders['images'], $accessToken);
            if (($upload['ok'] ?? false) !== true) {
                $this->cleanupUploadedItems($uploadedItems, $accessToken);
                return $upload;
            }
            $uploadedItems[] = (string) ($upload['item']['item_id'] ?? '');
            $backdropMeta = $upload['item'];
        }

        $this->settings->upsert('onedrive', 'onedrive.last_upload_at', date('Y-m-d H:i:s'), false);
        $this->settings->upsert('onedrive', 'onedrive.last_connection_status', 'Connected', false);
        $this->logger->log('upload.package.success', [
            'video_item_id' => (string) (($videoMeta['item']['item_id'] ?? '')),
            'subtitle_count' => count($subtitleMetadata),
            'has_thumbnail' => is_array($thumbnailMeta),
            'has_poster' => is_array($posterMeta),
            'has_backdrop' => is_array($backdropMeta),
        ]);

        return [
            'ok' => true,
            'message' => 'Upload OneDrive concluido.',
            'folders' => $folders,
            'video' => $videoMeta['item'],
            'video_chunks_total' => (int) ($videoMeta['chunks_total'] ?? 0),
            'video_chunks_uploaded' => (int) ($videoMeta['chunks_uploaded'] ?? 0),
            'subtitles' => $subtitleMetadata,
            'thumbnail' => $thumbnailMeta,
            'poster' => $posterMeta,
            'backdrop' => $backdropMeta,
        ];
    }

    public function fetchDriveItemById(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['ok' => false, 'message' => 'Item id OneDrive invalido.'];
        }

        $token = $this->resolveAccessToken(false);
        if (($token['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($token['message'] ?? 'Falha ao autenticar OneDrive.')];
        }

        $result = $this->graphRequestWithRetry('GET', '/me/drive/items/' . rawurlencode($itemId), null, (string) $token['token']);
        if (($result['ok'] ?? false) !== true && (int) ($result['status'] ?? 0) >= 401) {
            $retry = $this->resolveAccessToken(true);
            if (($retry['ok'] ?? false) === true) {
                $result = $this->graphRequestWithRetry('GET', '/me/drive/items/' . rawurlencode($itemId), null, (string) $retry['token']);
            }
        }

        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Falha ao buscar item no OneDrive.')];
        }

        $item = is_array($result['body'] ?? null) ? $result['body'] : [];
        $mapped = $this->mapDriveItem($item);
        if ($mapped === null) {
            return ['ok' => false, 'message' => 'Resposta do OneDrive sem metadados validos do item.'];
        }

        return ['ok' => true, 'item' => $mapped];
    }

    public function deleteItemById(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['ok' => true, 'message' => 'Item vazio ignorado.'];
        }

        $token = $this->resolveAccessToken(false);
        if (($token['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($token['message'] ?? 'Falha ao autenticar OneDrive para exclusao.')];
        }

        $result = $this->graphRequestWithRetry('DELETE', '/me/drive/items/' . rawurlencode($itemId), null, (string) $token['token']);
        if (($result['ok'] ?? false) !== true && (int) ($result['status'] ?? 0) >= 401) {
            $retry = $this->resolveAccessToken(true);
            if (($retry['ok'] ?? false) === true) {
                $result = $this->graphRequestWithRetry('DELETE', '/me/drive/items/' . rawurlencode($itemId), null, (string) $retry['token']);
            }
        }

        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Falha ao remover arquivo no OneDrive.')];
        }

        return ['ok' => true, 'message' => 'Arquivo removido no OneDrive.'];
    }

    private function resolveAccessToken(bool $forceRefresh): array
    {
        $accessToken = (string) $this->settings->value('onedrive.access_token', '');
        $refreshToken = (string) $this->settings->value('onedrive.refresh_token', '');
        $expiresAt = (string) $this->settings->value('onedrive.access_token_expires_at', '');

        $this->logger->log('token.resolve', [
            'force_refresh' => $forceRefresh,
            'has_access_token' => $accessToken !== '',
            'has_refresh_token' => $refreshToken !== '',
            'expires_at' => $expiresAt,
            'is_expired' => $this->isExpired($expiresAt, 60),
        ]);

        if (!$forceRefresh && $accessToken !== '' && !$this->isExpired($expiresAt, 60)) {
            $this->logger->log('token.resolve.cached_ok');
            return ['ok' => true, 'token' => $accessToken];
        }

        if ($refreshToken === '') {
            $this->logger->log('token.resolve.failed_no_refresh');
            return ['ok' => false, 'message' => 'Refresh token OneDrive ausente. Reconecte a conta em Settings.'];
        }

        $clientId = (string) $this->settings->value('onedrive.client_id', '');
        $clientSecret = (string) $this->settings->value('onedrive.client_secret', '');
        $authority = (string) $this->settings->value('onedrive.authority', 'consumers');
        $scopes = (string) $this->settings->value('onedrive.scopes', 'openid offline_access profile User.Read Files.ReadWrite');

        if ($clientId === '' || $clientSecret === '') {
            $this->logger->log('token.resolve.failed_missing_client_credentials');
            return ['ok' => false, 'message' => 'Client ID/Secret OneDrive nao configurados.'];
        }

        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $this->oneDrive->scopes($scopes),
        ]);

        $ch = curl_init($this->oneDrive->tokenEndpoint($authority));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($error !== '' || $status >= 400 || !is_array($decoded)) {
            $graphError = is_array($decoded) && isset($decoded['error_description']) ? (string) $decoded['error_description'] : '';
            $message = $graphError !== '' ? 'Falha no refresh token OneDrive: ' . $graphError : 'Falha ao renovar token OneDrive.';
            $this->settings->upsert('onedrive', 'onedrive.last_connection_status', 'Token refresh failed', false);
            $this->logger->log('token.refresh.failed', [
                'http_status' => $status,
                'curl_error' => $error !== '' ? 'yes' : 'no',
                'graph_error_present' => $graphError !== '',
            ]);
            return ['ok' => false, 'message' => $message];
        }

        $newAccessToken = isset($decoded['access_token']) && is_string($decoded['access_token']) ? trim($decoded['access_token']) : '';
        if ($newAccessToken === '') {
            $this->logger->log('token.refresh.failed_missing_access_token');
            return ['ok' => false, 'message' => 'Refresh token concluido sem access_token valido.'];
        }

        $this->settings->upsert('onedrive', 'onedrive.access_token', $newAccessToken, true);
        $this->settings->upsert('onedrive', 'onedrive.access_token_masked', substr($newAccessToken, 0, 10) . '...', false);
        if (isset($decoded['refresh_token']) && is_string($decoded['refresh_token']) && trim($decoded['refresh_token']) !== '') {
            $this->settings->upsert('onedrive', 'onedrive.refresh_token', trim($decoded['refresh_token']), true);
        }
        if (isset($decoded['expires_in']) && is_numeric($decoded['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + max(30, (int) $decoded['expires_in']));
            $this->settings->upsert('onedrive', 'onedrive.access_token_expires_at', $expiresAt, false);
        }
        $this->settings->upsert('onedrive', 'onedrive.last_connection_status', 'Connected', false);
        $this->settings->upsert('onedrive', 'onedrive.last_connected_at', date('Y-m-d H:i:s'), false);
        $this->logger->log('token.refresh.success', [
            'http_status' => $status,
            'expires_in' => isset($decoded['expires_in']) && is_numeric($decoded['expires_in']) ? (int) $decoded['expires_in'] : null,
        ]);

        return ['ok' => true, 'token' => $newAccessToken];
    }

    private function ensureFolderPath(string $path, string $accessToken): array
    {
        $normalized = trim($path, '/');
        if ($normalized === '') {
            return ['ok' => true];
        }

        $this->logger->log('folder.ensure.start', ['path' => '/' . $normalized]);

        $segments = array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => trim($segment) !== ''));
        $current = '';
        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : ($current . '/' . $segment);
            $exists = $this->graphRequestWithRetry('GET', '/me/drive/root:/' . $this->encodeGraphPath($current), null, $accessToken);
            if (($exists['ok'] ?? false) === true) {
                continue;
            }

            if ((int) ($exists['status'] ?? 0) !== 404) {
                $this->logger->log('folder.ensure.failed_exists_check', [
                    'segment_path' => $current,
                    'status' => (int) ($exists['status'] ?? 0),
                    'message' => (string) ($exists['message'] ?? ''),
                ]);
                return ['ok' => false, 'message' => 'Falha ao verificar pasta OneDrive: ' . $current];
            }

            $parentPath = trim(dirname('/' . $current), '/');
            $createEndpoint = $parentPath === ''
                ? '/me/drive/root/children'
                : '/me/drive/root:/' . $this->encodeGraphPath($parentPath) . ':/children';

            $create = $this->graphRequestWithRetry('POST', $createEndpoint, [
                'name' => $segment,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'replace',
            ], $accessToken);

            if (($create['ok'] ?? false) !== true) {
                $this->logger->log('folder.ensure.failed_create', [
                    'segment' => $segment,
                    'segment_path' => $current,
                    'status' => (int) ($create['status'] ?? 0),
                    'message' => (string) ($create['message'] ?? ''),
                ]);
                return ['ok' => false, 'message' => 'Falha ao criar pasta OneDrive: ' . $segment];
            }
        }

        $this->logger->log('folder.ensure.success', ['path' => '/' . $normalized]);
        return ['ok' => true];
    }

    private function uploadVideoWithSession(array $file, string $folderPath, string $accessToken): array
    {
        $filename = $this->sanitizeFileName((string) ($file['name'] ?? 'video.mp4'));
        $graphPath = trim($folderPath, '/') . '/' . $filename;

        $createSession = $this->graphRequestWithRetry('POST', '/me/drive/root:/' . $this->encodeGraphPath($graphPath) . ':/createUploadSession', [
            'item' => [
                '@microsoft.graph.conflictBehavior' => 'replace',
                'name' => $filename,
            ],
        ], $accessToken);

        if (($createSession['ok'] ?? false) !== true) {
            $this->logger->log('upload.video.session_failed', [
                'graph_path' => $graphPath,
                'status' => (int) ($createSession['status'] ?? 0),
                'message' => (string) ($createSession['message'] ?? ''),
            ]);
            return ['ok' => false, 'message' => 'Falha ao iniciar upload session no OneDrive.'];
        }

        $uploadUrl = (string) (($createSession['body']['uploadUrl'] ?? ''));
        if ($uploadUrl === '') {
            $this->logger->log('upload.video.session_missing_upload_url', [
                'graph_path' => $graphPath,
                'response_keys' => is_array($createSession['body'] ?? null) ? array_keys((array) $createSession['body']) : [],
            ]);
            return ['ok' => false, 'message' => 'Upload session retornou URL invalida.'];
        }

        $this->logger->log('upload.video.session_created', [
            'graph_path' => $graphPath,
            'upload_url_present' => true,
            'expires_at' => (string) (($createSession['body']['expirationDateTime'] ?? '')),
            'file_size' => (int) ($file['size'] ?? 0),
        ]);

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        $fp = @fopen($tmpPath, 'rb');
        if (!is_resource($fp)) {
            return ['ok' => false, 'message' => 'Falha ao abrir arquivo de video temporario.'];
        }

        $runtime = $this->uploadRuntimeConfig();
        $chunkBytes = (int) ($runtime['chunk_bytes'] ?? self::DEFAULT_CHUNK_BYTES);
        $chunkBytes = max(self::MIN_CHUNK_BYTES, min(self::MAX_CHUNK_BYTES, $chunkBytes));
        $maxRetries = (int) ($runtime['max_retries'] ?? self::DEFAULT_MAX_RETRIES);
        $maxRetries = max(self::MIN_MAX_RETRIES, min(self::MAX_MAX_RETRIES, $maxRetries));
        $retryBackoffMs = (int) ($runtime['retry_backoff_ms'] ?? self::DEFAULT_RETRY_BACKOFF_MS);
        $retryBackoffMs = max(self::MIN_RETRY_BACKOFF_MS, min(self::MAX_RETRY_BACKOFF_MS, $retryBackoffMs));

        $offset = 0;
        $chunksUploaded = 0;
        $chunksTotal = (int) max(1, ceil($size / $chunkBytes));
        $finalItem = null;

        while ($offset < $size) {
            $chunkLength = min($chunkBytes, $size - $offset);
            $buffer = fread($fp, $chunkLength);
            if ($buffer === false || strlen($buffer) !== $chunkLength) {
                fclose($fp);
                return ['ok' => false, 'message' => 'Falha na leitura de chunk do video.'];
            }

            $end = $offset + $chunkLength - 1;
            $chunkIndex = (int) floor($offset / $chunkBytes) + 1;
            $this->logger->log('upload.video.chunk.start', [
                'chunk_index' => $chunkIndex,
                'chunk_total' => $chunksTotal,
                'start' => $offset,
                'end' => $end,
                'content_length' => $chunkLength,
                'content_range' => 'bytes ' . $offset . '-' . $end . '/' . $size,
            ]);
            $response = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $response = $this->rawUploadChunk($uploadUrl, $buffer, $offset, $end, $size);
                $status = (int) ($response['status'] ?? 0);
                $retryable = ($response['ok'] ?? false) !== true
                    && ($status === 0 || $status === 408 || $status === 429 || $status >= 500);

                if (!$retryable || $attempt >= $maxRetries) {
                    break;
                }

                $this->logger->log('upload.video.chunk.retry', [
                    'chunk_index' => $chunkIndex,
                    'attempt' => $attempt,
                    'status' => $status,
                ]);
                usleep(($retryBackoffMs * 1000) * $attempt);
            }

            if (($response['ok'] ?? false) !== true) {
                fclose($fp);
                $this->logger->log('upload.video.chunk.failed', [
                    'chunk_index' => $chunkIndex,
                    'status' => (int) ($response['status'] ?? 0),
                    'message' => (string) ($response['message'] ?? ''),
                ]);
                return ['ok' => false, 'message' => (string) ($response['message'] ?? 'Falha ao enviar chunk para OneDrive.')];
            }

            $chunksUploaded++;
            $this->logger->log('upload.video.chunk.success', [
                'chunk_index' => $chunkIndex,
                'status' => (int) ($response['status'] ?? 0),
                'has_body' => is_array($response['body'] ?? null),
            ]);
            if (isset($response['body']) && is_array($response['body']) && isset($response['body']['id'])) {
                $finalItem = $response['body'];
            }

            $offset += $chunkLength;
        }
        fclose($fp);

        if (!is_array($finalItem)) {
            $this->logger->log('upload.video.failed_missing_final_item', [
                'chunks_uploaded' => $chunksUploaded,
                'chunks_total' => $chunksTotal,
            ]);
            return ['ok' => false, 'message' => 'Upload concluido sem metadados finais do arquivo.'];
        }

        $mapped = $this->mapDriveItem($finalItem);
        if ($mapped === null) {
            $this->logger->log('upload.video.failed_map_metadata');
            return ['ok' => false, 'message' => 'Falha ao mapear metadados finais do upload.'];
        }

        $this->logger->log('upload.video.success', [
            'item_id' => (string) ($mapped['item_id'] ?? ''),
            'source_path' => (string) ($mapped['source_path'] ?? ''),
            'chunks_uploaded' => $chunksUploaded,
            'chunks_total' => $chunksTotal,
        ]);

        return [
            'ok' => true,
            'item' => $mapped,
            'chunks_total' => $chunksTotal,
            'chunks_uploaded' => $chunksUploaded,
        ];
    }

    private function uploadSmallFile(array $file, string $folderPath, string $accessToken): array
    {
        $filename = $this->sanitizeFileName((string) ($file['name'] ?? 'file.bin'));
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $content = @file_get_contents($tmpPath);
        if ($content === false) {
            return ['ok' => false, 'message' => 'Falha ao ler arquivo para upload OneDrive.'];
        }

        $graphPath = trim($folderPath, '/') . '/' . $filename;
        $endpoint = '/me/drive/root:/' . $this->encodeGraphPath($graphPath) . ':/content';

        $result = $this->graphRequestWithRetry('PUT', $endpoint, null, $accessToken, [
            'Content-Type: ' . ((string) ($file['type'] ?? 'application/octet-stream')),
        ], $content);

        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Falha ao enviar arquivo ao OneDrive: ' . $filename];
        }

        $mapped = $this->mapDriveItem(is_array($result['body'] ?? null) ? $result['body'] : []);
        if ($mapped === null) {
            return ['ok' => false, 'message' => 'Upload concluido sem metadados validos para ' . $filename];
        }

        return ['ok' => true, 'item' => $mapped];
    }

    private function graphRequest(string $method, string $endpoint, ?array $jsonBody, string $accessToken, array $extraHeaders = [], ?string $rawBody = null): array
    {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : (self::GRAPH_BASE . $endpoint);
        $ch = curl_init($url);

        $headers = array_merge([
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ], $extraHeaders);

            $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($rawBody !== null) {
            $options[CURLOPT_POSTFIELDS] = $rawBody;
        } elseif (is_array($jsonBody)) {
            $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_POSTFIELDS] = $payload !== false ? $payload : '{}';
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            $this->logger->log('graph.request.network_error', [
                'method' => strtoupper($method),
                'endpoint' => $this->safeEndpointForLog($endpoint),
                'status' => $status,
            ]);
            return ['ok' => false, 'status' => $status, 'message' => 'Erro de rede Graph: ' . $error];
        }

        $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if ($status >= 200 && $status < 300) {
            $this->logger->log('graph.request.success', [
                'method' => strtoupper($method),
                'endpoint' => $this->safeEndpointForLog($endpoint),
                'status' => $status,
            ]);
            return ['ok' => true, 'status' => $status, 'body' => is_array($body) ? $body : []];
        }

        $graphMessage = '';
        if (is_array($body) && isset($body['error']['message']) && is_string($body['error']['message'])) {
            $graphMessage = $body['error']['message'];
        }

        $this->logger->log('graph.request.failed', [
            'method' => strtoupper($method),
            'endpoint' => $this->safeEndpointForLog($endpoint),
            'status' => $status,
            'graph_message_present' => $graphMessage !== '',
        ]);

        return [
            'ok' => false,
            'status' => $status,
            'message' => $graphMessage !== '' ? $graphMessage : 'Graph retornou erro HTTP ' . $status,
            'body' => is_array($body) ? $body : null,
        ];
    }

    private function graphRequestWithRetry(string $method, string $endpoint, ?array $jsonBody, string $accessToken, array $extraHeaders = [], ?string $rawBody = null, int $maxAttempts = 0): array
    {
        if ($maxAttempts <= 0) {
            $maxAttempts = max(2, min(5, $this->uploadMaxRetries()));
        }

        $backoffMs = max(200, min(3000, $this->retryBackoffMs()));
        $attempt = 1;
        do {
            $result = $this->graphRequest($method, $endpoint, $jsonBody, $accessToken, $extraHeaders, $rawBody);
            $status = (int) ($result['status'] ?? 0);
            $retryable = ($result['ok'] ?? false) !== true
                && ($status === 0 || $status === 408 || $status === 429 || $status >= 500);

            if (!$retryable || $attempt >= $maxAttempts) {
                return $result;
            }

            $this->logger->log('graph.request.retry', [
                'method' => strtoupper($method),
                'endpoint' => $this->safeEndpointForLog($endpoint),
                'status' => $status,
                'attempt' => $attempt,
            ]);
            usleep(($backoffMs * 1000) * $attempt);
            $attempt++;
        } while ($attempt <= $maxAttempts);

        return $result;
    }

    private function boundedIntegerSetting(string $key, int $default, int $min, int $max): int
    {
        $value = (string) $this->settings->value($key, (string) $default);
        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;
        if ($intValue < $min) {
            return $min;
        }
        if ($intValue > $max) {
            return $max;
        }
        return $intValue;
    }

    private function rawUploadChunk(string $uploadUrl, string $chunkData, int $start, int $end, int $total): array
    {
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $chunkData,
            CURLOPT_HTTPHEADER => [
                'Content-Length: ' . strlen($chunkData),
                'Content-Range: bytes ' . $start . '-' . $end . '/' . $total,
                'Content-Type: application/octet-stream',
            ],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            $this->logger->log('upload.video.chunk.network_error', [
                'start' => $start,
                'end' => $end,
                'total' => $total,
                'status' => $status,
            ]);
            return ['ok' => false, 'status' => $status, 'message' => 'Falha no chunk upload: ' . $error];
        }

        $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (in_array($status, [200, 201, 202], true)) {
            $this->logger->log('upload.video.chunk.response', [
                'status' => $status,
                'start' => $start,
                'end' => $end,
                'total' => $total,
                'has_body' => is_array($body),
                'body' => is_array($body) ? $body : null,
                'next_expected_ranges' => is_array($body) && isset($body['nextExpectedRanges']) ? $body['nextExpectedRanges'] : [],
            ]);
            return ['ok' => true, 'status' => $status, 'body' => is_array($body) ? $body : null];
        }

        return ['ok' => false, 'status' => $status, 'message' => 'Chunk upload retornou HTTP ' . $status];
    }

    private function extractSingleFile(mixed $file): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return ['exists' => false];
        }

        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['exists' => false];
        }

        return [
            'exists' => $error === UPLOAD_ERR_OK,
            'error' => $error,
            'name' => (string) ($file['name'] ?? ''),
            'type' => (string) ($file['type'] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
        ];
    }

    private function extractMultipleFiles(mixed $file): array
    {
        if (!is_array($file) || !isset($file['name']) || !is_array($file['name'])) {
            return [];
        }

        $items = [];
        $count = count($file['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $items[] = [
                'exists' => $error === UPLOAD_ERR_OK,
                'error' => $error,
                'name' => (string) ($file['name'][$i] ?? ''),
                'type' => (string) ($file['type'][$i] ?? ''),
                'tmp_name' => (string) ($file['tmp_name'][$i] ?? ''),
                'size' => (int) ($file['size'][$i] ?? 0),
            ];
        }

        return $items;
    }

    private function validateVideoFile(array $file): array
    {
        if (($file['exists'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Arquivo de video nao enviado corretamente.'];
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mkv', 'mov', 'webm', 'ts'], true)) {
            return ['ok' => false, 'message' => 'Video deve ser .mp4, .mkv, .mov, .webm ou .ts.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxVideoBytes()) {
            return ['ok' => false, 'message' => 'Video invalido. Limite maximo: ' . $this->maxVideoGb() . 'GB.'];
        }

        return ['ok' => true];
    }

    private function validateVideoFileMeta(array $fileMeta): array
    {
        $name = trim((string) ($fileMeta['name'] ?? ''));
        $size = (int) ($fileMeta['size'] ?? 0);
        if ($name === '' || $size <= 0) {
            return ['ok' => false, 'message' => 'Arquivo de video invalido para iniciar upload session.'];
        }

        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mkv', 'mov', 'webm', 'ts'], true)) {
            return ['ok' => false, 'message' => 'Video deve ser .mp4, .mkv, .mov, .webm ou .ts.'];
        }

        if ($size > $this->maxVideoBytes()) {
            return ['ok' => false, 'message' => 'Video invalido. Limite maximo: ' . $this->maxVideoGb() . 'GB.'];
        }

        return ['ok' => true];
    }

    private function validateSubtitleFile(array $file): array
    {
        if (($file['exists'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Falha no upload de legenda.'];
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['vtt', 'srt'], true)) {
            return ['ok' => false, 'message' => 'Legenda deve estar em .vtt ou .srt.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_SUBTITLE_BYTES) {
            return ['ok' => false, 'message' => 'Legenda invalida. Limite maximo: 8MB por arquivo.'];
        }

        return ['ok' => true];
    }

    private function validateImageFile(array $file): array
    {
        if (($file['exists'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Falha no upload da imagem.'];
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['ok' => false, 'message' => 'Imagem deve ser JPG, PNG ou WEBP.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_IMAGE_BYTES) {
            return ['ok' => false, 'message' => 'Imagem invalida. Limite maximo: 4MB.'];
        }

        return ['ok' => true];
    }

    private function mapDriveItem(array $item): ?array
    {
        if (!isset($item['id']) || !is_string($item['id'])) {
            return null;
        }

        $parentReference = isset($item['parentReference']) && is_array($item['parentReference'])
            ? $item['parentReference']
            : [];
        $parentPath = '';
        if (isset($parentReference['path']) && is_string($parentReference['path'])) {
            $path = $parentReference['path'];
            $rootPos = strpos($path, '/root:');
            $parentPath = $rootPos !== false ? substr($path, $rootPos + 6) : $path;
        }

        $mimeType = '';
        if (isset($item['file']) && is_array($item['file']) && isset($item['file']['mimeType']) && is_string($item['file']['mimeType'])) {
            $mimeType = $item['file']['mimeType'];
        }

        $downloadUrl = '';
        if (isset($item['@microsoft.graph.downloadUrl']) && is_string($item['@microsoft.graph.downloadUrl'])) {
            $downloadUrl = $item['@microsoft.graph.downloadUrl'];
        }

        return [
            'item_id' => (string) $item['id'],
            'name' => (string) ($item['name'] ?? ''),
            'parent_path' => $parentPath,
            'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : 0,
            'mimeType' => $mimeType,
            'webUrl' => (string) ($item['webUrl'] ?? ''),
            'downloadUrl' => $downloadUrl,
            'drive_id' => (string) ($parentReference['driveId'] ?? ''),
            'parent_item_id' => (string) ($parentReference['id'] ?? ''),
            'source_path' => ($parentPath !== '' ? $parentPath . '/' : '/') . (string) ($item['name'] ?? ''),
        ];
    }

    private function cleanupUploadedItems(array $itemIds, string $accessToken): void
    {
        foreach ($itemIds as $itemId) {
            $id = trim((string) $itemId);
            if ($id === '') {
                continue;
            }
            $this->graphRequest('DELETE', '/me/drive/items/' . rawurlencode($id), null, $accessToken);
        }
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

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'file_' . date('YmdHis');
        }
        $name = preg_replace('/[^\w\-.]+/u', '-', $name);
        $name = trim((string) $name, '-');
        return $name !== '' ? $name : ('file_' . date('YmdHis'));
    }

    private function encodeGraphPath(string $path): string
    {
        $segments = array_map(static fn (string $seg): string => rawurlencode($seg), explode('/', trim($path, '/')));
        return implode('/', array_filter($segments, static fn (string $v): bool => $v !== ''));
    }

    private function safeEndpointForLog(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http')) {
            $parts = parse_url($endpoint);
            if (!is_array($parts)) {
                return '[external-url]';
            }

            $host = isset($parts['host']) ? (string) $parts['host'] : 'unknown-host';
            $path = isset($parts['path']) ? (string) $parts['path'] : '';
            return $host . $path;
        }

        return $endpoint;
    }

    private function slugPathPart(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');
        return $value !== '' ? $value : $fallback;
    }

    private function inferSubtitleLanguage(string $fileName, int $index): string
    {
        $base = strtolower(pathinfo($fileName, PATHINFO_FILENAME));
        if (preg_match('/([a-z]{2}(?:-[a-z]{2})?)$/', $base, $matches) === 1) {
            return $matches[1];
        }

        return $index === 0 ? 'pt-BR' : 'und';
    }
}
