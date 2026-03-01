<?php
$planItems = $planItems ?? [];
$planFilters = $planFilters ?? ['q' => '', 'active' => '', 'billing_cycle' => ''];
$planPagination = $planPagination ?? ['page' => 1, 'lastPage' => 1];
$subItems = $subItems ?? [];
$subFilters = $subFilters ?? ['q' => '', 'status' => '', 'plan_id' => 0];
$subPagination = $subPagination ?? ['page' => 1, 'lastPage' => 1];
$cycleOptions = $cycleOptions ?? ['MONTHLY', 'YEARLY'];
$statusOptions = $statusOptions ?? ['ACTIVE', 'TRIALING', 'PAUSED', 'CANCELED'];
$planOptions = $planOptions ?? [];
$userOptions = $userOptions ?? [];
$canManageBilling = $canManageBilling ?? false;
$paymentGatewayEnabled = $paymentGatewayEnabled ?? false;
$paymentProvider = (string) ($paymentProvider ?? 'MOCK');
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-bank-card-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Billing Plans</h5>
          <p class="text-body-secondary mb-0">Configure planos, ciclos e limites de consumo.</p>
        </div>
      </div>
      <?php if ($canManageBilling): ?>
      <button class="btn btn-primary js-open-modal" data-target="#plan-create-modal"><i class="ri ri-add-circle-line me-1"></i>Novo plano</button>
      <?php endif; ?>
    </div>

    <?php if (!$paymentGatewayEnabled): ?>
      <div class="alert alert-warning mb-3">
        Gateway de pagamento <strong><?= e($paymentProvider) ?></strong> desabilitado. Planos com pagamento obrigatorio ficarao indisponiveis na selecao publica.
      </div>
    <?php endif; ?>

    <form method="get" action="/admin/billing" class="row g-3">
      <div class="col-md-4"><input class="form-control" name="plan_q" value="<?= e((string) $planFilters['q']) ?>" placeholder="Buscar plano"></div>
      <div class="col-md-3"><select class="form-select" name="plan_cycle"><option value="">Todos os ciclos</option><?php foreach ($cycleOptions as $cycle): ?><option value="<?= e($cycle) ?>" <?= ((string) $planFilters['billing_cycle'] === (string) $cycle) ? 'selected' : '' ?>><?= e($cycle) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select" name="plan_active"><option value="">Todos</option><option value="1" <?= ((string) $planFilters['active'] === '1') ? 'selected' : '' ?>>Ativos</option><option value="0" <?= ((string) $planFilters['active'] === '0') ? 'selected' : '' ?>>Inativos</option></select></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
  </div>
</div>

<div class="row g-4 mb-4">
  <?php if ($planItems === []): ?>
    <div class="col-12"><div class="card"><div class="card-body text-center text-body-secondary">Nenhum plano encontrado.</div></div></div>
  <?php endif; ?>
  <?php foreach ($planItems as $plan): ?>
    <?php
      $payload = json_encode([
          'id' => (int) $plan['id'],
          'plan_name' => (string) $plan['plan_name'],
          'slug' => (string) $plan['slug'],
          'price' => (string) $plan['price'],
          'currency' => (string) $plan['currency'],
          'billing_cycle' => (string) $plan['billing_cycle'],
          'profile_limit' => (int) ($plan['profile_limit'] ?? 1),
          'max_streams' => (int) $plan['max_streams'],
          'quality_level' => (string) $plan['quality_level'],
          'active' => (int) $plan['active'],
          'is_selectable' => (int) ($plan['is_selectable'] ?? 1),
          'requires_payment_method' => (int) ($plan['requires_payment_method'] ?? 0),
          'sort_order' => (int) ($plan['sort_order'] ?? 100),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h5 class="mb-1"><?= e((string) $plan['plan_name']) ?></h5>
              <small class="text-body-secondary">/<?= e((string) $plan['slug']) ?></small>
            </div>
            <?php if ($canManageBilling): ?>
            <div class="dropdown wp-actions-dropdown">
              <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
              <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                <a href="#" class="dropdown-item js-edit-plan" data-plan='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                <a href="#" class="dropdown-item text-danger js-delete-plan" data-id="<?= (int) $plan['id'] ?>" data-name="<?= e((string) $plan['plan_name']) ?>"><i class="ri ri-delete-bin-line me-2"></i>Excluir</a>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-end gap-2 mb-2">
            <h3 class="mb-0"><?= e((string) $plan['currency']) ?> <?= e(number_format((float) $plan['price'], 2, ',', '.')) ?></h3>
            <small class="text-body-secondary">/<?= strtolower((string) $plan['billing_cycle']) ?></small>
          </div>
          <div class="small text-body-secondary mb-2">Limite de perfis: <strong><?= (int) ($plan['profile_limit'] ?? 1) ?></strong> | Streams: <strong><?= (int) $plan['max_streams'] ?></strong> | Qualidade: <strong><?= e((string) $plan['quality_level']) ?></strong></div>
          <span class="badge <?= ((int) $plan['active'] === 1) ? 'bg-label-success' : 'bg-label-warning' ?>"><?= ((int) $plan['active'] === 1) ? 'Ativo' : 'Inativo' ?></span>
          <span class="badge <?= ((int) ($plan['is_selectable'] ?? 1) === 1) ? 'bg-label-info' : 'bg-label-warning' ?> ms-1"><?= ((int) ($plan['is_selectable'] ?? 1) === 1) ? 'Selecionavel' : 'Nao selecionavel' ?></span>
          <span class="badge <?= ((int) ($plan['requires_payment_method'] ?? 0) === 1) ? 'bg-label-primary' : 'bg-label-secondary' ?> ms-1"><?= ((int) ($plan['requires_payment_method'] ?? 0) === 1) ? 'Pagamento obrigatorio' : 'Sem pagamento' ?></span>
          <span class="badge bg-label-info ms-1">Assinantes: <?= (int) ($plan['subscribers_count'] ?? 0) ?></span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="wp-card-heading">
      <span class="wp-card-heading-icon"><i class="ri ri-user-settings-line"></i></span>
      <div class="wp-card-heading-copy">
        <h5 class="mb-0">Subscriptions</h5>
      </div>
    </div>
    <?php if ($canManageBilling): ?>
      <button class="btn btn-outline-primary btn-sm js-open-modal" data-target="#subscription-assign-modal"><i class="ri ri-user-settings-line me-1"></i>Atribuir/alterar</button>
    <?php endif; ?>
  </div>
  <div class="card-body border-bottom">
    <form method="get" action="/admin/billing" class="row g-3">
      <div class="col-md-4"><input class="form-control" name="sub_q" value="<?= e((string) $subFilters['q']) ?>" placeholder="Buscar assinaturas"></div>
      <div class="col-md-3"><select class="form-select" name="sub_status"><option value="">Todos os status</option><?php foreach ($statusOptions as $status): ?><option value="<?= e($status) ?>" <?= ((string) $subFilters['status'] === (string) $status) ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select" name="sub_plan_id"><option value="0">Todos os planos</option><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>" <?= ((int) $subFilters['plan_id'] === (int) $plan['id']) ? 'selected' : '' ?>><?= e((string) $plan['plan_name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead><tr><th>Usuario</th><th>Plano</th><th>Status</th><th>Pagamento</th><th>Inicio</th><th>Fim</th><th>Auto renew</th></tr></thead>
      <tbody>
      <?php if ($subItems === []): ?>
        <tr><td colspan="7" class="text-center text-body-secondary">Nenhuma assinatura encontrada.</td></tr>
      <?php endif; ?>
      <?php foreach ($subItems as $sub): ?>
        <tr>
          <td><div><?= e((string) $sub['user_name']) ?></div><small class="text-body-secondary"><?= e((string) $sub['user_email']) ?></small></td>
          <td><?= e((string) $sub['plan_name']) ?> <small class="text-body-secondary">(<?= e((string) $sub['billing_cycle']) ?>)</small></td>
          <td><span class="badge bg-label-info"><?= e((string) $sub['status']) ?></span></td>
          <td><span class="badge bg-label-secondary"><?= e((string) ($sub['payment_status'] ?? 'NOT_REQUIRED')) ?></span></td>
          <td><?= e((string) date('d/m/Y', strtotime((string) $sub['start_at']))) ?></td>
          <td><?= e((string) ($sub['end_at'] ? date('d/m/Y', strtotime((string) $sub['end_at'])) : '-')) ?></td>
          <td><?= ((int) $sub['auto_renew'] === 1) ? 'Sim' : 'Nao' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canManageBilling): ?>
<div class="modal fade" id="plan-create-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Novo plano</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="plan-create"></div>
    <form id="plan-create-form" class="js-ajax-form" data-endpoint="/admin/billing/plans/create" data-errors-key="plan-create">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="plan_name" required></div>
        <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" required></div>
        <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="price" type="number" min="0" step="0.01" required></div>
        <div class="col-md-2"><label class="form-label">Moeda</label><input class="form-control" name="currency" value="BRL"></div>
        <div class="col-md-3"><label class="form-label">Ciclo</label><select class="form-select" name="billing_cycle"><?php foreach ($cycleOptions as $cycle): ?><option value="<?= e($cycle) ?>"><?= e($cycle) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Limite perfis</label><input class="form-control" name="profile_limit" type="number" min="1" max="5" value="1"></div>
        <div class="col-md-2"><label class="form-label">Streams</label><input class="form-control" name="max_streams" type="number" min="1" value="1"></div>
        <div class="col-md-3"><label class="form-label">Qualidade</label><input class="form-control" name="quality_level" value="1080p"></div>
        <div class="col-md-3"><label class="form-label">Ativo</label><select class="form-select" name="active"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        <div class="col-md-3"><label class="form-label">Selecionavel no publico</label><select class="form-select" name="is_selectable"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        <div class="col-md-3"><label class="form-label">Requer pagamento</label><select class="form-select" name="requires_payment_method"><option value="0">Nao</option><option value="1">Sim</option></select></div>
        <div class="col-md-3"><label class="form-label">Ordem</label><input class="form-control" name="sort_order" type="number" min="1" max="9999" value="100"></div>
      </div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#plan-create-form">Criar</button></div>
</div></div></div>

<div class="modal fade" id="plan-edit-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Editar plano</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="plan-edit"></div>
    <form id="plan-edit-form" class="js-ajax-form" data-endpoint="/admin/billing/plans/update" data-errors-key="plan-edit">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <input type="hidden" name="id" id="plan-e-id">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="plan_name" id="plan-e-name" required></div>
        <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" name="slug" id="plan-e-slug" required></div>
        <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="price" id="plan-e-price" type="number" min="0" step="0.01" required></div>
        <div class="col-md-2"><label class="form-label">Moeda</label><input class="form-control" name="currency" id="plan-e-currency"></div>
        <div class="col-md-3"><label class="form-label">Ciclo</label><select class="form-select" name="billing_cycle" id="plan-e-cycle"><?php foreach ($cycleOptions as $cycle): ?><option value="<?= e($cycle) ?>"><?= e($cycle) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Limite perfis</label><input class="form-control" name="profile_limit" id="plan-e-profile-limit" type="number" min="1" max="5"></div>
        <div class="col-md-2"><label class="form-label">Streams</label><input class="form-control" name="max_streams" id="plan-e-max-streams" type="number" min="1"></div>
        <div class="col-md-3"><label class="form-label">Qualidade</label><input class="form-control" name="quality_level" id="plan-e-quality"></div>
        <div class="col-md-3"><label class="form-label">Ativo</label><select class="form-select" name="active" id="plan-e-active"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        <div class="col-md-3"><label class="form-label">Selecionavel no publico</label><select class="form-select" name="is_selectable" id="plan-e-selectable"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        <div class="col-md-3"><label class="form-label">Requer pagamento</label><select class="form-select" name="requires_payment_method" id="plan-e-requires-payment"><option value="0">Nao</option><option value="1">Sim</option></select></div>
        <div class="col-md-3"><label class="form-label">Ordem</label><input class="form-control" name="sort_order" id="plan-e-sort-order" type="number" min="1" max="9999"></div>
      </div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#plan-edit-form">Salvar</button></div>
</div></div></div>

<div class="modal fade" id="subscription-assign-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Atribuir assinatura</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="form-errors text-danger small mb-2" data-form-errors="subscription-assign"></div>
    <form id="subscription-assign-form" class="js-ajax-form" data-endpoint="/admin/billing/subscriptions/assign" data-errors-key="subscription-assign">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <div class="mb-3"><label class="form-label">Usuario</label><select class="form-select" name="user_id" required><?php foreach ($userOptions as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e((string) $user['name']) ?> (<?= e((string) $user['email']) ?>)</option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Plano</label><select class="form-select" name="plan_id" required><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e((string) $plan['plan_name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($statusOptions as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Data final (opcional)</label><input class="form-control" type="date" name="end_at"></div>
      <div class="mb-0"><label class="form-label">Auto renew</label><select class="form-select" name="auto_renew"><option value="1">Sim</option><option value="0">Nao</option></select></div>
    </form>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#subscription-assign-form">Salvar</button></div>
</div></div></div>
<?php endif; ?>
