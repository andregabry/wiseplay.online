<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class OneDriveService
{
    private const GRAPH_DRIVE_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/drive';
    private const DEFAULT_SCOPES = 'openid offline_access profile User.Read Files.ReadWrite';
    private const CACHE_TTL_SECONDS = 300;

    public function authority(?string $authority = null): string
    {
        $value = strtolower(trim((string) ($authority ?? env('ONEDRIVE_AUTHORITY', 'consumers'))));
        return in_array($value, ['consumers', 'common'], true) ? $value : 'consumers';
    }

    public function scopes(?string $scopes = null): string
    {
        $scopes = trim((string) ($scopes ?? env('ONEDRIVE_SCOPES', self::DEFAULT_SCOPES)));
        return $scopes !== '' ? $scopes : self::DEFAULT_SCOPES;
    }

    public function buildAuthorizeUrl(string $clientId, string $redirectUri, string $state, ?string $authority = null, ?string $scopes = null): string
    {
        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => $this->scopes($scopes),
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return 'https://login.microsoftonline.com/' . $this->authority($authority) . '/oauth2/v2.0/authorize?' . $params;
    }

    public function tokenEndpoint(?string $authority = null): string
    {
        return 'https://login.microsoftonline.com/' . $this->authority($authority) . '/oauth2/v2.0/token';
    }

    public function fetchDriveQuota(bool $forceRefresh = false, int $cacheTtlSeconds = self::CACHE_TTL_SECONDS): array
    {
        $settings = new Setting();
        $connected = $this->hasTokenContext($settings);

        if (!$connected) {
            return [
                'ok' => false,
                'connected' => false,
                'message' => 'OneDrive nao esta conectado. Clique em Connect para concluir OAuth 2.0.',
                'quota' => null,
                'last_updated' => null,
                'from_cache' => false,
                'is_stale' => false,
            ];
        }

        if (!$forceRefresh) {
            $cached = $this->readCachedQuota($settings, $cacheTtlSeconds);
            if ($cached !== null) {
                return $cached;
            }
        }

        $tokenResult = $this->resolveAccessToken($settings, false);
        if (($tokenResult['ok'] ?? false) !== true) {
            $fallback = $this->readCachedQuota($settings, 365 * 24 * 60 * 60);
            if ($fallback !== null) {
                $fallback['is_stale'] = true;
                $fallback['message'] = 'Token expirado. Exibindo ultimo quota em cache.';
                return $fallback;
            }

            return [
                'ok' => false,
                'connected' => true,
                'message' => (string) ($tokenResult['message'] ?? 'Falha ao autenticar com OneDrive.'),
                'quota' => null,
                'last_updated' => null,
                'from_cache' => false,
                'is_stale' => false,
            ];
        }

        $accessToken = (string) ($tokenResult['token'] ?? '');
        $graphResult = $this->requestDriveResource($accessToken);

        if (($graphResult['unauthorized'] ?? false) === true) {
            $retryToken = $this->resolveAccessToken($settings, true);
            if (($retryToken['ok'] ?? false) === true) {
                $graphResult = $this->requestDriveResource((string) ($retryToken['token'] ?? ''));
            } else {
                $graphResult = [
                    'ok' => false,
                    'message' => (string) ($retryToken['message'] ?? 'Falha ao renovar token OneDrive.'),
                    'unauthorized' => true,
                ];
            }
        }

        if (($graphResult['ok'] ?? false) !== true) {
            $fallback = $this->readCachedQuota($settings, 365 * 24 * 60 * 60);
            if ($fallback !== null) {
                $fallback['is_stale'] = true;
                $fallback['message'] = 'Graph indisponivel no momento. Exibindo ultimo quota em cache.';
                return $fallback;
            }

            return [
                'ok' => false,
                'connected' => true,
                'message' => (string) ($graphResult['message'] ?? 'Nao foi possivel obter quota OneDrive.'),
                'quota' => null,
                'last_updated' => null,
                'from_cache' => false,
                'is_stale' => false,
            ];
        }

        $body = $graphResult['body'] ?? null;
        $quota = is_array($body) && isset($body['quota']) && is_array($body['quota'])
            ? $this->normalizeQuota($body['quota'])
            : null;

        if ($quota === null) {
            return [
                'ok' => false,
                'connected' => true,
                'message' => 'Graph retornou drive sem informacoes de quota.',
                'quota' => null,
                'last_updated' => null,
                'from_cache' => false,
                'is_stale' => false,
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->storeQuotaCache($settings, $quota, $now);

        return [
            'ok' => true,
            'connected' => true,
            'message' => '',
            'quota' => $quota,
            'last_updated' => $now,
            'from_cache' => false,
            'is_stale' => false,
        ];
    }

    public function presentQuota(array $quotaResult): array
    {
        $quota = is_array($quotaResult['quota'] ?? null) ? $quotaResult['quota'] : null;
        $hasQuota = $quota !== null;

        $percentage = $hasQuota ? (float) ($quota['percentage'] ?? 0.0) : 0.0;
        $percentage = max(0.0, min(100.0, $percentage));
        $state = $hasQuota ? strtolower(trim((string) ($quota['state'] ?? 'unknown'))) : 'unknown';

        $stateLabel = match ($state) {
            'normal' => 'Normal',
            'nearing' => 'Nearing',
            'critical' => 'Critical',
            'exceeded' => 'Exceeded',
            default => 'Unknown',
        };

        $stateBadgeClass = match ($state) {
            'normal' => 'bg-label-success',
            'nearing' => 'bg-label-warning',
            'critical', 'exceeded' => 'bg-label-danger',
            default => 'bg-label-secondary',
        };

        $progressBarClass = match ($state) {
            'normal' => 'bg-success',
            'nearing' => 'bg-warning',
            'critical', 'exceeded' => 'bg-danger',
            default => 'bg-secondary',
        };

        $total = $hasQuota ? (int) ($quota['total'] ?? 0) : 0;
        $planHint = $total > 0 && $this->isOneTbPlan($total) ? '1 TB plan detected' : '';
        $message = trim((string) ($quotaResult['message'] ?? ''));

        if (!$hasQuota && $message === '') {
            $message = ($quotaResult['connected'] ?? false)
                ? 'Sem quota disponivel no momento.'
                : 'OneDrive nao conectado.';
        }

        return [
            'connected' => (bool) ($quotaResult['connected'] ?? false),
            'has_quota' => $hasQuota,
            'message' => $message,
            'total_human' => $hasQuota ? $this->bytesToHuman((int) ($quota['total'] ?? 0)) : '-',
            'used_human' => $hasQuota ? $this->bytesToHuman((int) ($quota['used'] ?? 0)) : '-',
            'remaining_human' => $hasQuota ? $this->bytesToHuman((int) ($quota['remaining'] ?? 0)) : '-',
            'percentage' => round($percentage, 1),
            'percentage_label' => number_format($percentage, 1, '.', '') . '%',
            'state' => $state,
            'state_label' => $stateLabel,
            'state_badge_class' => $stateBadgeClass,
            'progress_bar_class' => $progressBarClass,
            'last_updated' => (string) ($quotaResult['last_updated'] ?? '-'),
            'plan_hint' => $planHint,
            'from_cache' => (bool) ($quotaResult['from_cache'] ?? false),
            'is_stale' => (bool) ($quotaResult['is_stale'] ?? false),
        ];
    }

    private function hasTokenContext(Setting $settings): bool
    {
        $refreshToken = (string) $settings->value('onedrive.refresh_token', '');
        $accessToken = (string) $settings->value('onedrive.access_token', '');
        return $refreshToken !== '' || $accessToken !== '';
    }

    private function readCachedQuota(Setting $settings, int $maxAgeSeconds): ?array
    {
        $cacheJson = (string) $settings->value('onedrive.quota_cache_json', '');
        $cacheAt = (string) $settings->value('onedrive.quota_cache_at', '');

        if ($cacheJson === '' || $cacheAt === '') {
            return null;
        }

        $cacheTs = strtotime($cacheAt);
        if ($cacheTs === false) {
            return null;
        }

        if ((time() - $cacheTs) > $maxAgeSeconds) {
            return null;
        }

        $decoded = json_decode($cacheJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        $quota = $this->normalizeQuota($decoded);
        if ($quota === null) {
            return null;
        }

        return [
            'ok' => true,
            'connected' => true,
            'message' => '',
            'quota' => $quota,
            'last_updated' => $cacheAt,
            'from_cache' => true,
            'is_stale' => false,
        ];
    }

    private function storeQuotaCache(Setting $settings, array $quota, string $timestamp): void
    {
        $settings->upsert('onedrive', 'onedrive.quota_cache_json', json_encode($quota, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', false);
        $settings->upsert('onedrive', 'onedrive.quota_cache_at', $timestamp, false);
    }

    private function resolveAccessToken(Setting $settings, bool $forceRefresh): array
    {
        $accessToken = (string) $settings->value('onedrive.access_token', '');
        $refreshToken = (string) $settings->value('onedrive.refresh_token', '');
        $expiresAt = (string) $settings->value('onedrive.access_token_expires_at', '');

        if (!$forceRefresh && $accessToken !== '' && !$this->isExpired($expiresAt, 60)) {
            return [
                'ok' => true,
                'token' => $accessToken,
            ];
        }

        if ($refreshToken === '') {
            return [
                'ok' => false,
                'message' => 'OneDrive conectado sem refresh token. Reconecte a conta para renovar acesso.',
            ];
        }

        return $this->refreshAccessToken($settings, $refreshToken);
    }

    private function refreshAccessToken(Setting $settings, string $refreshToken): array
    {
        $clientId = (string) $settings->value('onedrive.client_id', '');
        $clientSecret = (string) $settings->value('onedrive.client_secret', '');
        $authority = (string) $settings->value('onedrive.authority', 'consumers');
        $scopes = (string) $settings->value('onedrive.scopes', self::DEFAULT_SCOPES);

        if ($clientId === '' || $clientSecret === '') {
            return [
                'ok' => false,
                'message' => 'Client ID/Secret ausentes para refresh do token OneDrive.',
            ];
        }

        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $this->scopes($scopes),
        ]);

        $endpoint = $this->tokenEndpoint($authority);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($curlError !== '' || $statusCode >= 400 || !is_array($decoded)) {
            $settings->upsert('onedrive', 'onedrive.last_connection_status', 'Token refresh failed', false);
            $errorDescription = is_array($decoded) && isset($decoded['error_description']) ? (string) $decoded['error_description'] : '';
            $message = $errorDescription !== '' ? 'Falha no refresh OneDrive: ' . $errorDescription : 'Falha no refresh do token OneDrive.';

            return [
                'ok' => false,
                'message' => $message,
            ];
        }

        $accessToken = isset($decoded['access_token']) && is_string($decoded['access_token'])
            ? trim($decoded['access_token'])
            : '';

        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Refresh concluido sem access token valido.',
            ];
        }

        $settings->upsert('onedrive', 'onedrive.access_token', $accessToken, true);
        $settings->upsert('onedrive', 'onedrive.access_token_masked', substr($accessToken, 0, 10) . '...', false);

        if (isset($decoded['refresh_token']) && is_string($decoded['refresh_token']) && trim($decoded['refresh_token']) !== '') {
            $settings->upsert('onedrive', 'onedrive.refresh_token', trim($decoded['refresh_token']), true);
        }

        if (isset($decoded['expires_in']) && is_numeric($decoded['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + max(30, (int) $decoded['expires_in']));
            $settings->upsert('onedrive', 'onedrive.access_token_expires_at', $expiresAt, false);
        }

        $settings->upsert('onedrive', 'onedrive.last_connection_status', 'Connected', false);
        $settings->upsert('onedrive', 'onedrive.last_connected_at', date('Y-m-d H:i:s'), false);

        return [
            'ok' => true,
            'token' => $accessToken,
        ];
    }

    private function requestDriveResource(string $accessToken): array
    {
        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Access token OneDrive ausente.',
                'unauthorized' => true,
            ];
        }

        $ch = curl_init(self::GRAPH_DRIVE_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $isUnauthorized = $statusCode === 401 || $statusCode === 403;

        if ($curlError !== '') {
            return [
                'ok' => false,
                'message' => 'Falha de rede ao consultar Microsoft Graph.',
                'unauthorized' => false,
            ];
        }

        if ($statusCode >= 400 || !is_array($decoded)) {
            $graphMessage = '';
            if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $graphMessage = $decoded['error']['message'];
            }

            return [
                'ok' => false,
                'message' => $graphMessage !== '' ? 'Graph retornou erro: ' . $graphMessage : 'Falha ao consultar quota no Graph.',
                'unauthorized' => $isUnauthorized,
            ];
        }

        return [
            'ok' => true,
            'body' => $decoded,
            'unauthorized' => false,
        ];
    }

    private function normalizeQuota(array $quota): ?array
    {
        if ($quota === []) {
            return null;
        }

        $hasTotal = array_key_exists('total', $quota) && is_numeric($quota['total']);
        $hasUsed = array_key_exists('used', $quota) && is_numeric($quota['used']);
        $hasRemaining = array_key_exists('remaining', $quota) && is_numeric($quota['remaining']);

        $total = $hasTotal ? max(0, (int) $quota['total']) : 0;
        $used = $hasUsed ? max(0, (int) $quota['used']) : 0;
        $remaining = $hasRemaining ? max(0, (int) $quota['remaining']) : null;
        $state = isset($quota['state']) ? strtolower(trim((string) $quota['state'])) : 'unknown';

        if ($total <= 0) {
            if ($hasUsed && $remaining !== null) {
                $total = $used + $remaining;
            } elseif ($hasUsed) {
                $total = $used;
            } elseif ($remaining !== null) {
                $total = $remaining;
            } else {
                return null;
            }
        }

        if ($remaining === null && $total >= $used) {
            $remaining = max(0, $total - $used);
        }

        if ($remaining === null) {
            $remaining = 0;
        }

        $percentage = $total > 0 ? ((float) $used / (float) $total) * 100.0 : 0.0;

        return [
            'total' => $total,
            'used' => $used,
            'remaining' => $remaining,
            'state' => $state !== '' ? $state : 'unknown',
            'percentage' => round(max(0.0, min(100.0, $percentage)), 1),
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

    private function bytesToHuman(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < (count($units) - 1)) {
            $value /= 1024;
            $unitIndex++;
        }

        $precision = match (true) {
            $unitIndex <= 2 => 0, // B/KB/MB
            $unitIndex === 3 => 2, // GB
            default => 3, // TB/PB to avoid "1.0 TB" for values like 0.999 TB
        };
        return number_format($value, $precision, '.', '') . ' ' . $units[$unitIndex];
    }

    private function isOneTbPlan(int $totalBytes): bool
    {
        $tb = 1024 * 1024 * 1024 * 1024;
        return $totalBytes >= (int) ($tb * 0.90) && $totalBytes <= (int) ($tb * 1.10);
    }
}
