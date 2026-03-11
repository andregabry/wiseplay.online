<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'role' => '', 'status' => ''];
$pagination = $pagination ?? ['page' => 1, 'perPage' => 9, 'total' => 0, 'lastPage' => 1];
$roleOptions = $roleOptions ?? [];
$statusOptions = $statusOptions ?? [];
$planOptions = $planOptions ?? [];
$canManageUsers = $canManageUsers ?? false;

$avatarFallback = '/assets/img/avatar-library/avatar-01.svg';
$avatarOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/users?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'role' => (string) ($filters['role'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'page' => $pageNum,
    ]);
};
?>

<div class="card" data-users-module>
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-user-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Users Management</h5>
          <p class="text-body-secondary mb-0">Contas, avatar de conta, plano, status e perfis criados.</p>
        </div>
      </div>
      <?php if ($canManageUsers): ?>
        <button class="btn btn-primary js-open-modal" data-target="#user-create-modal">
          <i class="ri ri-user-add-line me-1"></i>Novo usuario
        </button>
      <?php endif; ?>
    </div>

    <form method="get" action="/admin/users" class="row g-3 mb-4">
      <div class="col-md-5">
        <input class="form-control" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por nome ou email">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="role">
          <option value="">Todos os papeis</option>
          <?php foreach ($roleOptions as $role): ?>
            <option value="<?= e($role) ?>" <?= ((string) $filters['role'] === (string) $role) ? 'selected' : '' ?>><?= e($role) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="status">
          <option value="">Todos os status</option>
          <?php foreach ($statusOptions as $status): ?>
            <option value="<?= e($status) ?>" <?= ((string) $filters['status'] === (string) $status) ? 'selected' : '' ?>><?= e($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
      </div>
    </form>

    <?php if ($items === []): ?>
      <div class="text-center text-body-secondary py-5">Nenhum usuario encontrado.</div>
    <?php else: ?>
      <?php if ($canManageUsers): ?>
        <div class="wp-bulk-toolbar" data-bulk-toolbar data-endpoint="/admin/users/bulk-delete">
          <div class="form-check">
            <input class="form-check-input js-users-select-all" type="checkbox" value="1" id="users-select-all">
            <label class="form-check-label" for="users-select-all">Selecionar todos desta pagina</label>
          </div>
          <div class="wp-bulk-toolbar-copy">
            <strong class="js-users-selected-count">0</strong>
            <span>usuario(s) selecionado(s)</span>
          </div>
          <button type="button" class="btn btn-semantic-danger js-users-bulk-delete" disabled>
            <i class="ri ri-delete-bin-line me-1"></i>Excluir selecionados
          </button>
        </div>
      <?php endif; ?>
      <div class="table-responsive wp-users-table-wrap">
        <table class="table align-middle wp-users-table">
          <thead>
            <tr>
              <?php if ($canManageUsers): ?><th class="wp-users-checkbox-col"></th><?php endif; ?>
              <th>Usuario</th>
              <th>Role</th>
              <th>Status</th>
              <th>Plano</th>
              <th>Perfis</th>
              <th>Criado em</th>
              <th>Ultimo login</th>
              <?php if ($canManageUsers): ?><th class="text-end">Acoes</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $user): ?>
              <?php
                $userId = (int) $user['id'];
                $isActive = ((string) $user['status'] === 'ACTIVE');
                $avatar = $avatarOrFallback((string) ($user['account_avatar'] ?? ''), $avatarFallback);
                $payload = json_encode([
                    'id' => $userId,
                    'name' => (string) $user['name'],
                    'email' => (string) $user['email'],
                    'account_avatar' => (string) ($user['account_avatar'] ?? ''),
                    'role' => (string) $user['role'],
                    'status' => (string) $user['status'],
                    'plan_id' => (int) ($user['subscription_plan_id'] ?? 0),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
              ?>
              <tr>
                <?php if ($canManageUsers): ?>
                  <td class="wp-users-checkbox-col">
                    <div class="form-check">
                      <input
                        class="form-check-input js-user-row-checkbox"
                        type="checkbox"
                        value="<?= $userId ?>"
                        data-name="<?= e((string) $user['name']) ?>"
                        aria-label="Selecionar <?= e((string) $user['name']) ?>">
                    </div>
                  </td>
                <?php endif; ?>
                <td>
                  <div class="wp-user-cell">
                    <div class="avatar avatar-lg wp-user-avatar">
                      <img src="<?= e($avatar) ?>" alt="avatar conta" class="rounded-circle" onerror="this.src='<?= e($avatarFallback) ?>'">
                    </div>
                    <div class="wp-user-cell-copy">
                      <strong><?= e((string) $user['name']) ?></strong>
                      <span><?= e((string) $user['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td><span class="badge bg-label-info"><?= e((string) $user['role']) ?></span></td>
                <td><span class="badge <?= $isActive ? 'bg-label-success' : 'bg-label-warning' ?>"><?= $isActive ? 'ACTIVE' : 'DISABLED' ?></span></td>
                <td>
                  <div class="wp-user-table-meta">
                    <strong><?= e((string) ($user['subscription_plan'] ?: 'Sem plano')) ?></strong>
                    <small><?= e((string) ($user['subscription_status'] ?: 'Sem assinatura')) ?></small>
                  </div>
                </td>
                <td><span class="badge bg-label-primary"><?= (int) ($user['profiles_count'] ?? 0) ?></span></td>
                <td>
                  <div class="wp-user-table-meta">
                    <strong><?= e((string) date('d/m/Y', strtotime((string) $user['created_at']))) ?></strong>
                    <small><?= e((string) date('H:i', strtotime((string) $user['created_at']))) ?></small>
                  </div>
                </td>
                <td>
                  <div class="wp-user-table-meta">
                    <strong><?= e((string) ($user['last_login_at'] ? date('d/m/Y', strtotime((string) $user['last_login_at'])) : 'Nunca')) ?></strong>
                    <small><?= e((string) ($user['last_login_at'] ? date('H:i', strtotime((string) $user['last_login_at'])) : 'Sem acesso')) ?></small>
                  </div>
                </td>
                <?php if ($canManageUsers): ?>
                  <td class="text-end wp-actions-cell">
                    <div class="dropdown wp-actions-dropdown">
                      <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
                      <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                        <a href="#" class="dropdown-item js-view-user" data-user='<?= e($payload) ?>'><i class="ri ri-eye-line me-2"></i>Detalhes</a>
                        <a href="#" class="dropdown-item js-edit-user" data-user='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                        <a href="#" class="dropdown-item js-toggle-user" data-id="<?= $userId ?>" data-next="<?= $isActive ? 'DISABLED' : 'ACTIVE' ?>" data-name="<?= e((string) $user['name']) ?>">
                          <i class="ri ri-repeat-line me-2"></i><?= $isActive ? 'Desativar' : 'Ativar' ?>
                        </a>
                        <a href="#" class="dropdown-item text-danger js-delete-user" data-id="<?= $userId ?>" data-name="<?= e((string) $user['name']) ?>">
                          <i class="ri ri-delete-bin-line me-2"></i>Excluir
                        </a>
                      </div>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ((int) $pagination['lastPage'] > 1): ?>
  <nav class="mt-4">
    <ul class="pagination">
      <li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a>
      </li>
      <?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?>
        <li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>">
          <a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<?php if ($canManageUsers): ?>
<div class="modal fade" id="user-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Novo usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="form-errors text-danger small mb-2" data-form-errors="user-create"></div>
        <form id="user-create-form" class="js-ajax-form" data-endpoint="/admin/users/create" data-errors-key="user-create" data-has-files="1" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="name" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div>
            <div class="col-md-6"><label class="form-label">Senha</label><input class="form-control" name="password" type="password" required></div>
            <div class="col-md-3"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach ($roleOptions as $role): ?><option value="<?= e($role) ?>" <?= $role === 'CUSTOMER' ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="ACTIVE">ACTIVE</option><option value="DISABLED">DISABLED</option></select></div>
            <div class="col-md-6"><label class="form-label">Plano</label><select class="form-select" name="plan_id" required><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e((string) $plan['plan_name']) ?> (limite <?= (int) ($plan['profile_limit'] ?? 0) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Avatar da conta (URL opcional)</label><input class="form-control" name="account_avatar" placeholder="https://... ou /assets/..."></div>
            <div class="col-md-6"><label class="form-label">Upload avatar da conta</label><input class="form-control" type="file" name="account_avatar_file" accept="image/png,image/jpeg,image/webp"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#user-create-form">Criar</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="user-edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Editar usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="form-errors text-danger small mb-2" data-form-errors="user-edit"></div>
        <form id="user-edit-form" class="js-ajax-form" data-endpoint="/admin/users/update" data-errors-key="user-edit" data-has-files="1" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <input type="hidden" name="id" id="u-e-id">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="name" id="u-e-name" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" id="u-e-email" type="email" required></div>
            <div class="col-md-6"><label class="form-label">Nova senha (opcional)</label><input class="form-control" name="password" type="password"></div>
            <div class="col-md-3"><label class="form-label">Role</label><select class="form-select" name="role" id="u-e-role"><?php foreach ($roleOptions as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status" id="u-e-status"><option value="ACTIVE">ACTIVE</option><option value="DISABLED">DISABLED</option></select></div>
            <div class="col-md-6"><label class="form-label">Plano</label><select class="form-select" name="plan_id" id="u-e-plan-id" required><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e((string) $plan['plan_name']) ?> (limite <?= (int) ($plan['profile_limit'] ?? 0) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Avatar da conta (URL)</label><input class="form-control" name="account_avatar" id="u-e-account-avatar"></div>
            <div class="col-md-6"><label class="form-label">Upload avatar da conta</label><input class="form-control" type="file" name="account_avatar_file" accept="image/png,image/jpeg,image/webp"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#user-edit-form">Salvar</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="user-view-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detalhes do usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p><strong>Nome:</strong> <span data-user-detail="name">-</span></p>
        <p><strong>Email:</strong> <span data-user-detail="email">-</span></p>
        <p><strong>Role:</strong> <span data-user-detail="role">-</span></p>
        <p><strong>Status:</strong> <span data-user-detail="status">-</span></p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
