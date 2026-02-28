<?php
$stats = $stats ?? [];
$recentAudits = $recentAudits ?? [];
$oneDriveQuota = $oneDriveQuota ?? [];
$canManageOneDrive = $canManageOneDrive ?? false;
?>
<div class="row g-4">
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Usuarios</span>
          <span class="kpi-icon bg-label-primary"><i class="ri ri-user-line"></i></span>
        </div>
        <h4 class="stat-value"><?= (int) ($stats['users_total'] ?? 0) ?></h4>
        <small class="text-body-secondary d-block">Ativos: <?= (int) ($stats['users_active'] ?? 0) ?></small>
        <span class="kpi-chip up mt-2"><i class="ri ri-arrow-up-line"></i> +12%</span>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Perfis</span>
          <span class="kpi-icon bg-label-warning"><i class="ri ri-team-line"></i></span>
        </div>
        <h4 class="stat-value"><?= (int) ($stats['profiles_total'] ?? 0) ?></h4>
        <small class="text-body-secondary d-block">Todos os tipos</small>
        <span class="kpi-chip up mt-2"><i class="ri ri-arrow-up-line"></i> +8%</span>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Titulos</span>
          <span class="kpi-icon bg-label-info"><i class="ri ri-movie-2-line"></i></span>
        </div>
        <h4 class="stat-value"><?= (int) ($stats['titles_published'] ?? 0) ?></h4>
        <small class="text-body-secondary d-block">Publicados</small>
        <span class="kpi-chip up mt-2"><i class="ri ri-arrow-up-line"></i> +6%</span>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Midias</span>
          <span class="kpi-icon bg-label-success"><i class="ri ri-film-line"></i></span>
        </div>
        <h4 class="stat-value"><?= (int) ($stats['media_published'] ?? 0) ?></h4>
        <small class="text-body-secondary d-block">Publicadas</small>
        <span class="kpi-chip up mt-2"><i class="ri ri-arrow-up-line"></i> +4%</span>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Assinaturas</span>
          <span class="kpi-icon bg-label-secondary"><i class="ri ri-bank-card-line"></i></span>
        </div>
        <h4 class="stat-value"><?= (int) ($stats['subscriptions_active'] ?? 0) ?></h4>
        <small class="text-body-secondary d-block">Ativas/trial</small>
        <span class="kpi-chip down mt-2"><i class="ri ri-arrow-down-line"></i> -2%</span>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card dashboard-kpi">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="stat-label">Auditoria</span>
          <span class="kpi-icon bg-label-danger"><i class="ri ri-shield-check-line"></i></span>
        </div>
        <h4 class="stat-value"><?= count($recentAudits) ?></h4>
        <small class="text-body-secondary d-block">Eventos recentes</small>
        <span class="kpi-chip up mt-2"><i class="ri ri-pulse-line"></i> live</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mt-0">
  <div class="col-12">
    <div class="card wp-onedrive-quota-widget" data-onedrive-quota-widget>
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
          <div>
            <h5 class="mb-1">OneDrive Storage</h5>
            <p class="text-body-secondary mb-0">Quota atual da conta conectada no Microsoft Graph.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge <?= e((string) ($oneDriveQuota['state_badge_class'] ?? 'bg-label-secondary')) ?>" data-quota-field="state_badge">
              <span data-quota-field="state_label"><?= e((string) ($oneDriveQuota['state_label'] ?? 'Unknown')) ?></span>
            </span>
            <?php if ($canManageOneDrive): ?>
              <button type="button" class="btn btn-outline-info btn-sm js-onedrive-quota-refresh"><i class="ri ri-refresh-line me-1"></i>Refresh quota</button>
            <?php endif; ?>
          </div>
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

        <div class="row g-3 mb-2">
          <div class="col-md-4 col-12">
            <div class="wp-quota-metric-card">
              <small class="wp-quota-metric-label">Total</small>
              <h6 class="mb-0" data-quota-field="total_human"><?= e((string) ($oneDriveQuota['total_human'] ?? '-')) ?></h6>
            </div>
          </div>
          <div class="col-md-4 col-12">
            <div class="wp-quota-metric-card">
              <small class="wp-quota-metric-label">Used</small>
              <h6 class="mb-0" data-quota-field="used_human_secondary"><?= e((string) ($oneDriveQuota['used_human'] ?? '-')) ?></h6>
            </div>
          </div>
          <div class="col-md-4 col-12">
            <div class="wp-quota-metric-card">
              <small class="wp-quota-metric-label">Remaining</small>
              <h6 class="mb-0" data-quota-field="remaining_human"><?= e((string) ($oneDriveQuota['remaining_human'] ?? '-')) ?></h6>
            </div>
          </div>
        </div>

        <div class="small text-success fw-medium" data-quota-field="plan_hint"><?= e((string) ($oneDriveQuota['plan_hint'] ?? '')) ?></div>
        <div class="small text-body-secondary" data-quota-field="message"><?= e((string) ($oneDriveQuota['message'] ?? '')) ?></div>
        <div class="small text-body-secondary mt-1">Last updated: <span data-quota-field="last_updated"><?= e((string) ($oneDriveQuota['last_updated'] ?? '-')) ?></span></div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="mb-0">Auditoria recente</h5>
    <span class="badge bg-label-primary">Live</span>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>Data</th>
          <th>Ator</th>
          <th>Acao</th>
          <th>Entidade</th>
          <th>ID</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentAudits === []): ?>
          <tr><td colspan="5" class="text-center text-body-secondary">Sem eventos recentes.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentAudits as $audit): ?>
          <tr>
            <td><?= e((string) $audit['created_at']) ?></td>
            <td><?= e((string) ($audit['actor_name'] ?? 'Sistema')) ?></td>
            <td><span class="badge bg-label-info"><?= e((string) $audit['action']) ?></span></td>
            <td><?= e((string) $audit['entity_type']) ?></td>
            <td><?= e((string) ($audit['entity_id'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
