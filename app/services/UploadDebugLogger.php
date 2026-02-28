<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class UploadDebugLogger
{
    private const LOG_FILE = '/storage/logs/onedrive-upload.log';

    private ?bool $enabledCache = null;
    private ?Setting $settings = null;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings;
    }

    public function enabled(): bool
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }

        $envEnabled = strtolower(trim((string) env('ONEDRIVE_UPLOAD_DEBUG', '0')));
        if (in_array($envEnabled, ['1', 'true', 'yes', 'on'], true)) {
            $this->enabledCache = true;
            return true;
        }

        $settings = $this->settings ?? new Setting();
        $settingEnabled = strtolower(trim((string) $settings->value('onedrive.upload_debug', '0')));
        $this->enabledCache = in_array($settingEnabled, ['1', 'true', 'yes', 'on'], true);
        return $this->enabledCache;
    }

    public function log(string $event, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        $entry = [
            'at' => date('c'),
            'event' => $event,
            'context' => $this->sanitize($context),
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $path = BASE_PATH . self::LOG_FILE;
        @file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND);
    }

    private function sanitize(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $keyStr = strtolower((string) $key);
            if ($this->isSensitiveKey($keyStr)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            if (is_string($value) && strlen($value) > 1024) {
                $sanitized[$key] = substr($value, 0, 1024) . '...[truncated]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (str_starts_with($key, 'has_')) {
            return false;
        }

        $sensitive = [
            'authorization',
            'access_token',
            'refresh_token',
            'client_secret',
            'password',
            'uploadurl',
            'upload_url',
            'tempauth',
        ];

        foreach ($sensitive as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
