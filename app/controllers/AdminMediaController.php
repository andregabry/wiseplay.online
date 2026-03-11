<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\Media;
use App\models\Setting;
use App\models\Title;
use App\services\OneDriveGraphService;
use App\services\PublicNotificationService;
use App\services\OneDriveService;
use App\services\UploadDebugLogger;

final class AdminMediaController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN', 'EDITOR'];

    public function index(): void
    {
        $this->guard();

        $model = new Media();
        $titleModel = new Title();
        $settingModel = new Setting();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'title_id' => (int) ($_GET['title_id'] ?? 0),
            'source_type' => trim((string) ($_GET['source_type'] ?? '')),
            'is_published' => trim((string) ($_GET['is_published'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;

        try {
            $result = $model->paginate($filters, $page, $perPage);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel carregar midias.');
            $result = ['items' => [], 'total' => 0];
        }

        $total = (int) $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
            try {
                $result = $model->paginate($filters, $page, $perPage);
            } catch (\Throwable) {
                $result = ['items' => [], 'total' => $total];
            }
        }

        $titles = $titleModel->paginate([], 1, 1000);

        $graphService = new OneDriveGraphService($settingModel);
        $oneDriveConnected = $graphService->isConnected();
        $uploadRuntime = $graphService->uploadRuntimeConfig();

        $quotaCard = null;
        $quotaRemainingBytes = 0;
        try {
            $quotaService = new OneDriveService();
            $quotaResult = $quotaService->fetchDriveQuota(false);
            $quotaCard = $quotaService->presentQuota($quotaResult);
            if (is_array($quotaResult['quota'] ?? null)) {
                $quotaRemainingBytes = (int) ($quotaResult['quota']['remaining'] ?? 0);
            }
        } catch (\Throwable) {
            $quotaCard = null;
            $quotaRemainingBytes = 0;
        }

        $this->render('admin/media', [
            'title' => 'Media | WisePlay Admin',
            'pageTitle' => 'Media',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'titleOptions' => $titles['items'],
            'sourceTypes' => $model->sourceTypes(),
            'uploadStatuses' => Media::UPLOAD_STATUSES,
            'oneDriveConnected' => $oneDriveConnected,
            'oneDriveRootFolder' => $graphService->rootFolder(),
            'oneDriveChunkBytes' => $graphService->chunkSizeBytes(),
            'oneDriveMaxVideoBytes' => $graphService->maxVideoBytes(),
            'oneDriveUploadMaxRetries' => (int) ($uploadRuntime['max_retries'] ?? 3),
            'oneDriveUploadRetryBackoffMs' => (int) ($uploadRuntime['retry_backoff_ms'] ?? 500),
            'oneDriveUploadDebug' => $graphService->debugEnabled(),
            'oneDriveQuota' => $quotaCard,
            'oneDriveQuotaRemainingBytes' => $quotaRemainingBytes,
            'canManageMedia' => in_array((string) (Auth::user()['role'] ?? ''), self::MANAGE_ROLES, true),
        ]);
    }

    public function create(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $payload = $this->readPayload();
        if ((string) ($payload['source_type'] ?? '') === '') {
            $payload['source_type'] = 'ONEDRIVE';
        }
        if ((string) ($payload['upload_status'] ?? '') === '') {
            $payload['upload_status'] = 'PENDING';
        }
        $title = (new Title())->findById($payload['title_id']);
        $payload = $this->normalizePayloadForTitle($payload, $title);
        $errors = $this->validatePayload($payload, $title, false, null);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        $mediaModel = new Media();
        if ($mediaModel->existsDuplicate($payload, null)) {
            Response::json([
                'ok' => false,
                'message' => 'Ja existe uma midia para esse titulo/temporada/episodio/fonte.',
                'errors' => ['item_label' => 'Duplicado para a mesma sequencia e source_type.'],
            ], 422);
        }

        $uploadMeta = null;
        if ($payload['source_type'] === 'ONEDRIVE') {
            if ($this->hasUploadedFile('video_file')) {
                $upload = $this->uploadToOneDrive($payload, $title, true);
                if (($upload['ok'] ?? false) !== true) {
                    Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload OneDrive.')], 422);
                }

                $uploadMeta = $upload;
                $payload = $this->applyOneDriveUploadToPayload($payload, $upload);
            } else {
                $hydrate = $this->hydrateOneDrivePayloadFromExternalId($payload);
                if (($hydrate['ok'] ?? false) !== true) {
                    Response::json(['ok' => false, 'message' => (string) ($hydrate['message'] ?? 'Falha ao validar item OneDrive finalizado.')], 422);
                }
                $payload = is_array($hydrate['payload'] ?? null) ? $hydrate['payload'] : $payload;
            }
        } else {
            $payload['upload_status'] = 'READY';
        }

        try {
            $id = $mediaModel->create($payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar midia.'], 500);
        }

        try {
            $createdMedia = $mediaModel->findById($id);
            if (is_array($createdMedia) && is_array($title)) {
                (new PublicNotificationService())->notifyMediaCreated($title, $createdMedia);
            }
        } catch (\Throwable) {
            // notification must not block admin media flow
        }

        $this->audit('media.create', 'media', (string) $id, [
            'title_id' => $payload['title_id'],
            'item_label' => $payload['item_label'],
            'source_type' => $payload['source_type'],
            'upload_status' => $payload['upload_status'],
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Midia criada com sucesso.',
            'media_id' => $id,
            'upload' => $uploadMeta ? [
                'status' => 'done',
                'chunks_total' => (int) ($uploadMeta['video_chunks_total'] ?? 0),
                'chunks_uploaded' => (int) ($uploadMeta['video_chunks_uploaded'] ?? 0),
                'source_type' => 'ONEDRIVE',
                'external_id' => (string) ($payload['external_id'] ?? ''),
                'source_path' => (string) ($payload['source_path'] ?? ''),
                'stream_ref' => (string) ($payload['stream_ref'] ?? ''),
            ] : null,
        ]);
    }

    public function update(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $mediaModel = new Media();
        $existing = $mediaModel->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Midia nao encontrada.'], 404);
        }

        $payload = $this->readPayload();
        $payload = $this->mergeExistingPayload($payload, $existing);

        $title = (new Title())->findById($payload['title_id']);
        $payload = $this->normalizePayloadForTitle($payload, $title);
        $errors = $this->validatePayload($payload, $title, true, $existing);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        if ($mediaModel->existsDuplicate($payload, $id)) {
            Response::json([
                'ok' => false,
                'message' => 'Ja existe outra midia para esse titulo/temporada/episodio/fonte.',
                'errors' => ['item_label' => 'Duplicado para a mesma sequencia e source_type.'],
            ], 422);
        }

        $uploadMeta = null;
        if ($payload['source_type'] === 'ONEDRIVE' && $this->hasUploadedFile('video_file')) {
            $upload = $this->uploadToOneDrive($payload, $title, false);
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload OneDrive.')], 422);
            }

            $uploadMeta = $upload;
            $payload = $this->applyOneDriveUploadToPayload($payload, $upload);
        } elseif ($payload['source_type'] === 'ONEDRIVE' && trim((string) ($payload['external_id'] ?? '')) !== '') {
            $hydrate = $this->hydrateOneDrivePayloadFromExternalId($payload);
            if (($hydrate['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($hydrate['message'] ?? 'Falha ao validar item OneDrive finalizado.')], 422);
            }
            $payload = is_array($hydrate['payload'] ?? null) ? $hydrate['payload'] : $payload;
        }

        try {
            $mediaModel->update($id, $payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar midia.'], 500);
        }

        $this->audit('media.update', 'media', (string) $id, [
            'title_id' => $payload['title_id'],
            'item_label' => $payload['item_label'],
            'source_type' => $payload['source_type'],
            'upload_status' => $payload['upload_status'],
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Midia atualizada com sucesso.',
            'upload' => $uploadMeta ? [
                'status' => 'done',
                'chunks_total' => (int) ($uploadMeta['video_chunks_total'] ?? 0),
                'chunks_uploaded' => (int) ($uploadMeta['video_chunks_uploaded'] ?? 0),
                'source_type' => 'ONEDRIVE',
                'external_id' => (string) ($payload['external_id'] ?? ''),
                'source_path' => (string) ($payload['source_path'] ?? ''),
                'stream_ref' => (string) ($payload['stream_ref'] ?? ''),
            ] : null,
        ]);
    }

    public function replaceSource(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $mediaModel = new Media();
        $existing = $mediaModel->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Midia nao encontrada.'], 404);
        }

        $payload = $this->mergeExistingPayload($this->readPayload(), $existing);
        $payload['source_type'] = 'ONEDRIVE';

        $title = (new Title())->findById((int) $payload['title_id']);
        $payload = $this->normalizePayloadForTitle($payload, $title);

        $upload = null;
        if ($this->hasUploadedFile('video_file')) {
            $upload = $this->uploadToOneDrive($payload, $title, false);
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no replace de video.')], 422);
            }
            $payload = $this->applyOneDriveUploadToPayload($payload, $upload);
        } elseif (trim((string) ($payload['external_id'] ?? '')) !== '') {
            $hydrate = $this->hydrateOneDrivePayloadFromExternalId($payload);
            if (($hydrate['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($hydrate['message'] ?? 'Falha ao validar item OneDrive finalizado.')], 422);
            }
            $payload = is_array($hydrate['payload'] ?? null) ? $hydrate['payload'] : $payload;
        } else {
            Response::json(['ok' => false, 'message' => 'Selecione um novo video para substituir.'], 422);
        }

        try {
            $mediaModel->update($id, $payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao substituir video.'], 500);
        }

        $this->audit('media.replace_source', 'media', (string) $id, [
            'external_id' => $payload['external_id'],
            'source_path' => $payload['source_path'],
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Arquivo de video substituido com sucesso.',
            'upload' => [
                'status' => 'done',
                'chunks_total' => (int) (($upload['video_chunks_total'] ?? 0)),
                'chunks_uploaded' => (int) (($upload['video_chunks_uploaded'] ?? 0)),
                'source_type' => 'ONEDRIVE',
                'external_id' => (string) ($payload['external_id'] ?? ''),
                'source_path' => (string) ($payload['source_path'] ?? ''),
                'stream_ref' => (string) ($payload['stream_ref'] ?? ''),
            ],
        ]);
    }

    public function startUploadSession(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $titleId = (int) ($_POST['title_id'] ?? 0);
        $seasonNumber = (int) ($_POST['season_number'] ?? 0);
        $episodeNumber = (int) ($_POST['episode_number'] ?? 0);
        $fileName = trim((string) ($_POST['file_name'] ?? ''));
        $fileSize = (int) ($_POST['file_size'] ?? 0);
        $mimeType = trim((string) ($_POST['mime_type'] ?? 'application/octet-stream'));

        if ($titleId <= 0 || $fileName === '' || $fileSize <= 0) {
            Response::json(['ok' => false, 'message' => 'Dados insuficientes para iniciar upload session.'], 422);
        }

        $title = (new Title())->findById($titleId);
        if (!$title) {
            Response::json(['ok' => false, 'message' => 'Titulo invalido.'], 422);
        }

        if (($title['type'] === 'SERIES' || $title['type'] === 'COURSE') && $seasonNumber < 1) {
            Response::json(['ok' => false, 'message' => 'Modulo/temporada obrigatorio para series e cursos.'], 422);
        }

        if (($title['type'] === 'SERIES' || $title['type'] === 'COURSE') && $episodeNumber < 1) {
            Response::json(['ok' => false, 'message' => 'Licao/episodio obrigatorio para series e cursos.'], 422);
        }

        if ($title['type'] === 'MOVIE') {
            $seasonNumber = 0;
            $episodeNumber = 0;
        }

        $quotaService = new OneDriveService();
        $quotaResult = $quotaService->fetchDriveQuota(false);
        if (($quotaResult['ok'] ?? false) === true && is_array($quotaResult['quota'] ?? null)) {
            $quota = $quotaResult['quota'];
            $remaining = (int) ($quota['remaining'] ?? 0);
            $reservedBytes = 10 * 1024 * 1024; // reserva minima para overhead/futuros arquivos
            if ($remaining > 0 && $remaining < ($fileSize + $reservedBytes)) {
                Response::json([
                    'ok' => false,
                    'message' => 'Espaco insuficiente no OneDrive para este upload. Atualize quota ou libere espaco.',
                    'errors' => [
                        'video_file' => 'Espaco restante: ' . $this->humanBytes($remaining) . '. Arquivo selecionado: ' . $this->humanBytes($fileSize) . '.',
                    ],
                    'quota' => $quotaService->presentQuota($quotaResult),
                ], 422);
            }
        }

        $graph = new OneDriveGraphService();
        $session = $graph->createVideoUploadSession([
            'title_type' => (string) ($title['type'] ?? 'MOVIE'),
            'title_slug' => (string) ($title['slug'] ?? ''),
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
        ], [
            'name' => $fileName,
            'size' => $fileSize,
            'type' => $mimeType,
        ]);

        if (($session['ok'] ?? false) !== true) {
            Response::json(['ok' => false, 'message' => (string) ($session['message'] ?? 'Falha ao iniciar upload session OneDrive.')], 422);
        }

        $this->audit('media.upload_session.start', 'media', null, [
            'title_id' => $titleId,
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
            'file_name' => $fileName,
            'file_size' => $fileSize,
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Upload session iniciada.',
            'session' => [
                'upload_url' => (string) ($session['upload_url'] ?? ''),
                'expires_at' => (string) ($session['expires_at'] ?? ''),
                'chunk_bytes' => (int) ($session['chunk_bytes'] ?? (10 * 1024 * 1024)),
                'max_video_bytes' => (int) ($session['max_video_bytes'] ?? (10 * 1024 * 1024 * 1024)),
                'max_retries' => (int) ($session['max_retries'] ?? 3),
                'retry_backoff_ms' => (int) ($session['retry_backoff_ms'] ?? 500),
                'destination_path' => (string) ($session['destination_path'] ?? ''),
                'file_name' => (string) ($session['file_name'] ?? ''),
            ],
        ]);
    }

    public function logUploadChunk(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $logger = new UploadDebugLogger(new Setting());
        if (!$logger->enabled()) {
            Response::json(['ok' => true]);
        }

        $logger->log('client.chunk.telemetry', [
            'phase' => trim((string) ($_POST['phase'] ?? '')),
            'chunk_index' => (int) ($_POST['chunk_index'] ?? 0),
            'chunk_total' => (int) ($_POST['chunk_total'] ?? 0),
            'start' => (int) ($_POST['start'] ?? 0),
            'end' => (int) ($_POST['end'] ?? 0),
            'content_length' => (int) ($_POST['content_length'] ?? 0),
            'content_range' => trim((string) ($_POST['content_range'] ?? '')),
            'status' => (int) ($_POST['status'] ?? 0),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'response_excerpt' => trim((string) ($_POST['response_excerpt'] ?? '')),
        ]);

        Response::json(['ok' => true]);
    }

    public function delete(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $deleteSource = (int) ($_POST['delete_source'] ?? 0) === 1;

        $mediaModel = new Media();
        $media = $mediaModel->findById($id);
        if (!$media) {
            Response::json(['ok' => false, 'message' => 'Midia nao encontrada.'], 404);
        }

        if ($deleteSource && strtoupper((string) ($media['source_type'] ?? '')) === 'ONEDRIVE') {
            $deleteResult = $this->deleteOneDriveAssets($media);
            if (($deleteResult['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($deleteResult['message'] ?? 'Falha ao excluir arquivos OneDrive.')], 422);
            }
        }

        try {
            $mediaModel->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao excluir midia.'], 500);
        }

        $this->audit('media.delete', 'media', (string) $id, [
            'item_label' => $media['item_label'],
            'delete_source' => $deleteSource,
            'source_type' => $media['source_type'] ?? null,
        ]);

        Response::json(['ok' => true, 'message' => $deleteSource ? 'Midia e arquivo(s) OneDrive excluidos.' : 'Midia excluida com sucesso.']);
    }

    private function readPayload(): array
    {
        return [
            'title_id' => (int) ($_POST['title_id'] ?? 0),
            'season_number' => (int) ($_POST['season_number'] ?? 0),
            'episode_number' => (int) ($_POST['episode_number'] ?? 0),
            'item_label' => trim((string) ($_POST['item_label'] ?? '')),
            'duration_minutes' => (int) ($_POST['duration_minutes'] ?? 0),
            'source_type' => strtoupper(trim((string) ($_POST['source_type'] ?? ''))),
            'source_path' => trim((string) ($_POST['source_path'] ?? '')),
            'external_id' => trim((string) ($_POST['external_id'] ?? '')),
            'stream_ref' => trim((string) ($_POST['stream_ref'] ?? '')),
            'stream_url' => trim((string) ($_POST['stream_url'] ?? '')),
            'stream_url_expires_at' => trim((string) ($_POST['stream_url_expires_at'] ?? '')),
            'download_url' => trim((string) ($_POST['download_url'] ?? '')),
            'drive_id' => trim((string) ($_POST['drive_id'] ?? '')),
            'parent_item_id' => trim((string) ($_POST['parent_item_id'] ?? '')),
            'mime_type' => trim((string) ($_POST['mime_type'] ?? '')),
            'file_size_bytes' => (int) ($_POST['file_size_bytes'] ?? 0),
            'upload_status' => strtoupper(trim((string) ($_POST['upload_status'] ?? ''))),
            'subtitles' => trim((string) ($_POST['subtitles'] ?? '')),
            'subtitles_json' => trim((string) ($_POST['subtitles_json'] ?? '')),
            'audio_lang' => trim((string) ($_POST['audio_lang'] ?? '')),
            'is_published' => (int) ($_POST['is_published'] ?? 1) === 1 ? 1 : 0,
            'thumbnail_url' => trim((string) ($_POST['thumbnail_url'] ?? '')),
            'poster_url' => trim((string) ($_POST['poster_url'] ?? '')),
            'backdrop_url' => trim((string) ($_POST['backdrop_url'] ?? '')),
            'last_source_sync_at' => trim((string) ($_POST['last_source_sync_at'] ?? '')),
        ];
    }

    private function validatePayload(array $payload, ?array $title, bool $isUpdate, ?array $existing): array
    {
        $errors = [];

        if (!$title) {
            $errors['title_id'] = 'Titulo invalido.';
            return $errors;
        }

        if (!Validator::required($payload['item_label']) || !Validator::minLength($payload['item_label'], 2)) {
            $errors['item_label'] = 'Titulo do item e obrigatorio.';
        }

        if ($payload['duration_minutes'] !== 0 && !Validator::integer($payload['duration_minutes'], 1, 1200)) {
            $errors['duration_minutes'] = 'Duracao invalida.';
        }

        if (!Validator::inArray($payload['source_type'], (new Media())->sourceTypes())) {
            $errors['source_type'] = 'Tipo de fonte invalido.';
        }

        if ($payload['source_type'] === 'URL' && $payload['source_path'] !== '' && !Validator::url($payload['source_path'])) {
            $errors['source_path'] = 'URL da fonte invalida.';
        }
        if ($payload['source_type'] !== 'ONEDRIVE' && trim((string) $payload['source_path']) === '') {
            $errors['source_path'] = 'Informe o source_path para fontes URL/LOCAL.';
        }

        if (($title['type'] === 'SERIES' || $title['type'] === 'COURSE')) {
            if (!Validator::integer($payload['season_number'], 1, 99)) {
                $errors['season_number'] = 'Modulo/temporada obrigatorio para series e cursos.';
            }
            if (!Validator::integer($payload['episode_number'], 1, 999)) {
                $errors['episode_number'] = 'Licao/episodio obrigatorio para series e cursos.';
            }
        } else {
            $payload['season_number'] = 0;
            $payload['episode_number'] = 0;
        }

        if (!Validator::inArray($payload['upload_status'], Media::UPLOAD_STATUSES)) {
            $errors['upload_status'] = 'Status de upload invalido.';
        }

        if ($payload['source_type'] === 'ONEDRIVE') {
            $hasVideo = $this->hasUploadedFile('video_file');
            $videoUploadError = $this->uploadErrorCode('video_file');
            $existingExternalId = trim((string) ($existing['external_id'] ?? ''));
            $incomingExternalId = trim((string) ($payload['external_id'] ?? ''));

            if ($videoUploadError !== null && $videoUploadError !== UPLOAD_ERR_NO_FILE) {
                $errors['video_file'] = $this->uploadErrorMessage($videoUploadError, 'video');
            }

            if (!$isUpdate && !$hasVideo) {
                if ($incomingExternalId === '' && !isset($errors['video_file'])) {
                    $errors['video_file'] = 'Arquivo de video e obrigatorio para OneDrive.';
                }
            }
            if ($isUpdate && !$hasVideo && $existingExternalId === '' && $incomingExternalId === '') {
                if (!isset($errors['video_file'])) {
                    $errors['video_file'] = 'Envie um video OneDrive para concluir esta midia.';
                }
            }

            $graphService = new OneDriveGraphService();
            if (!$graphService->isConnected()) {
                $errors['source_type'] = 'OneDrive nao conectado. Configure e conecte em Settings.';
            }
        }

        return $errors;
    }

    private function uploadToOneDrive(array $payload, ?array $title, bool $isCreate): array
    {
        if (!$title) {
            return ['ok' => false, 'message' => 'Titulo invalido para upload OneDrive.'];
        }

        $graphService = new OneDriveGraphService();
        if (!$graphService->isConnected()) {
            return ['ok' => false, 'message' => 'OneDrive nao conectado. Abra Settings e clique em Connect.'];
        }

        if ($isCreate && !$this->hasUploadedFile('video_file')) {
            return ['ok' => false, 'message' => 'Video e obrigatorio para criar midia OneDrive.'];
        }

        return $graphService->uploadMediaPackage([
            'title_type' => (string) ($title['type'] ?? 'MOVIE'),
            'title_slug' => (string) ($title['slug'] ?? ''),
            'season_number' => (int) ($payload['season_number'] ?? 0),
            'episode_number' => (int) ($payload['episode_number'] ?? 0),
            'item_label' => (string) ($payload['item_label'] ?? ''),
        ], $_FILES);
    }

    private function applyOneDriveUploadToPayload(array $payload, array $upload): array
    {
        $video = is_array($upload['video'] ?? null) ? $upload['video'] : [];
        $subtitles = is_array($upload['subtitles'] ?? null) ? $upload['subtitles'] : [];
        $thumbnail = is_array($upload['thumbnail'] ?? null) ? $upload['thumbnail'] : null;
        $poster = is_array($upload['poster'] ?? null) ? $upload['poster'] : null;
        $backdrop = is_array($upload['backdrop'] ?? null) ? $upload['backdrop'] : null;

        $payload['source_type'] = 'ONEDRIVE';
        $payload['external_id'] = (string) ($video['item_id'] ?? ($payload['external_id'] ?? ''));
        $payload['source_path'] = (string) ($video['source_path'] ?? ($payload['source_path'] ?? ''));
        $payload['stream_ref'] = $payload['external_id'] !== '' ? 'onedrive:item:' . $payload['external_id'] : (string) ($payload['stream_ref'] ?? '');
        $payload['stream_url'] = (string) ($video['downloadUrl'] ?? ($payload['stream_url'] ?? ''));
        $payload['download_url'] = (string) ($video['downloadUrl'] ?? ($payload['download_url'] ?? ''));
        $payload['drive_id'] = (string) ($video['drive_id'] ?? ($payload['drive_id'] ?? ''));
        $payload['parent_item_id'] = (string) ($video['parent_item_id'] ?? ($payload['parent_item_id'] ?? ''));
        $payload['mime_type'] = (string) ($video['mimeType'] ?? ($payload['mime_type'] ?? ''));
        $payload['file_size_bytes'] = (int) ($video['size'] ?? ($payload['file_size_bytes'] ?? 0));
        $payload['upload_status'] = 'READY';
        $payload['last_source_sync_at'] = date('Y-m-d H:i:s');
        $payload['stream_url_expires_at'] = $payload['stream_url'] !== '' ? date('Y-m-d H:i:s', time() + 55 * 60) : null;

        if ($subtitles !== []) {
            $payload['subtitles_json'] = json_encode($subtitles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
            $languages = [];
            foreach ($subtitles as $sub) {
                $lang = strtolower(trim((string) ($sub['language'] ?? '')));
                if ($lang !== '') {
                    $languages[] = $lang;
                }
            }
            $payload['subtitles'] = $languages !== [] ? implode(',', array_unique($languages)) : ($payload['subtitles'] ?? '');
        }

        if (is_array($thumbnail)) {
            $payload['thumbnail_url'] = (string) (($thumbnail['downloadUrl'] ?? '') !== '' ? $thumbnail['downloadUrl'] : ($thumbnail['webUrl'] ?? ''));
        }
        if (is_array($poster)) {
            $payload['poster_url'] = (string) (($poster['downloadUrl'] ?? '') !== '' ? $poster['downloadUrl'] : ($poster['webUrl'] ?? ''));
        }
        if (is_array($backdrop)) {
            $payload['backdrop_url'] = (string) (($backdrop['downloadUrl'] ?? '') !== '' ? $backdrop['downloadUrl'] : ($backdrop['webUrl'] ?? ''));
        }

        return $payload;
    }

    private function mergeExistingPayload(array $payload, array $existing): array
    {
        $mergeFields = [
            'source_type',
            'source_path',
            'external_id',
            'stream_ref',
            'stream_url',
            'stream_url_expires_at',
            'download_url',
            'drive_id',
            'parent_item_id',
            'mime_type',
            'file_size_bytes',
            'upload_status',
            'subtitles_json',
            'thumbnail_url',
            'poster_url',
            'backdrop_url',
            'last_source_sync_at',
        ];

        foreach ($mergeFields as $field) {
            if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null || $payload[$field] === 0) {
                if (array_key_exists($field, $existing)) {
                    $payload[$field] = $existing[$field];
                }
            }
        }

        if ((string) ($payload['source_type'] ?? '') === 'ONEDRIVE' && (string) ($payload['upload_status'] ?? '') === '') {
            $payload['upload_status'] = 'READY';
        }

        return $payload;
    }

    private function deleteOneDriveAssets(array $media): array
    {
        $graphService = new OneDriveGraphService();
        if (!$graphService->isConnected()) {
            return ['ok' => false, 'message' => 'OneDrive desconectado; nao foi possivel remover arquivos remotos.'];
        }

        $itemIds = [];
        $externalId = trim((string) ($media['external_id'] ?? ''));
        if ($externalId !== '') {
            $itemIds[] = $externalId;
        }

        $subtitlesJson = trim((string) ($media['subtitles_json'] ?? ''));
        if ($subtitlesJson !== '') {
            $decoded = json_decode($subtitlesJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_array($item) && isset($item['item_id']) && is_string($item['item_id'])) {
                        $id = trim($item['item_id']);
                        if ($id !== '') {
                            $itemIds[] = $id;
                        }
                    }
                }
            }
        }

        $itemIds = array_values(array_unique($itemIds));
        foreach ($itemIds as $itemId) {
            $result = $graphService->deleteItemById($itemId);
            if (($result['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => 'Falha ao excluir item OneDrive: ' . $itemId];
            }
        }

        return ['ok' => true];
    }

    private function hasUploadedFile(string $field): bool
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return false;
        }

        $error = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
        return $error === UPLOAD_ERR_OK;
    }

    private function uploadErrorCode(string $field): ?int
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }
        return (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    private function hasAnyOneDriveUploadFiles(): bool
    {
        if ($this->hasUploadedFile('video_file') || $this->hasUploadedFile('thumbnail_file') || $this->hasUploadedFile('poster_file') || $this->hasUploadedFile('backdrop_file')) {
            return true;
        }

        if (!isset($_FILES['subtitle_files']) || !is_array($_FILES['subtitle_files'])) {
            return false;
        }

        if (!isset($_FILES['subtitle_files']['error']) || !is_array($_FILES['subtitle_files']['error'])) {
            return false;
        }

        foreach ($_FILES['subtitle_files']['error'] as $error) {
            if ((int) $error === UPLOAD_ERR_OK) {
                return true;
            }
        }

        return false;
    }

    private function normalizePayloadForTitle(array $payload, ?array $title): array
    {
        if (!$title) {
            return $payload;
        }

        if ((string) ($title['type'] ?? '') === 'MOVIE') {
            $payload['season_number'] = 0;
            $payload['episode_number'] = 0;
        } else {
            $payload['season_number'] = max(1, (int) ($payload['season_number'] ?? 0));
            $payload['episode_number'] = max(1, (int) ($payload['episode_number'] ?? 0));
        }

        return $payload;
    }

    private function uploadErrorMessage(int $code, string $label = 'arquivo'): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload de ' . $label . ' excedeu o limite configurado no servidor/formulario.',
            UPLOAD_ERR_PARTIAL => 'Upload de ' . $label . ' foi recebido parcialmente. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum ' . $label . ' foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario de upload indisponivel no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar ' . $label . ' no servidor.',
            UPLOAD_ERR_EXTENSION => 'Upload de ' . $label . ' bloqueado por extensao do PHP.',
            default => 'Falha no upload de ' . $label . '.',
        };
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        $decimals = $unit >= 2 ? 2 : 0;
        return number_format($value, $decimals, '.', '') . ' ' . $units[$unit];
    }

    private function hydrateOneDrivePayloadFromExternalId(array $payload): array
    {
        $externalId = trim((string) ($payload['external_id'] ?? ''));
        if ($externalId === '') {
            return ['ok' => false, 'message' => 'External ID OneDrive ausente apos upload.'];
        }

        $graphService = new OneDriveGraphService();
        $fetched = $graphService->fetchDriveItemById($externalId);
        if (($fetched['ok'] ?? false) !== true || !is_array($fetched['item'] ?? null)) {
            return ['ok' => false, 'message' => (string) ($fetched['message'] ?? 'Falha ao validar item OneDrive por external_id.')];
        }

        $item = $fetched['item'];
        $payload['source_type'] = 'ONEDRIVE';
        $payload['external_id'] = (string) ($item['item_id'] ?? $externalId);
        $payload['source_path'] = (string) ($item['source_path'] ?? ($payload['source_path'] ?? ''));
        $payload['stream_ref'] = 'onedrive:item:' . $payload['external_id'];
        $payload['stream_url'] = (string) ($item['downloadUrl'] ?? ($payload['stream_url'] ?? ''));
        $payload['download_url'] = (string) ($item['downloadUrl'] ?? ($payload['download_url'] ?? ''));
        $payload['drive_id'] = (string) ($item['drive_id'] ?? ($payload['drive_id'] ?? ''));
        $payload['parent_item_id'] = (string) ($item['parent_item_id'] ?? ($payload['parent_item_id'] ?? ''));
        $payload['mime_type'] = (string) ($item['mimeType'] ?? ($payload['mime_type'] ?? ''));
        $payload['file_size_bytes'] = (int) ($item['size'] ?? ($payload['file_size_bytes'] ?? 0));
        $payload['upload_status'] = 'READY';
        $payload['last_source_sync_at'] = date('Y-m-d H:i:s');
        $payload['stream_url_expires_at'] = $payload['stream_url'] !== '' ? date('Y-m-d H:i:s', time() + 55 * 60) : null;

        return ['ok' => true, 'payload' => $payload];
    }
}
