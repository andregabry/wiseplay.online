<?php
$onedrive = $onedrive ?? [];
$smtp = $smtp ?? [];
$oneDriveQuota = $oneDriveQuota ?? [];
$canManageSettings = $canManageSettings ?? false;
$configProfiles = $configProfiles ?? [];
$configProfileCategories = $configProfileCategories ?? [];

if (!is_array($configProfileCategories) || $configProfileCategories === []) {
    $configProfileCategories = [
        'api_connect' => 'API Connect',
        'email' => 'Email',
        'general' => 'General',
        'payment' => 'Payment',
    ];
}

$mask = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    if (strlen($value) <= 6) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 3) . str_repeat('*', strlen($value) - 6) . substr($value, -3);
};

$oneDriveStatus = (string) ($onedrive['last_connection_status'] ?? 'Not connected');
$oneDriveLastConnected = (string) (($onedrive['last_connected_at'] ?? '') !== '' ? $onedrive['last_connected_at'] : '-');
$oneDriveQuotaSummary = (string) ($oneDriveQuota['used_human'] ?? '-') . ' / ' . (string) ($oneDriveQuota['total_human'] ?? '-');

$configProfilesDisplay = is_array($configProfiles) ? $configProfiles : [];
array_unshift($configProfilesDisplay, [
    '__kind' => 'onedrive',
    'id' => 0,
    'category' => 'api_connect',
    'config_name' => 'OneDrive Personal',
    'description' => 'Integracao com OneDrive para uploads e streaming. Status: ' . $oneDriveStatus . ' | Quota: ' . $oneDriveQuotaSummary,
    'is_default' => 1,
    'is_active' => 1,
    'payload_json' => (string) json_encode([
        'authority' => (string) ($onedrive['authority'] ?? 'consumers'),
        'root_folder' => (string) ($onedrive['root_folder'] ?? 'WisePlay'),
        'status' => $oneDriveStatus,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$profileCategoryLabel = static function (string $category) use ($configProfileCategories): string {
    if (isset($configProfileCategories[$category])) {
        return (string) $configProfileCategories[$category];
    }

    return ucfirst(str_replace('_', ' ', $category));
};
?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="wp-card-heading">
      <span class="wp-card-heading-icon"><i class="ri ri-list-settings-line"></i></span>
      <div class="wp-card-heading-copy">
        <h5 class="mb-0">Settings por categoria</h5>
        <small class="text-body-secondary">Defina multiplas configuracoes por categoria e marque qual sera a padrao de producao.</small>
      </div>
    </div>
    <?php if ($canManageSettings): ?>
      <button type="button" class="btn btn-primary btn-sm js-open-config-profile-create"><i class="ri ri-add-circle-line me-1"></i>Adicionar settings</button>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Categoria</th>
          <th>Descricao</th>
          <th>Padrao</th>
          <?php if ($canManageSettings): ?><th>Acoes</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($configProfilesDisplay === []): ?>
        <tr><td colspan="<?= $canManageSettings ? '4' : '3' ?>" class="text-center text-body-secondary">Nenhuma config cadastrada para categorias.</td></tr>
      <?php endif; ?>

      <?php foreach ($configProfilesDisplay as $profile): ?>
        <?php
        $profilePayload = json_decode((string) ($profile['payload_json'] ?? '{}'), true);
        if (!is_array($profilePayload)) {
            $profilePayload = [];
        }

        $profileJson = [
            'id' => (int) ($profile['id'] ?? 0),
            'category' => (string) ($profile['category'] ?? ''),
            'config_name' => (string) ($profile['config_name'] ?? ''),
            'description' => (string) ($profile['description'] ?? ''),
            'is_default' => (int) ($profile['is_default'] ?? 0),
            'is_active' => (int) ($profile['is_active'] ?? 1),
            'payload' => $profilePayload,
        ];
        ?>
        <tr>
          <td>
            <span class="badge bg-label-secondary"><?= e($profileCategoryLabel((string) ($profile['category'] ?? 'general'))) ?></span>
          </td>
          <td class="setting-value-cell">
            <div class="fw-medium"><?= e((string) ($profile['description'] ?? '-')) ?></div>
            <?php if ((string) ($profile['config_name'] ?? '') !== ''): ?>
              <small class="text-body-secondary">Nome: <?= e((string) $profile['config_name']) ?></small>
            <?php endif; ?>
            <?php if ((string) ($profile['__kind'] ?? '') === 'onedrive'): ?>
              <div class="small text-body-secondary mt-1">Ultima conexao: <?= e($oneDriveLastConnected) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int) ($profile['is_default'] ?? 0) === 1): ?>
              <span class="badge bg-success">Padrao</span>
            <?php else: ?>
              <span class="badge bg-label-secondary">Alternativa</span>
            <?php endif; ?>
          </td>

          <?php if ($canManageSettings): ?>
          <td>
            <div class="d-flex align-items-center gap-1">
              <?php if ((string) ($profile['__kind'] ?? '') === 'onedrive'): ?>
                <button type="button" class="btn btn-sm btn-outline-primary js-edit-onedrive" title="Editar OneDrive"><i class="ri ri-cloud-line"></i></button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary js-edit-config-profile" data-config='<?= e((string) json_encode($profileJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>' title="Editar"><i class="ri ri-pencil-line"></i></button>
                <button type="button" class="btn btn-sm btn-outline-success js-default-config-profile" data-id="<?= (int) ($profile['id'] ?? 0) ?>" data-description="<?= e((string) ($profile['description'] ?? 'config')) ?>" title="Definir padrao" <?= ((int) ($profile['is_default'] ?? 0) === 1) ? 'disabled' : '' ?>><i class="ri ri-check-line"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger js-delete-config-profile" data-id="<?= (int) ($profile['id'] ?? 0) ?>" data-name="<?= e((string) ($profile['description'] ?? 'config')) ?>" title="Excluir"><i class="ri ri-delete-bin-line"></i></button>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canManageSettings): ?>
<div class="modal fade" id="setting-config-profile-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Configuracao por categoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="setting-config-profile"></div>
    <form id="setting-config-profile-form" class="js-ajax-form" data-endpoint="/admin/settings/configs/create" data-errors-key="setting-config-profile">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <input type="hidden" name="id" id="scp-id" value="">

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Categoria</label>
          <select class="form-select" name="category" id="scp-category" required>
            <?php foreach ($configProfileCategories as $value => $label): ?>
              <option value="<?= e((string) $value) ?>"><?= e((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Nome da config (opcional)</label>
          <input class="form-control" name="config_name" id="scp-config-name" maxlength="120" placeholder="Ex: Producao principal">
        </div>
        <div class="col-md-2">
          <label class="form-label">Padrao</label>
          <select class="form-select" name="is_default" id="scp-is-default">
            <option value="0">Nao</option>
            <option value="1">Sim</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Ativa</label>
          <select class="form-select" name="is_active" id="scp-is-active">
            <option value="1">Sim</option>
            <option value="0">Nao</option>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Descricao</label>
        <textarea class="form-control" name="description" id="scp-description" rows="2" maxlength="255" required placeholder="Explique para que essa configuracao sera usada"></textarea>
      </div>

      <div id="scp-dynamic-fields" class="d-grid gap-3"></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-primary js-submit-form" data-form="#setting-config-profile-form">Salvar configuracao</button>
  </div>
</div></div></div>

<div class="modal fade" id="onedrive-edit-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Editar OneDrive Personal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="onedrive-save"></div>
    <form id="onedrive-save-form" class="js-ajax-form" data-endpoint="/admin/settings/onedrive/save" data-errors-key="onedrive-save">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <div class="mb-3"><label class="form-label">Client ID</label><input class="form-control" name="client_id" value="<?= e((string) ($onedrive['client_id'] ?? '')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>></div>
      <div class="mb-3"><label class="form-label">Client Secret</label><input class="form-control" name="client_secret" value="" placeholder="<?= e($mask((string) ($onedrive['client_secret'] ?? ''))) ?>" <?= $canManageSettings ? '' : 'readonly' ?>></div>
      <div class="mb-3"><label class="form-label">Redirect URI</label><input class="form-control" name="redirect_uri" value="<?= e((string) ($onedrive['redirect_uri'] ?? '')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>></div>
      <div class="mb-3"><label class="form-label">Authority</label><select class="form-select" name="authority" <?= $canManageSettings ? '' : 'disabled' ?>><option value="consumers" <?= ((string) ($onedrive['authority'] ?? 'consumers') === 'consumers') ? 'selected' : '' ?>>consumers</option><option value="common" <?= ((string) ($onedrive['authority'] ?? 'consumers') === 'common') ? 'selected' : '' ?>>common</option></select></div>
      <div class="mb-3"><label class="form-label">Scopes</label><textarea class="form-control" name="scopes" rows="2" <?= $canManageSettings ? '' : 'readonly' ?>><?= e((string) ($onedrive['scopes'] ?? '')) ?></textarea></div>
      <div class="mb-3"><label class="form-label">Root folder</label><input class="form-control" name="root_folder" value="<?= e((string) ($onedrive['root_folder'] ?? 'WisePlay')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>></div>
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label">Chunk size (MB)</label>
          <input class="form-control" type="number" min="5" max="20" name="upload_chunk_mb" value="<?= e((string) ($onedrive['upload_chunk_mb'] ?? '10')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">Max video (GB)</label>
          <input class="form-control" type="number" min="1" max="20" name="upload_max_video_gb" value="<?= e((string) ($onedrive['upload_max_video_gb'] ?? '10')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">Max retries</label>
          <input class="form-control" type="number" min="2" max="6" name="upload_max_retries" value="<?= e((string) ($onedrive['upload_max_retries'] ?? '3')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">Retry backoff (ms)</label>
          <input class="form-control" type="number" min="200" max="10000" step="100" name="upload_retry_backoff_ms" value="<?= e((string) ($onedrive['upload_retry_backoff_ms'] ?? '500')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="onedrive-upload-debug" name="upload_debug" value="1" <?= ((string) ($onedrive['upload_debug'] ?? '0') === '1') ? 'checked' : '' ?> <?= $canManageSettings ? '' : 'disabled' ?>>
        <label class="form-check-label" for="onedrive-upload-debug">Debug mode upload (logs detalhados)</label>
      </div>
    </form>

    <div class="d-grid gap-2 mt-3">
      <a class="btn btn-outline-primary <?= $canManageSettings ? '' : 'disabled' ?>" href="<?= $canManageSettings ? '/admin/settings/onedrive/connect' : '#' ?>"><i class="ri ri-link me-1"></i>Connect</a>
      <button class="btn btn-outline-secondary js-onedrive-test <?= $canManageSettings ? '' : 'disabled' ?>" <?= $canManageSettings ? '' : 'disabled' ?>><i class="ri ri-flask-line me-1"></i>Test connection</button>
      <button class="btn btn-outline-info js-onedrive-quota-refresh <?= $canManageSettings ? '' : 'disabled' ?>" <?= $canManageSettings ? '' : 'disabled' ?>><i class="ri ri-refresh-line me-1"></i>Refresh quota</button>
    </div>

    <div class="mt-3 p-3 rounded bg-label-secondary small">
      <div><strong>Status:</strong> <?= e((string) ($onedrive['last_connection_status'] ?? 'Not connected')) ?></div>
      <div><strong>Ultima conexao:</strong> <?= e((string) (($onedrive['last_connected_at'] ?? '') !== '' ? $onedrive['last_connected_at'] : '-')) ?></div>
      <div><strong>Token expira em:</strong> <?= e((string) (($onedrive['access_token_expires_at'] ?? '') !== '' ? $onedrive['access_token_expires_at'] : '-')) ?></div>
      <div><strong>Ultimo upload:</strong> <?= e((string) (($onedrive['last_upload_at'] ?? '') !== '' ? $onedrive['last_upload_at'] : '-')) ?></div>
    </div>

    <div class="wp-onedrive-quota-widget mt-3" data-onedrive-quota-widget>
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">Storage quota</h6>
        <span class="badge <?= e((string) ($oneDriveQuota['state_badge_class'] ?? 'bg-label-secondary')) ?>" data-quota-field="state_badge">
          <span data-quota-field="state_label"><?= e((string) ($oneDriveQuota['state_label'] ?? 'Unknown')) ?></span>
        </span>
      </div>

      <div class="d-flex justify-content-between small text-body-secondary mb-1">
        <span data-quota-field="used_human"><?= e((string) ($oneDriveQuota['used_human'] ?? '-')) ?></span>
        <span data-quota-field="percentage_label"><?= e((string) ($oneDriveQuota['percentage_label'] ?? '0.0%')) ?></span>
      </div>
      <div class="progress wp-quota-progress mb-3">
        <div
          class="progress-bar <?= e((string) ($oneDriveQuota['progress_bar_class'] ?? 'bg-secondary')) ?>"
          data-quota-field="progress_bar"
          role="progressbar"
          style="width: <?= e((string) ($oneDriveQuota['percentage'] ?? 0)) ?>%;"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-valuenow="<?= e((string) ($oneDriveQuota['percentage'] ?? 0)) ?>"></div>
      </div>

      <div class="row g-2 small mb-2">
        <div class="col-4">
          <div class="wp-quota-metric-label">Total</div>
          <div class="wp-quota-metric-value" data-quota-field="total_human"><?= e((string) ($oneDriveQuota['total_human'] ?? '-')) ?></div>
        </div>
        <div class="col-4">
          <div class="wp-quota-metric-label">Used</div>
          <div class="wp-quota-metric-value" data-quota-field="used_human_secondary"><?= e((string) ($oneDriveQuota['used_human'] ?? '-')) ?></div>
        </div>
        <div class="col-4">
          <div class="wp-quota-metric-label">Remaining</div>
          <div class="wp-quota-metric-value" data-quota-field="remaining_human"><?= e((string) ($oneDriveQuota['remaining_human'] ?? '-')) ?></div>
        </div>
      </div>

      <div class="small text-success fw-medium" data-quota-field="plan_hint"><?= e((string) ($oneDriveQuota['plan_hint'] ?? '')) ?></div>
      <div class="small text-body-secondary" data-quota-field="message"><?= e((string) ($oneDriveQuota['message'] ?? '')) ?></div>
      <div class="small text-body-secondary mt-1">Last updated: <span data-quota-field="last_updated"><?= e((string) ($oneDriveQuota['last_updated'] ?? '-')) ?></span></div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
    <button class="btn btn-primary js-submit-form" data-form="#onedrive-save-form">Salvar OneDrive</button>
  </div>
</div></div></div>

<div class="modal fade" id="smtp-edit-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Configurar SMTP / Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="smtp-save"></div>
    <form id="smtp-save-form" class="js-ajax-form" data-endpoint="/admin/settings/smtp/save" data-errors-key="smtp-save">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label">SMTP habilitado</label>
          <select class="form-select" name="enabled" <?= $canManageSettings ? '' : 'disabled' ?>>
            <option value="1" <?= ((string) ($smtp['enabled'] ?? '1') === '1') ? 'selected' : '' ?>>Sim</option>
            <option value="0" <?= ((string) ($smtp['enabled'] ?? '1') === '0') ? 'selected' : '' ?>>Nao</option>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Email de autenticacao</label>
          <input class="form-control" name="username" value="<?= e((string) ($smtp['username'] ?? 'noreply@wiseplay.online')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-4">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="password" value="" placeholder="<?= e($mask((string) ($smtp['password'] ?? ''))) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
      </div>

      <h6 class="mb-2">Outgoing server (SMTP)</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label">Servidor</label>
          <input class="form-control" name="host" value="<?= e((string) ($smtp['host'] ?? 'smtp.hostinger.com')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Porta</label>
          <input class="form-control" type="number" name="port" min="1" max="65535" value="<?= e((string) ($smtp['port'] ?? '465')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Criptografia</label>
          <select class="form-select" name="encryption" <?= $canManageSettings ? '' : 'disabled' ?>>
            <?php $smtpEncryption = strtolower((string) ($smtp['encryption'] ?? 'ssl')); ?>
            <option value="ssl" <?= $smtpEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="tls" <?= $smtpEncryption === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="none" <?= $smtpEncryption === 'none' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Protocolo</label>
          <input class="form-control" value="SMTP" readonly>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">From email</label>
          <input class="form-control" name="from_email" value="<?= e((string) ($smtp['from_email'] ?? 'noreply@wiseplay.online')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-6">
          <label class="form-label">From name</label>
          <input class="form-control" name="from_name" value="<?= e((string) ($smtp['from_name'] ?? 'WisePlay')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
      </div>

      <h6 class="mb-2">Incoming server (IMAP)</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label">Servidor</label>
          <input class="form-control" name="incoming_imap_host" value="<?= e((string) ($smtp['incoming_imap_host'] ?? 'imap.hostinger.com')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Porta</label>
          <input class="form-control" type="number" name="incoming_imap_port" min="1" max="65535" value="<?= e((string) ($smtp['incoming_imap_port'] ?? '993')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Criptografia</label>
          <?php $imapEncryption = strtolower((string) ($smtp['incoming_imap_encryption'] ?? 'ssl')); ?>
          <select class="form-select" name="incoming_imap_encryption" <?= $canManageSettings ? '' : 'disabled' ?>>
            <option value="ssl" <?= $imapEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="tls" <?= $imapEncryption === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="none" <?= $imapEncryption === 'none' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Protocolo</label>
          <input class="form-control" value="IMAP" readonly>
        </div>
      </div>

      <h6 class="mb-2">Incoming server (POP)</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label">Servidor</label>
          <input class="form-control" name="incoming_pop_host" value="<?= e((string) ($smtp['incoming_pop_host'] ?? 'pop.hostinger.com')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Porta</label>
          <input class="form-control" type="number" name="incoming_pop_port" min="1" max="65535" value="<?= e((string) ($smtp['incoming_pop_port'] ?? '995')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
        </div>
        <div class="col-md-2">
          <label class="form-label">Criptografia</label>
          <?php $popEncryption = strtolower((string) ($smtp['incoming_pop_encryption'] ?? 'ssl')); ?>
          <select class="form-select" name="incoming_pop_encryption" <?= $canManageSettings ? '' : 'disabled' ?>>
            <option value="ssl" <?= $popEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="tls" <?= $popEncryption === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="none" <?= $popEncryption === 'none' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Protocolo</label>
          <input class="form-control" value="POP" readonly>
        </div>
      </div>
    </form>

    <div class="row g-2 mt-2">
      <div class="col-md-8">
        <label class="form-label">Email para teste</label>
        <input class="form-control" id="smtp-test-email" value="<?= e((string) ($smtp['username'] ?? 'noreply@wiseplay.online')) ?>" <?= $canManageSettings ? '' : 'readonly' ?>>
      </div>
      <div class="col-md-4 d-grid">
        <label class="form-label invisible">Testar</label>
        <button class="btn btn-outline-secondary js-smtp-test <?= $canManageSettings ? '' : 'disabled' ?>" <?= $canManageSettings ? '' : 'disabled' ?>>
          <i class="ri ri-mail-send-line me-1"></i>Testar envio
        </button>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
    <button class="btn btn-primary js-submit-form" data-form="#smtp-save-form">Salvar SMTP</button>
  </div>
</div></div></div>
<?php endif; ?>
