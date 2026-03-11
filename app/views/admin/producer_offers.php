<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'status' => '', 'offer_type' => '', 'title_id' => 0];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1];
$types = $types ?? ['ONE_TIME', 'SUBSCRIPTION'];
$intervals = $intervals ?? ['MONTH', 'YEAR'];
$statuses = $statuses ?? ['ACTIVE', 'INACTIVE'];
$titleOptions = $titleOptions ?? [];
$producer = $producer ?? ['brand_name' => 'Producer'];

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/producer/offers?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'offer_type' => (string) ($filters['offer_type'] ?? ''),
        'title_id' => (int) ($filters['title_id'] ?? 0),
        'page' => $pageNum,
    ]);
};

$offerLabel = static function (array $item): string {
    $type = strtoupper((string) ($item['offer_type'] ?? 'ONE_TIME'));
    $interval = strtoupper((string) ($item['billing_interval'] ?? ''));
    if ($type === 'ONE_TIME') {
        return 'Pagamento unico';
    }
    if ($interval === 'YEAR') {
        return 'Assinatura anual';
    }
    return 'Assinatura mensal';
};
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-price-tag-3-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Offers for <?= e((string) ($producer['brand_name'] ?? 'Producer')) ?></h5>
          <p class="text-body-secondary mb-0">Cada oferta fica vinculada a um unico titulo e controla o lock/unlock da experiencia publica.</p>
        </div>
      </div>
      <button class="btn btn-primary js-open-modal" data-target="#producer-offer-create-modal"><i class="ri ri-add-circle-line me-1"></i>Nova oferta</button>
    </div>

    <form method="get" action="/admin/producer/offers" class="row g-3">
      <div class="col-md-4"><input class="form-control" type="text" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por titulo ou moeda"></div>
      <div class="col-md-3"><select class="form-select" name="title_id"><option value="0">Todos os titulos</option><?php foreach ($titleOptions as $title): ?><option value="<?= (int) $title['id'] ?>" <?= ((int) $filters['title_id'] === (int) $title['id']) ? 'selected' : '' ?>><?= e((string) $title['title_name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="offer_type"><option value="">Todos os tipos</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ((string) $filters['offer_type'] === (string) $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="status"><option value="">Todos os status</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ((string) $filters['status'] === (string) $status) ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
  </div>
</div>

<div class="row g-4 mb-4">
  <?php if ($items === []): ?>
    <div class="col-12"><div class="card"><div class="card-body text-center text-body-secondary">Nenhuma oferta encontrada para este produtor.</div></div></div>
  <?php endif; ?>
  <?php foreach ($items as $item): ?>
    <?php
      $payload = json_encode([
          'id' => (int) $item['id'],
          'title_id' => (int) $item['title_id'],
          'title_name' => (string) ($item['title_name'] ?? ''),
          'offer_type' => (string) ($item['offer_type'] ?? 'ONE_TIME'),
          'billing_interval' => (string) ($item['billing_interval'] ?? ''),
          'currency' => (string) ($item['currency'] ?? 'BRL'),
          'price' => (string) ($item['price'] ?? '0.00'),
          'status' => (string) ($item['status'] ?? 'ACTIVE'),
          'is_selectable' => (int) ($item['is_selectable'] ?? 1),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
            <div>
              <h5 class="mb-1"><?= e((string) ($item['title_name'] ?? 'Titulo')) ?></h5>
              <small class="text-body-secondary"><?= e($offerLabel($item)) ?></small>
            </div>
            <div class="dropdown wp-actions-dropdown">
              <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
              <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                <a href="#" class="dropdown-item js-edit-producer-offer" data-offer='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                <a href="#" class="dropdown-item text-danger js-delete-producer-offer" data-id="<?= (int) $item['id'] ?>" data-name="<?= e((string) ($item['title_name'] ?? 'oferta')) ?>"><i class="ri ri-delete-bin-line me-2"></i>Excluir</a>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-end gap-2 mb-2">
            <h3 class="mb-0"><?= e((string) ($item['currency'] ?? 'BRL')) ?> <?= e(number_format((float) ($item['price'] ?? 0), 2, ',', '.')) ?></h3>
            <small class="text-body-secondary"><?= e((string) ($item['title_type'] ?? 'TITLE')) ?></small>
          </div>

          <div class="small text-body-secondary mb-3">
            Titulo: <strong><?= e((string) ($item['title_name'] ?? '-')) ?></strong><br>
            Slug: /<?= e((string) ($item['title_slug'] ?? '-')) ?>
          </div>

          <span class="badge <?= ((string) ($item['status'] ?? '') === 'ACTIVE') ? 'bg-label-success' : 'bg-label-warning' ?>"><?= e((string) ($item['status'] ?? '')) ?></span>
          <span class="badge <?= ((int) ($item['is_selectable'] ?? 1) === 1) ? 'bg-label-info' : 'bg-label-warning' ?> ms-1"><?= ((int) ($item['is_selectable'] ?? 1) === 1) ? 'Selecionavel' : 'Coming soon' ?></span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ((int) $pagination['lastPage'] > 1): ?>
<nav class="mt-4"><ul class="pagination">
<li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a></li>
<?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?><li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a></li><?php endfor; ?>
<li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a></li>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="producer-offer-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nova oferta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="producer-offer-create"></div>
      <form id="producer-offer-create-form" class="js-ajax-form" data-endpoint="/admin/producer/offers/create" data-errors-key="producer-offer-create">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Titulo</label><select class="form-select" name="title_id" required><?php foreach ($titleOptions as $title): ?><option value="<?= (int) $title['id'] ?>"><?= e((string) $title['title_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select js-producer-offer-type" name="offer_type"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Periodicidade</label><select class="form-select js-producer-offer-interval" name="billing_interval"><option value="">Nao se aplica</option><?php foreach ($intervals as $interval): ?><option value="<?= e($interval) ?>"><?= e($interval) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Moeda</label><input class="form-control" name="currency" value="BRL" maxlength="3"></div>
          <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="price" type="number" min="0.01" step="0.01" required></div>
          <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $status === 'ACTIVE' ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Selecionavel</label><select class="form-select" name="is_selectable"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#producer-offer-create-form">Criar</button></div>
  </div></div>
</div>

<div class="modal fade" id="producer-offer-edit-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Editar oferta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="producer-offer-edit"></div>
      <form id="producer-offer-edit-form" class="js-ajax-form" data-endpoint="/admin/producer/offers/update" data-errors-key="producer-offer-edit">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <input type="hidden" name="id" id="offer-e-id">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Titulo</label><select class="form-select" name="title_id" id="offer-e-title-id" required><?php foreach ($titleOptions as $title): ?><option value="<?= (int) $title['id'] ?>"><?= e((string) $title['title_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select js-producer-offer-type" name="offer_type" id="offer-e-type"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Periodicidade</label><select class="form-select js-producer-offer-interval" name="billing_interval" id="offer-e-interval"><option value="">Nao se aplica</option><?php foreach ($intervals as $interval): ?><option value="<?= e($interval) ?>"><?= e($interval) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Moeda</label><input class="form-control" name="currency" id="offer-e-currency" maxlength="3"></div>
          <div class="col-md-3"><label class="form-label">Preco</label><input class="form-control" name="price" id="offer-e-price" type="number" min="0.01" step="0.01" required></div>
          <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status" id="offer-e-status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Selecionavel</label><select class="form-select" name="is_selectable" id="offer-e-selectable"><option value="1">Sim</option><option value="0">Nao</option></select></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#producer-offer-edit-form">Salvar</button></div>
  </div></div>
</div>
