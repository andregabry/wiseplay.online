<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'type' => '', 'status' => '', 'genre' => ''];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1];
$types = $types ?? ['MOVIE', 'SERIES', 'COURSE'];
$statuses = $statuses ?? ['DRAFT', 'PUBLISHED', 'ARCHIVED'];
$genres = $genres ?? [];
$planOptions = $planOptions ?? [];
$defaultPlanId = $defaultPlanId ?? 0;
$canManageTitles = $canManageTitles ?? false;
$assetOrFallback = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return '/assets/materialize-admin/img/avatars/1.png';
    }
    return $value;
};

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/titles?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'type' => (string) ($filters['type'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'genre' => (string) ($filters['genre'] ?? ''),
        'page' => $pageNum,
    ]);
};
?>

<datalist id="title-genre-list">
  <?php foreach ($genres as $genre): ?>
    <option value="<?= e((string) $genre) ?>"></option>
  <?php endforeach; ?>
</datalist>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-movie-2-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Titles Management</h5>
          <p class="text-body-secondary mb-0">Catalogo com filmes, series e cursos.</p>
        </div>
      </div>
      <?php if ($canManageTitles): ?>
        <button class="btn btn-primary js-open-modal" data-target="#title-create-modal"><i class="ri ri-add-circle-line me-1"></i>Novo titulo</button>
      <?php endif; ?>
    </div>

    <form method="get" action="/admin/titles" class="row g-3">
      <div class="col-md-4"><input class="form-control" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por nome, slug ou descricao"></div>
      <div class="col-md-2"><select class="form-select" name="type"><option value="">Todos os tipos</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ((string) $filters['type'] === (string) $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ((string) $filters['status'] === (string) $status) ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><input class="form-control" name="genre" value="<?= e((string) $filters['genre']) ?>" list="title-genre-list" placeholder="Genero"></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
    <div class="table-responsive text-nowrap mt-4 pt-3 border-top">
    <table class="table">
      <thead>
        <tr>
          <th>Titulo</th>
          <th>Tipo</th>
          <th>Genero</th>
          <th>Plano</th>
          <th>Ano</th>
          <th>Status</th>
          <th>Featured</th>
          <th>Midias</th>
          <?php if ($canManageTitles): ?><th>Acoes</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($items === []): ?>
        <tr><td colspan="<?= $canManageTitles ? '9' : '8' ?>" class="text-center text-body-secondary">Nenhum titulo encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <?php
          $payload = json_encode([
              'id' => (int) $item['id'],
              'title_name' => (string) $item['title_name'],
              'slug' => (string) $item['slug'],
              'type' => (string) $item['type'],
              'billing_plan_id' => (int) ($item['billing_plan_id'] ?? 0),
              'billing_plan_name' => (string) ($item['billing_plan_name'] ?? ''),
              'billing_plan_cycle' => (string) ($item['billing_plan_cycle'] ?? ''),
              'description' => (string) ($item['description'] ?? ''),
              'genre' => (string) ($item['genre'] ?? ''),
              'year' => (int) ($item['year'] ?? 0),
              'maturity_rating' => (string) ($item['maturity_rating'] ?? ''),
              'featured' => (int) ($item['featured'] ?? 0),
              'status' => (string) ($item['status'] ?? 'DRAFT'),
              'cover_image' => (string) ($item['cover_image'] ?? ''),
              'backdrop_image' => (string) ($item['backdrop_image'] ?? ''),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="avatar avatar-sm"><img src="<?= e($assetOrFallback((string) ($item['cover_image'] ?? ''))) ?>" class="rounded" alt="cover"></div>
              <div>
                <div class="fw-medium"><?= e((string) $item['title_name']) ?></div>
                <small class="text-body-secondary">/<?= e((string) $item['slug']) ?></small>
              </div>
            </div>
          </td>
          <td><span class="badge bg-label-info"><?= e((string) $item['type']) ?></span></td>
          <td><?= e((string) $item['genre']) ?></td>
          <td>
            <div class="fw-medium"><?= e((string) ($item['billing_plan_name'] ?? 'Free')) ?></div>
            <small class="text-body-secondary"><?= e((string) ($item['billing_plan_cycle'] ?? 'MONTHLY')) ?></small>
          </td>
          <td><?= e((string) ($item['year'] ?: '-')) ?></td>
          <td><span class="badge <?= ((string) $item['status'] === 'PUBLISHED') ? 'bg-label-success' : (((string) $item['status'] === 'ARCHIVED') ? 'bg-label-secondary' : 'bg-label-warning') ?>"><?= e((string) $item['status']) ?></span></td>
          <td><?= ((int) $item['featured'] === 1) ? 'Sim' : 'Nao' ?></td>
          <td><?= (int) ($item['media_count'] ?? 0) ?></td>
          <?php if ($canManageTitles): ?>
          <td>
            <div class="dropdown wp-actions-dropdown">
              <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
              <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                <a href="#" class="dropdown-item js-edit-title" data-title='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                <a href="#" class="dropdown-item text-danger js-delete-title" data-id="<?= (int) $item['id'] ?>" data-name="<?= e((string) $item['title_name']) ?>"><i class="ri ri-delete-bin-line me-2"></i>Excluir</a>
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
</div>

<?php if ((int) $pagination['lastPage'] > 1): ?>
<nav class="mt-4"><ul class="pagination">
<li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a></li>
<?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?><li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a></li><?php endfor; ?>
<li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a></li>
</ul></nav>
<?php endif; ?>

<?php if ($canManageTitles): ?>
<div class="modal fade" id="title-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Novo titulo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="title-create"></div>
      <form id="title-create-form" class="js-ajax-form" data-endpoint="/admin/titles/create" data-errors-key="title-create" data-has-files="1" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Nome</label><input class="form-control" name="title_name" required></div>
          <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" required></div>
          <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="type"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-12"><label class="form-label">Descricao</label><textarea class="form-control" name="description" rows="3"></textarea></div>
          <div class="col-md-3"><label class="form-label">Genero</label><input class="form-control" name="genre" list="title-genre-list" value="General"></div>
          <div class="col-md-3"><label class="form-label">Plano</label><select class="form-select" name="billing_plan_id"><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>" <?= (int) $plan['id'] === (int) $defaultPlanId ? 'selected' : '' ?>><?= e((string) $plan['plan_name']) ?> (<?= e((string) $plan['billing_cycle']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Ano</label><input class="form-control" name="year" type="number" min="1900" max="2100"></div>
          <div class="col-md-2"><label class="form-label">Maturidade</label><input class="form-control" name="maturity_rating" value="16"></div>
          <div class="col-md-2"><label class="form-label">Featured</label><select class="form-select" name="featured"><option value="0">Nao</option><option value="1">Sim</option></select></div>
          <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $status === 'DRAFT' ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Cover URL (opcional)</label><input class="form-control" name="cover_image"></div>
          <div class="col-md-6"><label class="form-label">Backdrop URL (opcional)</label><input class="form-control" name="backdrop_image"></div>
          <div class="col-md-6"><label class="form-label">Upload cover</label><input class="form-control" type="file" name="cover_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-6"><label class="form-label">Upload backdrop</label><input class="form-control" type="file" name="backdrop_file" accept="image/png,image/jpeg,image/webp"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#title-create-form">Criar</button></div>
  </div></div>
</div>

<div class="modal fade" id="title-edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Editar titulo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="title-edit"></div>
      <form id="title-edit-form" class="js-ajax-form" data-endpoint="/admin/titles/update" data-errors-key="title-edit" data-has-files="1" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <input type="hidden" name="id" id="t-e-id">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Nome</label><input class="form-control" name="title_name" id="t-e-title-name" required></div>
          <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" id="t-e-slug" required></div>
          <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="type" id="t-e-type"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-12"><label class="form-label">Descricao</label><textarea class="form-control" name="description" id="t-e-description" rows="3"></textarea></div>
          <div class="col-md-3"><label class="form-label">Genero</label><input class="form-control" name="genre" id="t-e-genre" list="title-genre-list"></div>
          <div class="col-md-3"><label class="form-label">Plano</label><select class="form-select" name="billing_plan_id" id="t-e-billing-plan-id"><?php foreach ($planOptions as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e((string) $plan['plan_name']) ?> (<?= e((string) $plan['billing_cycle']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Ano</label><input class="form-control" name="year" id="t-e-year" type="number" min="1900" max="2100"></div>
          <div class="col-md-2"><label class="form-label">Maturidade</label><input class="form-control" name="maturity_rating" id="t-e-maturity-rating"></div>
          <div class="col-md-2"><label class="form-label">Featured</label><select class="form-select" name="featured" id="t-e-featured"><option value="0">Nao</option><option value="1">Sim</option></select></div>
          <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status" id="t-e-status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Cover URL</label><input class="form-control" name="cover_image" id="t-e-cover-image"></div>
          <div class="col-md-6"><label class="form-label">Backdrop URL</label><input class="form-control" name="backdrop_image" id="t-e-backdrop-image"></div>
          <div class="col-md-6"><label class="form-label">Upload cover</label><input class="form-control" type="file" name="cover_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-6"><label class="form-label">Upload backdrop</label><input class="form-control" type="file" name="backdrop_file" accept="image/png,image/jpeg,image/webp"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#title-edit-form">Salvar</button></div>
  </div></div>
</div>
<?php endif; ?>
