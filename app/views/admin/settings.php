<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'category' => ''];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1];
$categories = $categories ?? [];
$onedrive = $onedrive ?? [];
$oneDriveQuota = $oneDriveQuota ?? [];
$canManageSettings = $canManageSettings ?? false;

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/settings?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'category' => (string) ($filters['category'] ?? ''),
        'page' => $pageNum,
    ]);
};

$mask = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    if (strlen($value) <= 6) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 3) . str_repeat('*', strlen($value) - 6) . substr($value, -3);
};
?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="wp-card-heading">
      <span class="wp-card-heading-icon"><i class="ri ri-settings-4-line"></i></span>
      <div class="wp-card-heading-copy">
        <h5 class="mb-0">Configuracoes gerais</h5>
        <small class="text-body-secondary">Lista de configuracoes em tabela com acoes por linha.</small>
      </div>
    </div>
    <?php if ($canManageSettings): ?>
      <button class="btn btn-primary btn-sm js-open-modal" data-target="#setting-create-modal"><i class="ri ri-add-circle-line me-1"></i>Adicionar setting</button>
    <?php endif; ?>
  </div>
  <div class="card-body border-bottom">
    <form method="get" action="/admin/settings" class="row g-3">
      <div class="col-md-6"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por chave ou valor"></div>
      <div class="col-md-4"><select class="form-select" name="category"><option value="">Todas as categorias</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category) ?>" <?= ((string) $filters['category'] === (string) $category) ? 'selected' : '' ?>><?= e((string) $category) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>Categoria</th>
          <th>Chave</th>
          <th>Valor</th>
          <th>Secreta</th>
          <?php if ($canManageSettings): ?><th>Acoes</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($items === []): ?>
        <tr><td colspan="<?= $canManageSettings ? '5' : '4' ?>" class="text-center text-body-secondary">Nenhuma configuracao encontrada.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <?php
          $payload = json_encode([
              'id' => (int) $item['id'],
              'category' => (string) $item['category'],
              'key_name' => (string) $item['key_name'],
              'value_text' => (string) ($item['value_text'] ?? ''),
              'is_secret' => (int) ($item['is_secret'] ?? 0),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <tr>
          <td><span class="badge bg-label-secondary"><?= e((string) $item['category']) ?></span></td>
          <td><?= e((string) $item['key_name']) ?></td>
          <td class="setting-value-cell"><?= e(((int) $item['is_secret'] === 1) ? $mask((string) ($item['value_text'] ?? '')) : (string) ($item['value_text'] ?? '')) ?></td>
          <td><?= ((int) $item['is_secret'] === 1) ? 'Sim' : 'Nao' ?></td>
          <?php if ($canManageSettings): ?>
          <td>
            <div class="d-flex align-items-center gap-1">
              <button type="button" class="btn btn-sm btn-outline-secondary js-view-setting" data-setting='<?= e($payload) ?>' title="Visualizar"><i class="ri ri-eye-line"></i></button>
              <button type="button" class="btn btn-sm btn-outline-primary js-edit-setting" data-setting='<?= e($payload) ?>' title="Editar"><i class="ri ri-pencil-line"></i></button>
              <button type="button" class="btn btn-sm btn-outline-danger js-delete-setting" data-id="<?= (int) $item['id'] ?>" data-name="<?= e((string) $item['key_name']) ?>" title="Excluir"><i class="ri ri-delete-bin-line"></i></button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header">
    <div class="wp-card-heading">
      <span class="wp-card-heading-icon"><i class="ri ri-cloud-line"></i></span>
      <div class="wp-card-heading-copy">
        <h5 class="mb-0">OneDrive Personal</h5>
      </div>
    </div>
  </div>
  <div class="card-body">
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
          <?php if ($canManageSettings): ?>
            <div class="d-grid mb-2"><button class="btn btn-primary js-submit-form" data-form="#onedrive-save-form" type="button">Salvar OneDrive</button></div>
          <?php endif; ?>
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
</div>

<?php if ((int) $pagination['lastPage'] > 1): ?>
<nav class="mt-2"><ul class="pagination">
<li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a></li>
<?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?><li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a></li><?php endfor; ?>
<li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a></li>
</ul></nav>
<?php endif; ?>

<?php if ($canManageSettings): ?>
<div class="modal fade" id="setting-create-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Nova configuracao</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="setting-create"></div>
    <form id="setting-create-form" class="js-ajax-form" data-endpoint="/admin/settings/create" data-errors-key="setting-create">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <div class="mb-3"><label class="form-label">Categoria</label><input class="form-control" name="category" value="general" required></div>
      <div class="mb-3"><label class="form-label">Chave</label><input class="form-control" name="key_name" required></div>
      <div class="mb-3"><label class="form-label">Valor</label><textarea class="form-control" name="value_text" rows="3"></textarea></div>
      <div class="mb-0"><label class="form-label">Secreta</label><select class="form-select" name="is_secret"><option value="0">Nao</option><option value="1">Sim</option></select></div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#setting-create-form">Criar</button></div>
</div></div></div>

<div class="modal fade" id="setting-edit-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Editar configuracao</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="setting-edit"></div>
    <form id="setting-edit-form" class="js-ajax-form" data-endpoint="/admin/settings/update" data-errors-key="setting-edit">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <input type="hidden" name="id" id="s-e-id">
      <div class="mb-3"><label class="form-label">Categoria</label><input class="form-control" name="category" id="s-e-category" required></div>
      <div class="mb-3"><label class="form-label">Chave</label><input class="form-control" name="key_name" id="s-e-key-name" required></div>
      <div class="mb-3"><label class="form-label">Valor</label><textarea class="form-control" name="value_text" id="s-e-value-text" rows="3"></textarea></div>
      <div class="mb-0"><label class="form-label">Secreta</label><select class="form-select" name="is_secret" id="s-e-is-secret"><option value="0">Nao</option><option value="1">Sim</option></select></div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#setting-edit-form">Salvar</button></div>
</div></div></div>

<div class="modal fade" id="setting-view-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Visualizar configuracao</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Categoria</label><input class="form-control" id="s-v-category" readonly></div>
    <div class="mb-3"><label class="form-label">Chave</label><input class="form-control" id="s-v-key-name" readonly></div>
    <div class="mb-3"><label class="form-label">Valor</label><textarea class="form-control" id="s-v-value-text" rows="4" readonly></textarea></div>
    <div class="mb-0"><label class="form-label">Secreta</label><input class="form-control" id="s-v-is-secret" readonly></div>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
</div></div></div>
<?php endif; ?>
