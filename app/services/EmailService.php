<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class EmailService
{
    private Setting $settings;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? new Setting();
    }

    public function sendVerificationCode(string $toEmail, string $toName, string $code, int $expiresMinutes): array
    {
        $subjectTemplate = (string) $this->settings->value('email.verification_subject', 'WisePlay - Codigo de verificacao');
        $bodyTemplate = (string) $this->settings->value(
            'email.verification_template',
            "Ola {{name}},\n\nSeu codigo de verificacao WisePlay: {{code}}\nEste codigo expira em {{expires_minutes}} minutos.\n\nSe voce nao solicitou este cadastro, ignore este email."
        );

        $replacements = [
            '{{name}}' => $toName !== '' ? $toName : 'usuario',
            '{{email}}' => $toEmail,
            '{{code}}' => $code,
            '{{expires_minutes}}' => (string) $expiresMinutes,
        ];

        $subject = strtr($subjectTemplate, $replacements);
        $bodyText = strtr($bodyTemplate, $replacements);

        $smtpEnabled = $this->smtpEnabled();
        if ($smtpEnabled) {
            $smtpResult = $this->sendViaSmtp($toEmail, $subject, $bodyText);
            if (($smtpResult['ok'] ?? false) === true) {
                return $smtpResult;
            }

            $devFallbackAllowed = $this->isTruthy((string) $this->settings->value('email.dev_log_delivery', '1'));
            if (!$devFallbackAllowed) {
                return $smtpResult;
            }
        }

        $this->logDelivery($toEmail, $subject, $bodyText);
        return ['ok' => true, 'message' => 'Email de verificacao registrado em log local.'];
    }

    private function sendViaSmtp(string $toEmail, string $subject, string $bodyText): array
    {
        $host = trim($this->settingOrEnv('smtp.host', 'SMTP_HOST', ''));
        $port = (int) $this->settingOrEnv('smtp.port', 'SMTP_PORT', '587');
        $username = trim($this->settingOrEnv('smtp.username', 'SMTP_USERNAME', ''));
        $password = $this->settingOrEnv('smtp.password', 'SMTP_PASSWORD', '');
        $encryption = strtolower(trim($this->settingOrEnv('smtp.encryption', 'SMTP_ENCRYPTION', 'tls')));
        $fromEmail = trim($this->settingOrEnv('smtp.from_email', 'SMTP_FROM_EMAIL', 'no-reply@wiseplay.local'));
        $fromName = trim($this->settingOrEnv('smtp.from_name', 'SMTP_FROM_NAME', 'WisePlay'));

        if ($host === '' || $port <= 0 || $fromEmail === '') {
            return ['ok' => false, 'message' => 'SMTP nao configurado corretamente.'];
        }

        $connection = $this->openSmtpSocket($host, $port, $encryption);
        if (($connection['ok'] ?? false) !== true || !isset($connection['socket']) || !is_resource($connection['socket'])) {
            return ['ok' => false, 'message' => (string) ($connection['message'] ?? 'Falha ao conectar SMTP.')];
        }

        $socket = $connection['socket'];
        stream_set_timeout($socket, 15);

        $expect = function (array $codes) use ($socket): array {
            $line = '';
            $full = '';
            while (($line = fgets($socket, 515)) !== false) {
                $full .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }

            $code = (int) substr($full, 0, 3);
            if (!in_array($code, $codes, true)) {
                return ['ok' => false, 'response' => trim($full), 'code' => $code];
            }
            return ['ok' => true, 'response' => trim($full), 'code' => $code];
        };

        $send = static function ($socket, string $command): void {
            fwrite($socket, $command . "\r\n");
        };

        $greet = $expect([220]);
        if (($greet['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP greeting invalido.'];
        }

        $send($socket, 'EHLO wiseplay.local');
        $ehlo = $expect([250]);
        if (($ehlo['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'EHLO SMTP falhou.'];
        }

        if ($encryption === 'tls') {
            $send($socket, 'STARTTLS');
            $tls = $expect([220]);
            if (($tls['ok'] ?? false) !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'STARTTLS falhou no SMTP.'];
            }

            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'Nao foi possivel negociar TLS com SMTP.'];
            }

            $send($socket, 'EHLO wiseplay.local');
            $ehloTls = $expect([250]);
            if (($ehloTls['ok'] ?? false) !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'EHLO apos TLS falhou.'];
            }
        }

        if ($username !== '') {
            $send($socket, 'AUTH LOGIN');
            if (($expect([334])['ok'] ?? false) !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'SMTP AUTH LOGIN indisponivel.'];
            }

            $send($socket, base64_encode($username));
            if (($expect([334])['ok'] ?? false) !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'Usuario SMTP rejeitado.'];
            }

            $send($socket, base64_encode($password));
            if (($expect([235])['ok'] ?? false) !== true) {
                fclose($socket);
                return ['ok' => false, 'message' => 'Senha SMTP rejeitada.'];
            }
        }

        $send($socket, 'MAIL FROM:<' . $fromEmail . '>');
        if (($expect([250])['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP MAIL FROM rejeitado.'];
        }

        $send($socket, 'RCPT TO:<' . $toEmail . '>');
        if (($expect([250, 251])['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP RCPT TO rejeitado.'];
        }

        $send($socket, 'DATA');
        if (($expect([354])['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP DATA rejeitado.'];
        }

        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $safeFromName = str_replace(["\r", "\n"], '', $fromName);
        $headers = [
            'From: ' . $safeFromName . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . $safeSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyText . "\r\n.";

        fwrite($socket, $message . "\r\n");
        if (($expect([250])['ok'] ?? false) !== true) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP envio de mensagem falhou.'];
        }

        $send($socket, 'QUIT');
        fclose($socket);

        return ['ok' => true, 'message' => 'Email enviado via SMTP.'];
    }

    private function openSmtpSocket(string $host, int $port, string $encryption): array
    {
        $candidates = $this->smtpConnectionCandidates($host);
        $errors = [];

        foreach ($candidates as $candidateHost) {
            $transportHost = $candidateHost;
            if ($encryption === 'ssl') {
                $transportHost = 'ssl://' . $candidateHost;
            }

            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                $transportHost . ':' . $port,
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT
            );

            if (is_resource($socket)) {
                return ['ok' => true, 'socket' => $socket];
            }

            $reason = trim($errstr) !== '' ? trim($errstr) : ('errno ' . $errno);
            $errors[] = $candidateHost . ':' . $port . ' (' . $reason . ')';
        }

        return [
            'ok' => false,
            'message' => 'Falha ao conectar SMTP. Tentativas: ' . implode(', ', $errors),
        ];
    }

    private function smtpConnectionCandidates(string $host): array
    {
        $normalizedHost = strtolower(trim($host));
        $candidates = [$host];

        // Local development: when using Mailpit, the app might run either inside Docker (mailpit)
        // or directly on host machine (localhost / 127.0.0.1 with published SMTP port).
        $isLocalAlias = in_array($normalizedHost, ['mailpit', 'localhost', '127.0.0.1'], true);
        if ($isLocalAlias) {
            foreach (['mailpit', 'localhost', '127.0.0.1'] as $fallback) {
                if (!in_array($fallback, $candidates, true)) {
                    $candidates[] = $fallback;
                }
            }
        }

        return $candidates;
    }

    private function smtpEnabled(): bool
    {
        $dbValue = $this->rawSetting('smtp.enabled');
        if ($dbValue !== null && $dbValue !== '') {
            if ($this->isTruthy($dbValue)) {
                return true;
            }

            $isDev = strtolower((string) config('app.env', 'production')) === 'development';
            $devForce = $this->isTruthy((string) env('SMTP_DEV_FORCE', '0'));
            $envHost = trim((string) env('SMTP_HOST', ''));
            if ($isDev && $devForce && $envHost !== '') {
                return true;
            }

            return false;
        }

        return $this->isTruthy((string) env('SMTP_ENABLED', '0'));
    }

    private function settingOrEnv(string $settingKey, string $envKey, string $default): string
    {
        $dbValue = $this->rawSetting($settingKey);
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }

        return (string) env($envKey, $default);
    }

    private function rawSetting(string $key): ?string
    {
        $row = $this->settings->findByKey($key);
        if (!$row) {
            return null;
        }

        return isset($row['value_text']) ? (string) $row['value_text'] : null;
    }

    private function logDelivery(string $toEmail, string $subject, string $bodyText): void
    {
        $entry = [
            'at' => date('c'),
            'to' => $toEmail,
            'subject' => $subject,
            'body' => $bodyText,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return;
        }

        @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line . PHP_EOL, FILE_APPEND);
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
