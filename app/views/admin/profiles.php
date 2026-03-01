<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'user_id' => 0, 'is_kids' => ''];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1];
$userOptions = $userOptions ?? [];
$maturityOptions = $maturityOptions ?? ['Livre', '10', '12', '14', '16', '18'];
$canManageProfiles = $canManageProfiles ?? false;
$canCreateProfiles = $canCreateProfiles ?? false;

$avatarFallback = '/assets/img/avatar-library/avatar-01.svg';
$avatarOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/profiles?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'user_id' => (int) ($filters['user_id'] ?? 0),
        'is_kids' => (string) ($filters['is_kids'] ?? ''),
        'page' => $pageNum,
    ]);
};
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-team-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Profiles Management</h5>
          <p class="text-body-secondary mb-0">Perfis criados no app publico (adulto/kids) com visao global para suporte.</p>
        </div>
      </div>
      <?php if ($canCreateProfiles): ?>
        <button class="btn btn-primary js-open-modal" data-target="#profile-create-modal"><i class="ri ri-add-circle-line me-1"></i>Novo perfil (maintenance)</button>
      <?php endif; ?>
    </div>

    <?php if (!$canCreateProfiles): ?>
      <div class="alert alert-warning py-2">
        Criacao manual de perfil no admin esta desativada. Perfis sao criados no fluxo publico do usuario.
      </div>
    <?php endif; ?>

    <form method="get" action="/admin/profiles" class="row g-3">
      <div class="col-md-5"><input class="form-control" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por perfil, usuario ou email"></div>
      <div class="col-md-3">
        <select class="form-select" name="user_id">
          <option value="0">Todos os usuarios</option>
          <?php foreach ($userOptions as $user): ?>
            <option value="<?= (int) $user['id'] ?>" <?= ((int) $filters['user_id'] === (int) $user['id']) ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="is_kids">
          <option value="">Todos os tipos</option>
          <option value="0" <?= ((string) $filters['is_kids'] === '0') ? 'selected' : '' ?>>Adulto</option>
          <option value="1" <?= ((string) $filters['is_kids'] === '1') ? 'selected' : '' ?>>Kids</option>
        </select>
      </div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Filtrar</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>Perfil</th>
          <th>Usuario dono</th>
          <th>Tipo</th>
          <th>Maturidade</th>
          <th>Idioma</th>
          <th>Autoplay</th>
          <?php if ($canManageProfiles): ?><th>Acoes</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($items === []): ?>
          <tr><td colspan="<?= $canManageProfiles ? '7' : '6' ?>" class="text-center text-body-secondary">Nenhum perfil encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <?php
            $payload = json_encode([
                'id' => (int) $item['id'],
                'user_id' => (int) $item['user_id'],
                'user_name' => (string) $item['user_name'],
                'user_email' => (string) $item['user_email'],
                'profile_name' => (string) $item['profile_name'],
                'avatar_path' => (string) ($item['avatar_path'] ?? ''),
                'is_kids' => (int) $item['is_kids'],
                'maturity_rating' => (string) $item['maturity_rating'],
                'language' => (string) $item['language'],
                'autoplay' => (int) $item['autoplay'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
          ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm"><img src="<?= e($avatarOrFallback((string) ($item['avatar_path'] ?? ''), $avatarFallback)) ?>" class="rounded-circle" alt="avatar"></div>
                <span><?= e((string) $item['profile_name']) ?></span>
              </div>
            </td>
            <td>
              <div><?= e((string) $item['user_name']) ?></div>
              <small class="text-body-secondary"><?= e((string) $item['user_email']) ?></small>
            </td>
            <td><span class="badge <?= ((int) $item['is_kids'] === 1) ? 'bg-label-warning' : 'bg-label-primary' ?>"><?= ((int) $item['is_kids'] === 1) ? 'Kids' : 'Adulto' ?></span></td>
            <td><?= e((string) $item['maturity_rating']) ?></td>
            <td><?= e((string) $item['language']) ?></td>
            <td><?= ((int) $item['autoplay'] === 1) ? 'On' : 'Off' ?></td>
            <?php if ($canManageProfiles): ?>
            <td>
              <div class="dropdown wp-actions-dropdown">
                <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
                <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                  <a href="#" class="dropdown-item js-edit-profile" data-profile='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                  <a href="#" class="dropdown-item text-danger js-delete-profile" data-id="<?= (int) $item['id'] ?>" data-name="<?= e((string) $item['profile_name']) ?>"><i class="ri ri-delete-bin-line me-2"></i>Excluir</a>
                </div>
              </div>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ((int) $pagination['lastPage'] > 1): ?>
  <nav class="mt-4">
    <ul class="pagination">
      <li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a></li>
      <?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?>
        <li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a></li>
      <?php endfor; ?>
      <li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a></li>
    </ul>
  </nav>
<?php endif; ?>

<?php if ($canCreateProfiles): ?>
<div class="modal fade" id="profile-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Novo perfil (maintenance)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="form-errors text-danger small mb-2" data-form-errors="profile-create"></div>
        <form id="profile-create-form" class="js-ajax-form" data-endpoint="/admin/profiles/create" data-errors-key="profile-create" data-has-files="1" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Usuario</label><select class="form-select" name="user_id" required><?php foreach ($userOptions as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e((string) $user['name']) ?> (<?= e((string) $user['email']) ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Nome do perfil</label><input class="form-control" name="profile_name" required></div>
            <div class="col-md-6"><label class="form-label">Avatar URL (opcional)</label><input class="form-control" name="avatar_path" placeholder="/assets/img/avatar-library/avatar-01.svg"></div>
            <div class="col-md-6"><label class="form-label">Upload avatar (opcional)</label><input class="form-control" type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp"></div>
            <div class="col-md-3"><label class="form-label">Modo Kids</label><select class="form-select" name="is_kids"><option value="0">Nao</option><option value="1">Sim</option></select></div>
            <div class="col-md-3"><label class="form-label">Classificacao</label><select class="form-select" name="maturity_rating"><?php foreach ($maturityOptions as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Idioma</label><input class="form-control" name="language" value="pt-BR"></div>
            <div class="col-md-3"><label class="form-label">Autoplay</label><select class="form-select" name="autoplay"><option value="1">On</option><option value="0">Off</option></select></div>
          </div>
        </form>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#profile-create-form">Criar</button></div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canManageProfiles): ?>
<div class="modal fade" id="profile-edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Editar perfil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="form-errors text-danger small mb-2" data-form-errors="profile-edit"></div>
        <form id="profile-edit-form" class="js-ajax-form" data-endpoint="/admin/profiles/update" data-errors-key="profile-edit" data-has-files="1" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <input type="hidden" name="id" id="p-e-id">
          <input type="hidden" name="user_id" id="p-e-user">
          <div class="row g-3">
            <div class="col-md-12"><label class="form-label">Usuario dono</label><input class="form-control" id="p-e-owner" readonly></div>
            <div class="col-md-6"><label class="form-label">Nome do perfil</label><input class="form-control" name="profile_name" id="p-e-name" required></div>
            <div class="col-md-6"><label class="form-label">Avatar URL</label><input class="form-control" name="avatar_path" id="p-e-avatar"></div>
            <div class="col-md-6"><label class="form-label">Upload avatar</label><input class="form-control" type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp"></div>
            <div class="col-md-3"><label class="form-label">Modo Kids</label><select class="form-select" name="is_kids" id="p-e-kids"><option value="0">Nao</option><option value="1">Sim</option></select></div>
            <div class="col-md-3"><label class="form-label">Classificacao</label><select class="form-select" name="maturity_rating" id="p-e-maturity"><?php foreach ($maturityOptions as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Idioma</label><input class="form-control" name="language" id="p-e-language"></div>
            <div class="col-md-3"><label class="form-label">Autoplay</label><select class="form-select" name="autoplay" id="p-e-autoplay"><option value="1">On</option><option value="0">Off</option></select></div>
          </div>
        </form>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#profile-edit-form">Salvar</button></div>
    </div>
  </div>
</div>
<?php endif; ?>
