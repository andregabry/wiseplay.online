<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'title_id' => 0, 'source_type' => '', 'is_published' => ''];
$pagination = $pagination ?? ['page' => 1, 'lastPage' => 1];
$titleOptions = $titleOptions ?? [];
$sourceTypes = $sourceTypes ?? ['ONEDRIVE', 'URL', 'LOCAL'];
$uploadStatuses = $uploadStatuses ?? ['PENDING', 'UPLOADING', 'READY', 'FAILED'];
$canManageMedia = $canManageMedia ?? false;
$oneDriveConnected = $oneDriveConnected ?? false;
$oneDriveRootFolder = $oneDriveRootFolder ?? 'WisePlay';
$oneDriveChunkBytes = (int) ($oneDriveChunkBytes ?? (10 * 1024 * 1024));
$oneDriveMaxVideoBytes = (int) ($oneDriveMaxVideoBytes ?? (10 * 1024 * 1024 * 1024));
$oneDriveUploadMaxRetries = (int) ($oneDriveUploadMaxRetries ?? 3);
$oneDriveUploadRetryBackoffMs = (int) ($oneDriveUploadRetryBackoffMs ?? 500);
$oneDriveUploadDebug = $oneDriveUploadDebug ?? false;
$oneDriveQuota = $oneDriveQuota ?? null;
$oneDriveQuotaRemainingBytes = (int) ($oneDriveQuotaRemainingBytes ?? 0);

$queryForPage = static function (int $pageNum, array $filters): string {
    return '/admin/media?' . http_build_query([
        'q' => (string) ($filters['q'] ?? ''),
        'title_id' => (int) ($filters['title_id'] ?? 0),
        'source_type' => (string) ($filters['source_type'] ?? ''),
        'is_published' => (string) ($filters['is_published'] ?? ''),
        'page' => $pageNum,
    ]);
};
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
      <div class="wp-card-heading">
        <span class="wp-card-heading-icon"><i class="ri ri-film-line"></i></span>
        <div class="wp-card-heading-copy">
          <h5 class="mb-1">Media Management</h5>
          <p class="text-body-secondary mb-0">Upload OneDrive com organizacao automatica de pastas para filmes, series e cursos.</p>
        </div>
      </div>
      <?php if ($canManageMedia): ?>
        <button class="btn btn-primary js-open-modal" data-target="#media-create-modal"><i class="ri ri-add-circle-line me-1"></i>Nova midia</button>
      <?php endif; ?>
    </div>

    <form method="get" action="/admin/media" class="row g-3">
      <div class="col-md-4"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Buscar por titulo, item ou origem"></div>
      <div class="col-md-3"><select class="form-select" name="title_id"><option value="0">Todos os titulos</option><?php foreach ($titleOptions as $titleOption): ?><option value="<?= (int) $titleOption['id'] ?>" <?= ((int) $filters['title_id'] === (int) $titleOption['id']) ? 'selected' : '' ?>><?= e((string) $titleOption['title_name']) ?> (<?= e((string) $titleOption['type']) ?>)</option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="source_type"><option value="">Todas as fontes</option><?php foreach ($sourceTypes as $type): ?><option value="<?= e($type) ?>" <?= ((string) $filters['source_type'] === (string) $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-1"><select class="form-select" name="is_published"><option value="">Pub?</option><option value="1" <?= ((string) $filters['is_published'] === '1') ? 'selected' : '' ?>>Sim</option><option value="0" <?= ((string) $filters['is_published'] === '0') ? 'selected' : '' ?>>Nao</option></select></div>
      <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filtrar</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Titulo</th>
          <th>Sequencia</th>
          <th>Fonte</th>
          <th>Upload</th>
          <th>Publicado</th>
          <?php if ($canManageMedia): ?><th>Acoes</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($items === []): ?>
        <tr><td colspan="<?= $canManageMedia ? '7' : '6' ?>" class="text-center text-body-secondary">Nenhuma midia encontrada.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <?php
          $payload = json_encode([
              'id' => (int) $item['id'],
              'title_id' => (int) $item['title_id'],
              'season_number' => (int) ($item['season_number'] ?? 0),
              'episode_number' => (int) ($item['episode_number'] ?? 0),
              'item_label' => (string) $item['item_label'],
              'duration_minutes' => (int) ($item['duration_minutes'] ?? 0),
              'source_type' => (string) $item['source_type'],
              'source_path' => (string) ($item['source_path'] ?? ''),
              'external_id' => (string) ($item['external_id'] ?? ''),
              'stream_ref' => (string) ($item['stream_ref'] ?? ''),
              'stream_url' => (string) ($item['stream_url'] ?? ''),
              'download_url' => (string) ($item['download_url'] ?? ''),
              'drive_id' => (string) ($item['drive_id'] ?? ''),
              'parent_item_id' => (string) ($item['parent_item_id'] ?? ''),
              'mime_type' => (string) ($item['mime_type'] ?? ''),
              'file_size_bytes' => (int) ($item['file_size_bytes'] ?? 0),
              'upload_status' => (string) ($item['upload_status'] ?? 'READY'),
              'subtitles' => (string) ($item['subtitles'] ?? ''),
              'subtitles_json' => (string) ($item['subtitles_json'] ?? ''),
              'audio_lang' => (string) ($item['audio_lang'] ?? ''),
              'is_published' => (int) ($item['is_published'] ?? 1),
              'thumbnail_url' => (string) ($item['thumbnail_url'] ?? ''),
              'poster_url' => (string) ($item['poster_url'] ?? ''),
              'backdrop_url' => (string) ($item['backdrop_url'] ?? ''),
              'last_source_sync_at' => (string) ($item['last_source_sync_at'] ?? ''),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
          $sequence = ((int) ($item['season_number'] ?? 0) > 0 || (int) ($item['episode_number'] ?? 0) > 0)
              ? ('S' . (int) ($item['season_number'] ?? 0) . ' / E' . (int) ($item['episode_number'] ?? 0))
              : '-';
          $status = strtoupper((string) ($item['upload_status'] ?? 'READY'));
          $uploadBadge = $status === 'READY' ? 'bg-label-success' : (($status === 'FAILED') ? 'bg-label-danger' : (($status === 'UPLOADING') ? 'bg-label-warning' : 'bg-label-secondary'));
        ?>
        <tr>
          <td>
            <div class="fw-medium"><?= e((string) $item['item_label']) ?></div>
            <small class="text-body-secondary"><?= e((string) ($item['audio_lang'] ?? '-')) ?><?= (int) ($item['duration_minutes'] ?? 0) > 0 ? ' • ' . (int) $item['duration_minutes'] . ' min' : '' ?></small>
          </td>
          <td><?= e((string) $item['title_name']) ?></td>
          <td><?= e($sequence) ?></td>
          <td>
            <span class="badge bg-label-info"><?= e((string) $item['source_type']) ?></span>
            <?php if (((string) ($item['source_type'] ?? '')) === 'ONEDRIVE' && (string) ($item['external_id'] ?? '') !== ''): ?>
              <div><small class="text-body-secondary">ID: <?= e((string) $item['external_id']) ?></small></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $uploadBadge ?>"><?= e($status) ?></span></td>
          <td><span class="badge <?= ((int) $item['is_published'] === 1) ? 'bg-label-success' : 'bg-label-warning' ?>"><?= ((int) $item['is_published'] === 1) ? 'Sim' : 'Nao' ?></span></td>
          <?php if ($canManageMedia): ?>
          <td>
            <div class="dropdown wp-actions-dropdown">
              <button type="button" class="btn btn-sm btn-icon js-actions-toggle" aria-haspopup="true" aria-expanded="false"><i class="ri ri-more-2-fill"></i></button>
              <div class="dropdown-menu dropdown-menu-end wp-actions-menu" role="menu">
                <a href="#" class="dropdown-item js-edit-media" data-media='<?= e($payload) ?>'><i class="ri ri-pencil-line me-2"></i>Editar</a>
                <a href="#" class="dropdown-item js-replace-media-source" data-media='<?= e($payload) ?>'><i class="ri ri-upload-cloud-2-line me-2"></i>Substituir video</a>
                <a href="#" class="dropdown-item text-danger js-delete-media" data-id="<?= (int) $item['id'] ?>" data-name="<?= e((string) $item['item_label']) ?>"><i class="ri ri-delete-bin-line me-2"></i>Excluir</a>
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
<nav class="mt-4"><ul class="pagination">
<li class="page-item <?= ((int) $pagination['page'] <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(max(1, (int) $pagination['page'] - 1), $filters)) ?>">Anterior</a></li>
<?php for ($p = 1; $p <= (int) $pagination['lastPage']; $p++): ?><li class="page-item <?= $p === (int) $pagination['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($queryForPage($p, $filters)) ?>"><?= $p ?></a></li><?php endfor; ?>
<li class="page-item <?= ((int) $pagination['page'] >= (int) $pagination['lastPage']) ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($queryForPage(min((int) $pagination['lastPage'], (int) $pagination['page'] + 1), $filters)) ?>">Proxima</a></li>
</ul></nav>
<?php endif; ?>

<?php if ($canManageMedia): ?>
<div class="modal fade" id="media-create-modal" tabindex="-1" aria-hidden="true" data-modal-role="create">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nova midia (upload OneDrive)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="media-create"></div>
      <form id="media-create-form" class="js-ajax-form" data-endpoint="/admin/media/create" data-errors-key="media-create" data-has-files="1" data-media-upload="1" data-onedrive-root-folder="<?= e($oneDriveRootFolder) ?>" data-chunk-bytes="<?= (int) $oneDriveChunkBytes ?>" data-max-video-bytes="<?= (int) $oneDriveMaxVideoBytes ?>" data-upload-max-retries="<?= (int) $oneDriveUploadMaxRetries ?>" data-upload-retry-backoff-ms="<?= (int) $oneDriveUploadRetryBackoffMs ?>" data-quota-remaining-bytes="<?= (int) $oneDriveQuotaRemainingBytes ?>" data-upload-debug="<?= $oneDriveUploadDebug ? '1' : '0' ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <input type="hidden" name="stream_ref" id="m-c-stream-ref">
        <input type="hidden" name="stream_url" id="m-c-stream-url">
        <input type="hidden" name="download_url" id="m-c-download-url">
        <input type="hidden" name="drive_id" id="m-c-drive-id">
        <input type="hidden" name="parent_item_id" id="m-c-parent-item-id">
        <input type="hidden" name="mime_type" id="m-c-mime-type">
        <input type="hidden" name="file_size_bytes" id="m-c-file-size-bytes">
        <input type="hidden" name="upload_status" id="m-c-upload-status-field" value="PENDING">
        <input type="hidden" name="stream_url_expires_at" id="m-c-stream-url-expires-at">
        <input type="hidden" name="last_source_sync_at" id="m-c-last-source-sync-at">

        <div class="row g-3">
          <div class="col-md-5"><label class="form-label">Titulo</label><select class="form-select js-media-title" name="title_id" id="m-c-title-id" required><?php foreach ($titleOptions as $titleOption): ?><option value="<?= (int) $titleOption['id'] ?>" data-type="<?= e((string) $titleOption['type']) ?>" data-slug="<?= e((string) $titleOption['slug']) ?>"><?= e((string) $titleOption['title_name']) ?> (<?= e((string) $titleOption['type']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Temporada/Modulo</label><input class="form-control js-media-season" type="number" min="0" name="season_number" id="m-c-season" value="0"></div>
          <div class="col-md-3"><label class="form-label">Episodio/Licao</label><input class="form-control js-media-episode" type="number" min="0" name="episode_number" id="m-c-episode" value="0"></div>
          <div class="col-md-1"><label class="form-label">Pub</label><select class="form-select" name="is_published" id="m-c-published"><option value="1">1</option><option value="0">0</option></select></div>

          <div class="col-md-6"><label class="form-label">Titulo do item</label><input class="form-control" name="item_label" id="m-c-label" required></div>
          <div class="col-md-2"><label class="form-label">Duracao</label><input class="form-control" type="number" min="0" name="duration_minutes" id="m-c-duration"></div>
          <div class="col-md-2"><label class="form-label">Source Type</label><select class="form-select" name="source_type" id="m-c-source-type"><?php foreach ($sourceTypes as $stype): ?><option value="<?= e($stype) ?>" <?= $stype === 'ONEDRIVE' ? 'selected' : '' ?>><?= e($stype) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Audio/Idioma</label><input class="form-control" name="audio_lang" id="m-c-audio-lang" placeholder="pt-BR"></div>

          <div class="col-md-12"><label class="form-label">Legendas (idiomas, csv)</label><input class="form-control" name="subtitles" id="m-c-subtitles" placeholder="pt-BR,en,es"></div>

          <div class="col-md-12">
            <div class="alert alert-primary mb-0">
              <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div>
                  <strong>OneDrive</strong>
                  <span class="badge <?= $oneDriveConnected ? 'bg-label-success' : 'bg-label-danger' ?> ms-1" id="m-c-onedrive-badge"><?= $oneDriveConnected ? 'Connected' : 'Disconnected' ?></span>
                  <div class="small mt-1">Destino: <code id="m-c-destination-preview">/<?= e($oneDriveRootFolder) ?>/...</code></div>
                </div>
                <?php if (is_array($oneDriveQuota) && ($oneDriveQuota['has_quota'] ?? false)): ?>
                  <div class="text-end small">
                    <div><strong>Quota</strong>: <?= e((string) ($oneDriveQuota['used_human'] ?? '-')) ?> / <?= e((string) ($oneDriveQuota['total_human'] ?? '-')) ?></div>
                    <div class="progress wp-quota-progress" style="width: 220px;"><div class="progress-bar <?= e((string) ($oneDriveQuota['progress_bar_class'] ?? 'bg-secondary')) ?>" style="width: <?= e((string) ($oneDriveQuota['percentage'] ?? 0)) ?>%"></div></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-12"><hr class="my-1"></div>

          <div class="col-md-6"><label class="form-label">Video file (obrigatorio OneDrive)</label><input class="form-control js-media-video-file" type="file" name="video_file" accept="video/mp4,video/x-matroska,video/quicktime,video/webm,video/mp2t,.mp4,.mkv,.mov,.webm,.ts"></div>
          <div class="col-md-6"><label class="form-label">Subtitle files (opcional, multiplo)</label><input class="form-control js-media-subtitle-files" type="file" name="subtitle_files[]" multiple accept=".vtt,.srt,text/vtt,application/x-subrip"></div>
          <div class="col-md-4"><label class="form-label">Thumbnail (opcional)</label><input class="form-control js-media-image-file" type="file" name="thumbnail_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-4"><label class="form-label">Poster (opcional)</label><input class="form-control js-media-image-file" type="file" name="poster_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-4"><label class="form-label">Backdrop (opcional)</label><input class="form-control js-media-image-file" type="file" name="backdrop_file" accept="image/png,image/jpeg,image/webp"></div>

          <div class="col-md-8">
            <div class="wp-media-file-preview" id="m-c-file-preview">
              <div class="small text-body-secondary">Arquivos selecionados</div>
              <ul class="mb-0 ps-3" id="m-c-file-preview-list"><li class="text-body-secondary">Nenhum arquivo selecionado.</li></ul>
            </div>
          </div>
          <div class="col-md-4">
            <div class="wp-image-preview-wrap"><img src="/assets/img/placeholders/image-placeholder.svg" alt="preview" id="m-c-image-preview"><small>Preview imagem</small></div>
          </div>

          <div class="col-12">
            <div class="wp-media-upload-progress" id="m-c-upload-progress-wrap" style="display:none;">
              <div class="d-flex justify-content-between small mb-1"><span id="m-c-upload-status">Preparing</span><span id="m-c-upload-percent">0.0%</span></div>
              <div class="progress mb-2"><div class="progress-bar bg-info" id="m-c-upload-progress" style="width:0%"></div></div>
              <div class="row g-2 small text-body-secondary">
                <div class="col-md-4">Chunk: <span id="m-c-upload-chunk">0/0</span></div>
                <div class="col-md-4">Transferido: <span id="m-c-upload-bytes">0 B / 0 B</span></div>
                <div class="col-md-4">Velocidade: <span id="m-c-upload-speed">0.00 MB/s</span></div>
                <div class="col-md-4">ETA: <span id="m-c-upload-eta">--:--</span></div>
                <div class="col-md-4">Retries: <span id="m-c-upload-retries">0/0</span></div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-semantic-warning js-media-upload-pause" data-form="#media-create-form" style="display:none;">Pausar</button>
                <button type="button" class="btn btn-sm btn-semantic-primary js-media-upload-resume" data-form="#media-create-form" style="display:none;">Retomar</button>
                <button type="button" class="btn btn-sm btn-semantic-danger js-media-upload-cancel" data-form="#media-create-form" style="display:none;">Cancelar upload</button>
              </div>
            </div>
          </div>

          <div class="col-12"><hr class="my-1"></div>

          <div class="col-md-4"><label class="form-label">External ID</label><input class="form-control" name="external_id" id="m-c-external-id" readonly></div>
          <div class="col-md-4"><label class="form-label">Source Path</label><input class="form-control" name="source_path" id="m-c-source-path" readonly></div>
          <div class="col-md-4"><label class="form-label">Playback ref</label><input class="form-control" id="m-c-stream-ref-view" readonly></div>
          <div class="col-md-6"><label class="form-label">Thumbnail URL</label><input class="form-control" name="thumbnail_url" id="m-c-thumbnail-url" readonly></div>
          <div class="col-md-6"><label class="form-label">Backdrop URL</label><input class="form-control" name="backdrop_url" id="m-c-backdrop-url" readonly></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#media-create-form" id="m-c-submit">Criar</button></div>
  </div></div>
</div>

<div class="modal fade" id="media-edit-modal" tabindex="-1" aria-hidden="true" data-modal-role="edit">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Editar midia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="form-errors text-danger small mb-2" data-form-errors="media-edit"></div>
      <form id="media-edit-form" class="js-ajax-form" data-endpoint="/admin/media/update" data-errors-key="media-edit" data-has-files="1" data-media-upload="1" data-onedrive-root-folder="<?= e($oneDriveRootFolder) ?>" data-chunk-bytes="<?= (int) $oneDriveChunkBytes ?>" data-max-video-bytes="<?= (int) $oneDriveMaxVideoBytes ?>" data-upload-max-retries="<?= (int) $oneDriveUploadMaxRetries ?>" data-upload-retry-backoff-ms="<?= (int) $oneDriveUploadRetryBackoffMs ?>" data-quota-remaining-bytes="<?= (int) $oneDriveQuotaRemainingBytes ?>" data-upload-debug="<?= $oneDriveUploadDebug ? '1' : '0' ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <input type="hidden" name="id" id="m-e-id">
        <input type="hidden" name="stream_ref" id="m-e-stream-ref">
        <input type="hidden" name="stream_url" id="m-e-stream-url">
        <input type="hidden" name="download_url" id="m-e-download-url">
        <input type="hidden" name="drive_id" id="m-e-drive-id">
        <input type="hidden" name="parent_item_id" id="m-e-parent-item-id">
        <input type="hidden" name="mime_type" id="m-e-mime-type">
        <input type="hidden" name="file_size_bytes" id="m-e-file-size-bytes">
        <input type="hidden" name="upload_status" id="m-e-upload-status-field" value="READY">
        <input type="hidden" name="stream_url_expires_at" id="m-e-stream-url-expires-at">
        <input type="hidden" name="last_source_sync_at" id="m-e-last-source-sync-at">

        <div class="row g-3">
          <div class="col-md-5"><label class="form-label">Titulo</label><select class="form-select js-media-title" name="title_id" id="m-e-title-id" required><?php foreach ($titleOptions as $titleOption): ?><option value="<?= (int) $titleOption['id'] ?>" data-type="<?= e((string) $titleOption['type']) ?>" data-slug="<?= e((string) $titleOption['slug']) ?>"><?= e((string) $titleOption['title_name']) ?> (<?= e((string) $titleOption['type']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Temporada/Modulo</label><input class="form-control js-media-season" type="number" min="0" name="season_number" id="m-e-season"></div>
          <div class="col-md-3"><label class="form-label">Episodio/Licao</label><input class="form-control js-media-episode" type="number" min="0" name="episode_number" id="m-e-episode"></div>
          <div class="col-md-1"><label class="form-label">Pub</label><select class="form-select" name="is_published" id="m-e-published"><option value="1">1</option><option value="0">0</option></select></div>

          <div class="col-md-6"><label class="form-label">Titulo do item</label><input class="form-control" name="item_label" id="m-e-label" required></div>
          <div class="col-md-2"><label class="form-label">Duracao</label><input class="form-control" type="number" min="0" name="duration_minutes" id="m-e-duration"></div>
          <div class="col-md-2"><label class="form-label">Source Type</label><select class="form-select" name="source_type" id="m-e-source-type"><?php foreach ($sourceTypes as $stype): ?><option value="<?= e($stype) ?>"><?= e($stype) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Audio</label><input class="form-control" name="audio_lang" id="m-e-audio-lang"></div>

          <div class="col-md-12"><label class="form-label">Legendas (idiomas)</label><input class="form-control" name="subtitles" id="m-e-subtitles"></div>
          <div class="col-md-6"><label class="form-label">Substituir video (opcional)</label><input class="form-control js-media-video-file" type="file" name="video_file" accept="video/mp4,video/x-matroska,video/quicktime,video/webm,video/mp2t,.mp4,.mkv,.mov,.webm,.ts"></div>
          <div class="col-md-6"><label class="form-label">Novas legendas (opcional, multiplo)</label><input class="form-control js-media-subtitle-files" type="file" name="subtitle_files[]" multiple accept=".vtt,.srt,text/vtt,application/x-subrip"></div>
          <div class="col-md-4"><label class="form-label">Thumbnail (opcional)</label><input class="form-control js-media-image-file" type="file" name="thumbnail_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-4"><label class="form-label">Poster (opcional)</label><input class="form-control js-media-image-file" type="file" name="poster_file" accept="image/png,image/jpeg,image/webp"></div>
          <div class="col-md-4"><label class="form-label">Backdrop (opcional)</label><input class="form-control js-media-image-file" type="file" name="backdrop_file" accept="image/png,image/jpeg,image/webp"></div>

          <div class="col-12">
            <div class="wp-media-upload-progress" id="m-e-upload-progress-wrap" style="display:none;">
              <div class="d-flex justify-content-between small mb-1"><span id="m-e-upload-status">Preparing</span><span id="m-e-upload-percent">0.0%</span></div>
              <div class="progress mb-2"><div class="progress-bar bg-info" id="m-e-upload-progress" style="width:0%"></div></div>
              <div class="row g-2 small text-body-secondary">
                <div class="col-md-4">Chunk: <span id="m-e-upload-chunk">0/0</span></div>
                <div class="col-md-4">Transferido: <span id="m-e-upload-bytes">0 B / 0 B</span></div>
                <div class="col-md-4">Velocidade: <span id="m-e-upload-speed">0.00 MB/s</span></div>
                <div class="col-md-4">ETA: <span id="m-e-upload-eta">--:--</span></div>
                <div class="col-md-4">Retries: <span id="m-e-upload-retries">0/0</span></div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-semantic-warning js-media-upload-pause" data-form="#media-edit-form" style="display:none;">Pausar</button>
                <button type="button" class="btn btn-sm btn-semantic-primary js-media-upload-resume" data-form="#media-edit-form" style="display:none;">Retomar</button>
                <button type="button" class="btn btn-sm btn-semantic-danger js-media-upload-cancel" data-form="#media-edit-form" style="display:none;">Cancelar upload</button>
              </div>
            </div>
          </div>

          <div class="col-md-4"><label class="form-label">External ID</label><input class="form-control" name="external_id" id="m-e-external-id"></div>
          <div class="col-md-8"><label class="form-label">Source Path</label><input class="form-control" name="source_path" id="m-e-source-path"></div>
          <div class="col-md-4"><label class="form-label">Playback ref</label><input class="form-control" id="m-e-stream-ref-view" readonly></div>
          <div class="col-md-4"><label class="form-label">Thumbnail URL</label><input class="form-control" name="thumbnail_url" id="m-e-thumbnail-url"></div>
          <div class="col-md-4"><label class="form-label">Backdrop URL</label><input class="form-control" name="backdrop_url" id="m-e-backdrop-url"></div>
        </div>
      </form>
      <div class="alert alert-primary mt-3 mb-0 small">Para filmes, deixe temporada/episodio em 0. Para series/cursos, informe modulo/episodio.</div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary js-submit-form" data-form="#media-edit-form" id="m-e-submit">Salvar</button></div>
  </div></div>
</div>
<?php endif; ?>
