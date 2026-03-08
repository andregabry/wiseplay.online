<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Response;
use App\core\Validator;
use App\models\Setting;
use App\services\EmailService;
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

        $page = 1;
        $perPage = 100000;

        try {
            $result = $model->paginate($filters, $page, $perPage, [
                'exclude_key_prefixes' => ['onedrive.'],
            ]);
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

        $smtp = [
            'enabled' => $model->value('smtp.enabled', '1'),
            'host' => $model->value('smtp.host', 'smtp.hostinger.com'),
            'port' => $model->value('smtp.port', '465'),
            'username' => $model->value('smtp.username', 'noreply@wiseplay.online'),
            'password' => $model->value('smtp.password', ''),
            'encryption' => strtolower((string) $model->value('smtp.encryption', 'ssl')),
            'from_email' => $model->value('smtp.from_email', 'noreply@wiseplay.online'),
            'from_name' => $model->value('smtp.from_name', 'WisePlay'),
            'incoming_imap_host' => $model->value('smtp.incoming_imap_host', 'imap.hostinger.com'),
            'incoming_imap_port' => $model->value('smtp.incoming_imap_port', '993'),
            'incoming_imap_encryption' => strtolower((string) $model->value('smtp.incoming_imap_encryption', 'ssl')),
            'incoming_pop_host' => $model->value('smtp.incoming_pop_host', 'pop.hostinger.com'),
            'incoming_pop_port' => $model->value('smtp.incoming_pop_port', '995'),
            'incoming_pop_encryption' => strtolower((string) $model->value('smtp.incoming_pop_encryption', 'ssl')),
            'outgoing_host' => $model->value('smtp.outgoing_host', 'smtp.hostinger.com'),
            'outgoing_port' => $model->value('smtp.outgoing_port', '465'),
            'outgoing_encryption' => strtolower((string) $model->value('smtp.outgoing_encryption', 'ssl')),
        ];

        $quotaResult = $oneDriveService->fetchDriveQuota(false);
        $quotaCard = $oneDriveService->presentQuota($quotaResult);
        $canManageSettings = in_array((string) (($_SESSION['auth_user']['role'] ?? '')), self::MANAGE_ROLES, true);

        $categories = $model->categories();
        if (!in_array('auth', $categories, true)) {
            $categories[] = 'auth';
        }
        if (!in_array('smtp', $categories, true)) {
            $categories[] = 'smtp';
        }
        sort($categories);

        $this->render('admin/settings', [
            'title' => 'Settings | WisePlay Admin',
            'pageTitle' => 'Settings',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => 1,
                'perPage' => $perPage,
                'total' => (int) $result['total'],
                'lastPage' => 1,
            ],
            'categories' => $categories,
            'categoryItemsMap' => $this->buildCategoryItemsMap($model->grouped()),
            'onedrive' => $onedrive,
            'smtp' => $smtp,
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

    public function updateCategory(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $category = trim((string) ($_POST['category'] ?? ''));
        $ids = $_POST['ids'] ?? [];
        $keyNames = $_POST['key_names'] ?? [];
        $values = $_POST['values'] ?? [];
        $secrets = $_POST['secrets'] ?? [];

        if ($category === '') {
            Response::json(['ok' => false, 'message' => 'Categoria invalida.'], 422);
        }

        if (!is_array($ids) || !is_array($keyNames) || !is_array($values) || !is_array($secrets) || count($ids) === 0) {
            Response::json(['ok' => false, 'message' => 'Nenhum item para atualizar.'], 422);
        }

        if (count($ids) !== count($keyNames) || count($ids) !== count($values) || count($ids) !== count($secrets)) {
            Response::json(['ok' => false, 'message' => 'Payload invalido.'], 422);
        }

        $model = new Setting();
        $updated = 0;

        try {
            foreach (array_values($ids) as $idx => $rawId) {
                $id = (int) $rawId;
                $keyName = trim((string) ($keyNames[$idx] ?? ''));
                if (!Validator::required($keyName) || !Validator::maxLength($keyName, 190)) {
                    Response::json(['ok' => false, 'message' => 'Chave invalida encontrada no lote.'], 422);
                }

                $existing = null;
                if ($id > 0) {
                    $existing = $model->findById($id);
                    if (!$existing) {
                        Response::json(['ok' => false, 'message' => 'Configuracao nao encontrada no lote.'], 404);
                    }
                } else {
                    $existing = $model->findByKey($keyName);
                }

                $valueText = trim((string) ($values[$idx] ?? ''));
                $isSecret = (int) ($secrets[$idx] ?? 0) === 1 ? 1 : 0;

                if ($existing) {
                    $itemCategory = trim((string) ($existing['category'] ?? ''));
                    $itemCategoryLabel = $itemCategory !== '' ? $itemCategory : 'uncategorized';
                    if ($itemCategoryLabel !== $category) {
                        Response::json(['ok' => false, 'message' => 'As configuracoes devem pertencer a mesma categoria.'], 422);
                    }

                    $model->update((int) $existing['id'], [
                        'category' => $itemCategory,
                        'key_name' => (string) ($existing['key_name'] ?? $keyName),
                        'value_text' => $valueText,
                        'is_secret' => $isSecret,
                    ]);
                } else {
                    $model->create([
                        'category' => $category,
                        'key_name' => $keyName,
                        'value_text' => $valueText,
                        'is_secret' => $isSecret,
                    ]);
                }

                $updated++;
            }
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar configuracoes da categoria.'], 500);
        }

        $this->audit('setting.update_category', 'setting', null, [
            'category' => $category,
            'updated_count' => $updated,
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Categoria atualizada com sucesso.',
            'updated_count' => $updated,
        ]);
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

    public function saveSmtp(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $enabled = (int) ($_POST['enabled'] ?? 1) === 1 ? '1' : '0';
        $host = trim((string) ($_POST['host'] ?? 'smtp.hostinger.com'));
        $port = (int) ($_POST['port'] ?? 465);
        $username = trim((string) ($_POST['username'] ?? 'noreply@wiseplay.online'));
        $password = trim((string) ($_POST['password'] ?? ''));
        $encryption = strtolower(trim((string) ($_POST['encryption'] ?? 'ssl')));
        $fromEmail = trim((string) ($_POST['from_email'] ?? $username));
        $fromName = trim((string) ($_POST['from_name'] ?? 'WisePlay'));

        $incomingImapHost = trim((string) ($_POST['incoming_imap_host'] ?? 'imap.hostinger.com'));
        $incomingImapPort = (int) ($_POST['incoming_imap_port'] ?? 993);
        $incomingImapEncryption = strtolower(trim((string) ($_POST['incoming_imap_encryption'] ?? 'ssl')));

        $incomingPopHost = trim((string) ($_POST['incoming_pop_host'] ?? 'pop.hostinger.com'));
        $incomingPopPort = (int) ($_POST['incoming_pop_port'] ?? 995);
        $incomingPopEncryption = strtolower(trim((string) ($_POST['incoming_pop_encryption'] ?? 'ssl')));

        $outgoingHost = $host;
        $outgoingPort = $port;
        $outgoingEncryption = $encryption;

        $errors = [];
        if ($host === '') {
            $errors['host'] = 'Servidor SMTP obrigatorio.';
        }
        if (!Validator::integer($port, 1, 65535)) {
            $errors['port'] = 'Porta SMTP invalida.';
        }
        if ($username === '' || !Validator::email($username)) {
            $errors['username'] = 'Email de autenticacao SMTP invalido.';
        }
        if ($fromEmail === '' || !Validator::email($fromEmail)) {
            $errors['from_email'] = 'Email remetente invalido.';
        }
        if ($fromName === '' || !Validator::maxLength($fromName, 120)) {
            $errors['from_name'] = 'Nome do remetente invalido.';
        }
        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            $errors['encryption'] = 'Criptografia SMTP invalida.';
        }

        if ($incomingImapHost === '') {
            $errors['incoming_imap_host'] = 'Servidor IMAP obrigatorio.';
        }
        if (!Validator::integer($incomingImapPort, 1, 65535)) {
            $errors['incoming_imap_port'] = 'Porta IMAP invalida.';
        }
        if (!in_array($incomingImapEncryption, ['none', 'tls', 'ssl'], true)) {
            $errors['incoming_imap_encryption'] = 'Criptografia IMAP invalida.';
        }

        if ($incomingPopHost === '') {
            $errors['incoming_pop_host'] = 'Servidor POP obrigatorio.';
        }
        if (!Validator::integer($incomingPopPort, 1, 65535)) {
            $errors['incoming_pop_port'] = 'Porta POP invalida.';
        }
        if (!in_array($incomingPopEncryption, ['none', 'tls', 'ssl'], true)) {
            $errors['incoming_pop_encryption'] = 'Criptografia POP invalida.';
        }

        if ($outgoingHost === '') {
            $errors['outgoing_host'] = 'Servidor de saida obrigatorio.';
        }
        if (!Validator::integer($outgoingPort, 1, 65535)) {
            $errors['outgoing_port'] = 'Porta de saida invalida.';
        }
        if (!in_array($outgoingEncryption, ['none', 'tls', 'ssl'], true)) {
            $errors['outgoing_encryption'] = 'Criptografia de saida invalida.';
        }

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos SMTP.', 'errors' => $errors], 422);
        }

        $model = new Setting();
        $model->upsert('smtp', 'smtp.enabled', $enabled, false);
        $model->upsert('smtp', 'smtp.host', $host, false);
        $model->upsert('smtp', 'smtp.port', (string) $port, false);
        $model->upsert('smtp', 'smtp.username', $username, false);
        if ($password !== '') {
            $model->upsert('smtp', 'smtp.password', $password, true);
        }
        $model->upsert('smtp', 'smtp.encryption', $encryption, false);
        $model->upsert('smtp', 'smtp.from_email', $fromEmail, false);
        $model->upsert('smtp', 'smtp.from_name', $fromName, false);

        $model->upsert('smtp', 'smtp.incoming_imap_host', $incomingImapHost, false);
        $model->upsert('smtp', 'smtp.incoming_imap_port', (string) $incomingImapPort, false);
        $model->upsert('smtp', 'smtp.incoming_imap_encryption', $incomingImapEncryption, false);
        $model->upsert('smtp', 'smtp.incoming_pop_host', $incomingPopHost, false);
        $model->upsert('smtp', 'smtp.incoming_pop_port', (string) $incomingPopPort, false);
        $model->upsert('smtp', 'smtp.incoming_pop_encryption', $incomingPopEncryption, false);
        $model->upsert('smtp', 'smtp.outgoing_host', $outgoingHost, false);
        $model->upsert('smtp', 'smtp.outgoing_port', (string) $outgoingPort, false);
        $model->upsert('smtp', 'smtp.outgoing_encryption', $outgoingEncryption, false);

        $this->audit('smtp.settings.save', 'setting', null, [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password_updated' => $password !== '',
            'encryption' => $encryption,
            'from_email' => $fromEmail,
        ]);

        Response::json(['ok' => true, 'message' => 'Configuracoes SMTP salvas.']);
    }

    public function testSmtpConnection(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $toEmail = trim((string) ($_POST['to_email'] ?? ''));
        $setting = new Setting();
        if ($toEmail === '') {
            $toEmail = (string) $setting->value('smtp.username', (string) $setting->value('smtp.from_email', ''));
        }

        if ($toEmail === '' || !Validator::email($toEmail)) {
            Response::json(['ok' => false, 'message' => 'Email de destino de teste invalido.'], 422);
        }

        $service = new EmailService($setting);
        $result = $service->sendTestEmail(
            $toEmail,
            'WisePlay SMTP test',
            "Este e um email de teste enviado pelo painel Admin do WisePlay.\n\nSe voce recebeu esta mensagem, o SMTP esta configurado corretamente."
        );

        if (($result['ok'] ?? false) !== true) {
            Response::json(['ok' => false, 'message' => (string) ($result['message'] ?? 'Falha no teste SMTP.')], 500);
        }

        $this->audit('smtp.test.success', 'setting', null, [
            'to' => $toEmail,
        ]);

        Response::json([
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'Email de teste enviado com sucesso.'),
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

    private function buildCategoryItemsMap(array $grouped): array
    {
        $map = [];
        foreach ($grouped as $category => $items) {
            $label = trim((string) $category);
            if ($label === '') {
                $label = 'uncategorized';
            }

            foreach ((array) $items as $item) {
                $keyName = (string) ($item['key_name'] ?? '');
                if (str_starts_with($keyName, 'onedrive.')) {
                    continue;
                }

                $map[$label][] = [
                    'id' => (int) ($item['id'] ?? 0),
                    'key_name' => $keyName,
                    'value_text' => (string) ($item['value_text'] ?? ''),
                    'is_secret' => (int) ($item['is_secret'] ?? 0),
                ];
            }
        }

        $authDefaults = $this->authDefaultSettings();
        if (!isset($map['auth'])) {
            $map['auth'] = [];
        }

        $byKey = [];
        foreach ($map['auth'] as $item) {
            $byKey[(string) ($item['key_name'] ?? '')] = $item;
        }

        $normalizedAuth = [];
        foreach ($authDefaults as $defaultItem) {
            $keyName = (string) $defaultItem['key_name'];
            if (isset($byKey[$keyName])) {
                $normalizedAuth[] = $byKey[$keyName];
                continue;
            }

            $normalizedAuth[] = [
                'id' => 0,
                'key_name' => $keyName,
                'value_text' => (string) $defaultItem['value_text'],
                'is_secret' => (int) $defaultItem['is_secret'],
            ];
        }

        foreach ($map['auth'] as $item) {
            $keyName = (string) ($item['key_name'] ?? '');
            if ($keyName === '') {
                continue;
            }
            $alreadyIncluded = false;
            foreach ($normalizedAuth as $authItem) {
                if ((string) ($authItem['key_name'] ?? '') === $keyName) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) {
                $normalizedAuth[] = $item;
            }
        }

        $map['auth'] = $normalizedAuth;

        return $map;
    }

    private function authDefaultSettings(): array
    {
        return [
            ['key_name' => 'auth.password_min_length', 'value_text' => '8', 'is_secret' => 0],
            ['key_name' => 'auth.verification_code_max_attempts', 'value_text' => '5', 'is_secret' => 0],
            ['key_name' => 'auth.verification_code_ttl_minutes', 'value_text' => '10', 'is_secret' => 0],
            ['key_name' => 'auth.verification_resend_cooldown_seconds', 'value_text' => '60', 'is_secret' => 0],
            ['key_name' => 'auth.verification_session_ttl_minutes', 'value_text' => '30', 'is_secret' => 0],
        ];
    }
}
