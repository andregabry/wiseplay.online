<?php
$producer = $producer ?? ['brand_name' => 'Producer', 'slug' => 'producer'];
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'type' => '', 'status' => ''];
$types = $types ?? ['MOVIE', 'SERIES', 'COURSE'];
$statuses = $statuses ?? ['DRAFT', 'PUBLISHED', 'ARCHIVED'];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1, 'total' => 0];

$assetOrFallback = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return '/assets/materialize-admin/img/avatars/1.png';
    }

    return $value;
};

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/producer/titles?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'type' => (string) ($filters['type'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'page' => $pageNum,
    ]);
};
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-stretch">
      <div class="col-xl-8">
        <div class="border rounded-4 p-4 h-100 bg-label-primary-subtle">
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="avatar avatar-md bg-label-primary rounded-circle"><i class="ri ri-store-3-line"></i></span>
            <div>
              <span class="text-uppercase text-body-secondary small d-block">Tenant atual</span>
              <h4 class="mb-1"><?= e((string) ($producer['brand_name'] ?? 'Producer')) ?></h4>
              <p class="mb-0 text-body-secondary">Slug publico: /producer/<?= e((string) ($producer['slug'] ?? 'producer')) ?></p>
            </div>
          </div>
          <p class="mb-0 text-body-secondary">Escopo aplicado no servidor: toda query desta tela usa <code>producer_id = <?= (int) ($producer['id'] ?? 0) ?></code>. Isso impede acesso cruzado entre produtores.</p>
        </div>
      </div>
      <div class="col-sm-6 col-xl-2">
        <div class="card border shadow-none h-100 mb-0">
          <div class="card-body">
            <span class="text-body-secondary small text-uppercase d-block mb-2">Titulos</span>
            <h3 class="mb-1"><?= (int) ($pagination['total'] ?? 0) ?></h3>
            <p class="mb-0 text-body-secondary">Visiveis neste tenant</p>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-2">
        <div class="card border shadow-none h-100 mb-0">
          <div class="card-body">
            <span class="text-body-secondary small text-uppercase d-block mb-2">Acesso</span>
            <h6 class="mb-1">PRODUCER</h6>
            <p class="mb-0 text-body-secondary">CRUD scoping pronto para ofertas, media e clientes.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div>
        <h5 class="mb-1">Meu catalogo</h5>
        <p class="mb-0 text-body-secondary">Listagem minima da Fase 1, isolada por produtor.</p>
      </div>
    </div>

    <form method="get" action="/admin/producer/titles" class="row g-3 mb-4">
      <div class="col-md-5"><input class="form-control" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por nome, slug ou descricao"></div>
      <div class="col-md-3"><select class="form-select" name="type"><option value="">Todos os tipos</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ((string) $filters['type'] === (string) $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ((string) $filters['status'] === (string) $status) ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>

    <div class="table-responsive text-nowrap">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Titulo</th>
            <th>Tipo</th>
            <th>Status</th>
            <th>Genero</th>
            <th>Midias</th>
            <th>Atualizado</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($items === []): ?>
          <tr><td colspan="6" class="text-center text-body-secondary py-5">Nenhum titulo encontrado para este produtor.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-md"><img src="<?= e($assetOrFallback((string) ($item['cover_image'] ?? ''))) ?>" class="rounded" alt="cover"></div>
                <div>
                  <div class="fw-medium"><?= e((string) ($item['title_name'] ?? '')) ?></div>
                  <small class="text-body-secondary">/<?= e((string) ($item['slug'] ?? '')) ?></small>
                </div>
              </div>
            </td>
            <td><span class="badge bg-label-info"><?= e((string) ($item['type'] ?? '')) ?></span></td>
            <td><span class="badge <?= ((string) ($item['status'] ?? '') === 'PUBLISHED') ? 'bg-label-success' : ((((string) ($item['status'] ?? '') === 'ARCHIVED')) ? 'bg-label-secondary' : 'bg-label-warning') ?>"><?= e((string) ($item['status'] ?? '')) ?></span></td>
            <td><?= e((string) ($item['genre'] ?? '-')) ?></td>
            <td><?= (int) ($item['media_count'] ?? 0) ?></td>
            <td><?= e((string) ($item['updated_at'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ((int) ($pagination['lastPage'] ?? 1) > 1): ?>
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
