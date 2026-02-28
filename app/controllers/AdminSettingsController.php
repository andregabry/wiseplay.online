<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Response;
use App\core\Validator;
use App\models\Setting;
use App\services\OneDriveService;

final class AdminSettingsController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN'];

    public function index(): void
    {
        $this->guard();

        $model = new Setting();
        $oneDriveService = new OneDriveService();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;

        try {
            $result = $model->paginate($filters, $page, $perPage);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel carregar configuracoes.');
            $result = ['items' => [], 'total' => 0];
        }

        $onedrive = [
            'client_id' => $model->value('onedrive.client_id', ''),
            'client_secret' => $model->value('onedrive.client_secret', ''),
            'redirect_uri' => $model->value('onedrive.redirect_uri', url('/admin/settings/onedrive/callback')),
            'authority' => $model->value('onedrive.authority', 'consumers'),
            'scopes' => $model->value('onedrive.scopes', 'openid offline_access profile User.Read Files.ReadWrite'),
            'root_folder' => $model->value('onedrive.root_folder', 'WisePlay'),
            'upload_debug' => $model->value('onedrive.upload_debug', env('ONEDRIVE_UPLOAD_DEBUG', '0') ?? '0'),
            'upload_chunk_mb' => $model->value('onedrive.upload_chunk_mb', env('ONEDRIVE_UPLOAD_CHUNK_MB', '10') ?? '10'),
            'upload_max_video_gb' => $model->value('onedrive.upload_max_video_gb', env('ONEDRIVE_UPLOAD_MAX_VIDEO_GB', '10') ?? '10'),
            'upload_max_retries' => $model->value('onedrive.upload_max_retries', env('ONEDRIVE_UPLOAD_MAX_RETRIES', '3') ?? '3'),
            'upload_retry_backoff_ms' => $model->value('onedrive.upload_retry_backoff_ms', env('ONEDRIVE_UPLOAD_RETRY_BACKOFF_MS', '500') ?? '500'),
            'last_connection_status' => $model->value('onedrive.last_connection_status', 'Not connected'),
            'last_connected_at' => $model->value('onedrive.last_connected_at', ''),
            'last_upload_at' => $model->value('onedrive.last_upload_at', ''),
            'access_token_expires_at' => $model->value('onedrive.access_token_expires_at', ''),
        ];

        $quotaResult = $oneDriveService->fetchDriveQuota(false);
        $quotaCard = $oneDriveService->presentQuota($quotaResult);
        $canManageSettings = in_array((string) (($_SESSION['auth_user']['role'] ?? '')), self::MANAGE_ROLES, true);

        $this->render('admin/settings', [
            'title' => 'Settings | WisePlay Admin',
            'pageTitle' => 'Settings',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int) $result['total'],
                'lastPage' => max(1, (int) ceil(((int) $result['total']) / $perPage)),
            ],
            'categories' => $model->categories(),
            'onedrive' => $onedrive,
            'oneDriveQuota' => $quotaCard,
            'canManageSettings' => $canManageSettings,
        ]);
    }

    public function create(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $payload = $this->readPayload();
        $model = new Setting();

        $errors = $this->validatePayload($payload, $model, true);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        try {
            $id = $model->create($payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar configuracao.'], 500);
        }

        $this->audit('setting.create', 'setting', (string) $id, [
            'category' => $payload['category'],
            'key_name' => $payload['key_name'],
            'is_secret' => $payload['is_secret'],
        ]);

        Response::json(['ok' => true, 'message' => 'Configuracao criada com sucesso.', 'setting_id' => $id]);
    }

    public function update(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new Setting();
        $existing = $model->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Configuracao nao encontrada.'], 404);
        }

        $payload = $this->readPayload();
        $payload['id'] = $id;

        $errors = $this->validatePayload($payload, $model, false);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        try {
            $model->update($id, $payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar configuracao.'], 500);
        }

        $this->audit('setting.update', 'setting', (string) $id, [
            'category' => $payload['category'],
            'key_name' => $payload['key_name'],
            'is_secret' => $payload['is_secret'],
        ]);

        Response::json(['ok' => true, 'message' => 'Configuracao atualizada com sucesso.']);
    }

    public function delete(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new Setting();
        $setting = $model->findById($id);
        if (!$setting) {
            Response::json(['ok' => false, 'message' => 'Configuracao nao encontrada.'], 404);
        }

        try {
            $model->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao excluir configuracao.'], 500);
        }

        $this->audit('setting.delete', 'setting', (string) $id, ['key_name' => $setting['key_name']]);

        Response::json(['ok' => true, 'message' => 'Configuracao excluida com sucesso.']);
    }

    public function saveOneDrive(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $clientId = trim((string) ($_POST['client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
        $redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
        $authority = strtolower(trim((string) ($_POST['authority'] ?? 'consumers')));
        $scopes = trim((string) ($_POST['scopes'] ?? 'openid offline_access profile User.Read Files.ReadWrite'));
        $rootFolder = trim((string) ($_POST['root_folder'] ?? 'WisePlay'));
        $normalizedRootFolder = trim($rootFolder, '/');
        $uploadDebug = (int) ($_POST['upload_debug'] ?? 0) === 1 ? '1' : '0';
        $uploadChunkMb = (int) ($_POST['upload_chunk_mb'] ?? 10);
        $uploadMaxVideoGb = (int) ($_POST['upload_max_video_gb'] ?? 10);
        $uploadMaxRetries = (int) ($_POST['upload_max_retries'] ?? 3);
        $uploadRetryBackoffMs = (int) ($_POST['upload_retry_backoff_ms'] ?? 500);

        $errors = [];
        if ($clientId !== '' && !Validator::minLength($clientId, 10)) {
            $errors['client_id'] = 'Client ID parece invalido.';
        }
        if ($clientSecret !== '' && !Validator::minLength($clientSecret, 10)) {
            $errors['client_secret'] = 'Client Secret parece invalido.';
        }
        if ($redirectUri !== '' && !Validator::url($redirectUri)) {
            $errors['redirect_uri'] = 'Redirect URI invalida.';
        }
        if (!in_array($authority, ['consumers', 'common'], true)) {
            $errors['authority'] = 'Authority deve ser consumers ou common.';
        }
        if ($scopes === '') {
            $errors['scopes'] = 'Scopes obrigatorios.';
        }
        if ($normalizedRootFolder === '' || !Validator::maxLength($normalizedRootFolder, 120)) {
            $errors['root_folder'] = 'Pasta raiz invalida.';
        }
        if (!Validator::integer($uploadChunkMb, 5, 20)) {
            $errors['upload_chunk_mb'] = 'Chunk size deve estar entre 5MB e 20MB.';
        }
        if (!Validator::integer($uploadMaxVideoGb, 1, 20)) {
            $errors['upload_max_video_gb'] = 'Limite maximo de video deve estar entre 1GB e 20GB.';
        }
        if (!Validator::integer($uploadMaxRetries, 2, 6)) {
            $errors['upload_max_retries'] = 'Max retries deve estar entre 2 e 6.';
        }
        if (!Validator::integer($uploadRetryBackoffMs, 200, 10000)) {
            $errors['upload_retry_backoff_ms'] = 'Retry backoff deve estar entre 200 e 10000 ms.';
        }

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos OneDrive.', 'errors' => $errors], 422);
        }

        $model = new Setting();
        $model->upsert('onedrive', 'onedrive.client_id', $clientId, false);
        if ($clientSecret !== '') {
            $model->upsert('onedrive', 'onedrive.client_secret', $clientSecret, true);
        }
        $model->upsert('onedrive', 'onedrive.redirect_uri', $redirectUri !== '' ? $redirectUri : url('/admin/settings/onedrive/callback'), false);
        $model->upsert('onedrive', 'onedrive.authority', $authority, false);
        $model->upsert('onedrive', 'onedrive.scopes', $scopes, false);
        $model->upsert('onedrive', 'onedrive.root_folder', $normalizedRootFolder, false);
        $model->upsert('onedrive', 'onedrive.upload_debug', $uploadDebug, false);
        $model->upsert('onedrive', 'onedrive.upload_chunk_mb', (string) $uploadChunkMb, false);
        $model->upsert('onedrive', 'onedrive.upload_max_video_gb', (string) $uploadMaxVideoGb, false);
        $model->upsert('onedrive', 'onedrive.upload_max_retries', (string) $uploadMaxRetries, false);
        $model->upsert('onedrive', 'onedrive.upload_retry_backoff_ms', (string) $uploadRetryBackoffMs, false);

        $this->audit('onedrive.settings.save', 'setting', null, [
            'authority' => $authority,
            'scopes' => $scopes,
            'root_folder' => $normalizedRootFolder,
            'upload_debug' => $uploadDebug,
            'upload_chunk_mb' => $uploadChunkMb,
            'upload_max_video_gb' => $uploadMaxVideoGb,
            'upload_max_retries' => $uploadMaxRetries,
            'upload_retry_backoff_ms' => $uploadRetryBackoffMs,
            'client_id_set' => $clientId !== '',
            'client_secret_updated' => $clientSecret !== '',
        ]);

        Response::json(['ok' => true, 'message' => 'Configuracoes OneDrive salvas.']);
    }

    public function connectOneDrive(): void
    {
        $this->authorize(self::MANAGE_ROLES);

        $setting = new Setting();
        $clientId = (string) $setting->value('onedrive.client_id', '');
        $redirectUri = (string) $setting->value('onedrive.redirect_uri', url('/admin/settings/onedrive/callback'));
        $authority = (string) $setting->value('onedrive.authority', 'consumers');
        $scopes = (string) $setting->value('onedrive.scopes', 'openid offline_access profile User.Read Files.ReadWrite');

        if ($clientId === '' || $redirectUri === '') {
            flash('error', 'Configure client_id e redirect_uri antes de conectar.');
            Response::redirect('/admin/settings');
        }

        $state = bin2hex(random_bytes(24));
        $_SESSION['onedrive_oauth_state'] = $state;

        $url = (new OneDriveService())->buildAuthorizeUrl($clientId, $redirectUri, $state, $authority, $scopes);
        Response::redirect($url);
    }

    public function oneDriveCallback(): void
    {
        $this->authorize(self::MANAGE_ROLES);

        $state = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');
        $sessionState = (string) ($_SESSION['onedrive_oauth_state'] ?? '');

        if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
            flash('error', 'State OAuth invalido ou expirado.');
            Response::redirect('/admin/settings');
        }
        unset($_SESSION['onedrive_oauth_state']);

        if ($code === '') {
            $errorDesc = trim((string) ($_GET['error_description'] ?? ''));
            flash('error', $errorDesc !== '' ? 'Falha no callback OneDrive.' : 'Callback OneDrive recebido sem code.');
            Response::redirect('/admin/settings');
        }

        $setting = new Setting();
        $clientId = (string) $setting->value('onedrive.client_id', '');
        $clientSecret = (string) $setting->value('onedrive.client_secret', '');
        $redirectUri = (string) $setting->value('onedrive.redirect_uri', url('/admin/settings/onedrive/callback'));
        $authority = (string) $setting->value('onedrive.authority', 'consumers');
        $scopes = (string) $setting->value('onedrive.scopes', 'openid offline_access profile User.Read Files.ReadWrite');

        if ($clientId === '' || $clientSecret === '') {
            flash('error', 'Client ID/Secret nao configurados para concluir token exchange.');
            Response::redirect('/admin/settings');
        }

        $tokenEndpoint = (new OneDriveService())->tokenEndpoint($authority);

        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => $scopes,
        ]);

        $ch = curl_init($tokenEndpoint);
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
            $setting->upsert('onedrive', 'onedrive.last_connection_status', 'Connection failed', false);
            $this->audit('onedrive.callback.failed', 'setting', null, [
                'http_status' => $statusCode,
                'curl_error' => $curlError !== '' ? 'yes' : 'no',
            ]);

            flash('error', 'Token exchange OneDrive falhou. Verifique configuracoes.');
            Response::redirect('/admin/settings');
        }

        $setting->upsert('onedrive', 'onedrive.last_connection_status', 'Connected', false);
        $setting->upsert('onedrive', 'onedrive.last_connected_at', date('Y-m-d H:i:s'), false);
        $setting->upsert('onedrive', 'onedrive.quota_cache_json', '', false);
        $setting->upsert('onedrive', 'onedrive.quota_cache_at', '', false);

        if (isset($decoded['refresh_token']) && is_string($decoded['refresh_token'])) {
            $setting->upsert('onedrive', 'onedrive.refresh_token', $decoded['refresh_token'], true);
        }

        if (isset($decoded['access_token']) && is_string($decoded['access_token'])) {
            $setting->upsert('onedrive', 'onedrive.access_token', $decoded['access_token'], true);
            $masked = substr($decoded['access_token'], 0, 10) . '...';
            $setting->upsert('onedrive', 'onedrive.access_token_masked', $masked, false);
        }

        if (isset($decoded['expires_in']) && is_numeric($decoded['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + max(30, (int) $decoded['expires_in']));
            $setting->upsert('onedrive', 'onedrive.access_token_expires_at', $expiresAt, false);
        }

        $this->audit('onedrive.callback.success', 'setting', null, ['http_status' => $statusCode]);

        flash('success', 'OneDrive conectado com sucesso (scaffold).');
        Response::redirect('/admin/settings');
    }

    public function testOneDriveConnection(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $setting = new Setting();
        $authority = (string) $setting->value('onedrive.authority', 'consumers');
        $url = 'https://login.microsoftonline.com/' . (new OneDriveService())->authority($authority) . '/v2.0/.well-known/openid-configuration';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $statusCode >= 400 || !is_string($raw) || $raw === '') {
            Response::json(['ok' => false, 'message' => 'Falha no teste de conexao OneDrive.'], 500);
        }

        Response::json(['ok' => true, 'message' => 'Endpoint OneDrive acessivel.']);
    }

    public function refreshOneDriveQuota(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $forceRefresh = (int) ($_POST['force'] ?? 1) === 1;
        $service = new OneDriveService();

        $result = $service->fetchDriveQuota($forceRefresh);
        $card = $service->presentQuota($result);

        if (($result['ok'] ?? false) !== true) {
            Response::json([
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'Nao foi possivel atualizar quota OneDrive.'),
                'quota' => $card,
            ]);
        }

        $this->audit('onedrive.quota.refresh', 'setting', null, [
            'from_cache' => (bool) ($result['from_cache'] ?? false),
            'is_stale' => (bool) ($result['is_stale'] ?? false),
            'force' => $forceRefresh,
        ]);

        Response::json([
            'ok' => true,
            'message' => (bool) ($result['from_cache'] ?? false)
                ? 'Quota OneDrive carregado do cache.'
                : 'Quota OneDrive atualizado com sucesso.',
            'quota' => $card,
        ]);
    }

    private function readPayload(): array
    {
        return [
            'category' => trim((string) ($_POST['category'] ?? 'general')),
            'key_name' => trim((string) ($_POST['key_name'] ?? '')),
            'value_text' => trim((string) ($_POST['value_text'] ?? '')),
            'is_secret' => (int) ($_POST['is_secret'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    private function validatePayload(array $payload, Setting $model, bool $isCreate): array
    {
        $errors = [];

        if (!Validator::required($payload['category']) || !Validator::maxLength($payload['category'], 60)) {
            $errors['category'] = 'Categoria invalida.';
        }

        if (!Validator::required($payload['key_name']) || !Validator::maxLength($payload['key_name'], 190)) {
            $errors['key_name'] = 'Chave invalida.';
        }

        if ($isCreate && $model->findByKey($payload['key_name'])) {
            $errors['key_name'] = 'Esta chave ja existe.';
        }

        return $errors;
    }
}
