<?php

declare(strict_types=1);

namespace App\services;

final class UploadService
{
    public function validateVideoUpload(string $mimeType, int $sizeBytes): array
    {
        $allowed = ['video/mp4', 'video/webm', 'video/x-matroska'];
        $max = 2 * 1024 * 1024 * 1024; // 2GB draft limit

        if (!in_array($mimeType, $allowed, true)) {
            return ['ok' => false, 'message' => 'Tipo de arquivo nao permitido.'];
        }

        if ($sizeBytes <= 0 || $sizeBytes > $max) {
            return ['ok' => false, 'message' => 'Arquivo excede limite de 2GB.'];
        }

        return ['ok' => true, 'message' => 'Arquivo valido.'];
    }

    public function validateImageUpload(string $mimeType, int $sizeBytes): array
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $max = 4 * 1024 * 1024; // 4MB

        if (!in_array($mimeType, $allowed, true)) {
            return ['ok' => false, 'message' => 'Imagem deve ser JPG, PNG ou WEBP.'];
        }

        if ($sizeBytes <= 0 || $sizeBytes > $max) {
            return ['ok' => false, 'message' => 'Imagem excede limite de 4MB.'];
        }

        return ['ok' => true, 'message' => 'Imagem valida.'];
    }

    public function storeImage(array $file, string $prefix = 'img'): array
    {
        if (!isset($file['tmp_name'], $file['name'], $file['type'], $file['size'], $file['error'])) {
            return ['ok' => false, 'message' => 'Upload invalido.'];
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Falha no upload do arquivo.'];
        }

        $validation = $this->validateImageUpload((string) $file['type'], (int) $file['size']);
        if (($validation['ok'] ?? false) !== true) {
            return $validation;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['ok' => false, 'message' => 'Extensao de imagem nao permitida.'];
        }

        $targetDir = BASE_PATH . '/public/assets/uploads/images';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['ok' => false, 'message' => 'Nao foi possivel preparar pasta de upload.'];
        }

        $filename = sprintf(
            '%s_%s_%s.%s',
            preg_replace('/[^a-z0-9_]+/i', '_', $prefix),
            date('YmdHis'),
            bin2hex(random_bytes(4)),
            $ext
        );

        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar a imagem.'];
        }

        return [
            'ok' => true,
            'message' => 'Imagem enviada com sucesso.',
            'path' => '/assets/uploads/images/' . $filename,
        ];
    }
}
