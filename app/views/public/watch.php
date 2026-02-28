<?php
$profile = $profile ?? [];
$mediaItem = $mediaItem ?? null;
$suggestions = $suggestions ?? [];

if (!$mediaItem) {
    echo '<main class="wp-generic"><p>Midia nao encontrada.</p></main>';
    return;
}

$sourcePath = trim((string) ($mediaItem['playback_url'] ?? ''));
if ($sourcePath === '') {
    $sourcePath = trim((string) ($mediaItem['source_path'] ?? ''));
}
$storedSourcePath = trim((string) ($mediaItem['source_path'] ?? ''));
$sourceType = (string) ($mediaItem['source_type'] ?? 'URL');
$sourceMime = trim((string) (($mediaItem['playback_mime_type'] ?? '') !== '' ? $mediaItem['playback_mime_type'] : ($mediaItem['mime_type'] ?? '')));
$duration = (int) ($mediaItem['duration_minutes'] ?? 0);
$playbackMessage = trim((string) ($mediaItem['playback_message'] ?? ''));
$sourceMimeLower = strtolower($sourceMime);
$isTsSource = (
    preg_match('/\.ts(\?.*)?$/i', $sourcePath) === 1
    || preg_match('/\.ts(\?.*)?$/i', $storedSourcePath) === 1
    || str_contains($sourceMimeLower, 'mp2t')
    || str_contains($sourceMimeLower, 'mpegts')
    || str_contains($sourceMimeLower, 'mpeg-tts')
    || (str_contains($sourceMimeLower, 'mpeg') && str_contains($sourceMimeLower, 'ts'))
);
$sourceMimeForTag = $sourceMime !== '' ? $sourceMime : ($isTsSource ? 'video/mp2t' : '');
$canEmbedVideo = $sourcePath !== '' && (
    $sourceType === 'ONEDRIVE'
    || preg_match('/\.(mp4|webm|ogg|mov|mkv|ts)(\\?.*)?$/i', $sourcePath) === 1
    || stripos($sourceMime, 'video/') === 0
    || stripos($sourceMime, 'mp2t') !== false
);
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-watch-page" data-watch-media-id="<?= (int) $mediaItem['id'] ?>" data-watch-title-id="<?= (int) $mediaItem['title_id'] ?>">
  <section class="wp-player-wrap">
    <div class="wp-player-head">
      <a href="/title/<?= (int) $mediaItem['title_id'] ?>" class="wp-btn tiny ghost">&larr; Voltar</a>
      <h1><?= e((string) $mediaItem['title_name']) ?></h1>
    </div>

    <div class="wp-player-box">
      <?php if ($canEmbedVideo): ?>
        <video
          controls
          preload="metadata"
          id="wp-video-element"
          data-source-url="<?= e($sourcePath) ?>"
          data-source-mime="<?= e($sourceMimeForTag) ?>"
          data-source-is-ts="<?= $isTsSource ? '1' : '0' ?>">
          <source src="<?= e($sourcePath) ?>"<?= $sourceMimeForTag !== '' ? ' type="' . e($sourceMimeForTag) . '"' : '' ?>>
        </video>
      <?php else: ?>
        <div class="wp-player-placeholder">
          <strong><?= e((string) $mediaItem['item_label']) ?></strong>
          <p>Player scaffold pronto para URL/OneDrive/local com token seguro.</p>
          <?php if ($playbackMessage !== ''): ?><p><small><?= e($playbackMessage) ?></small></p><?php endif; ?>
          <p><small>Fonte: <?= e($sourceType) ?></small></p>
          <?php if ($sourcePath !== ''): ?>
            <a class="wp-btn light" href="<?= e($sourcePath) ?>" target="_blank" rel="noreferrer">Abrir fonte</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="wp-watch-meta">
      <h2><?= e((string) $mediaItem['item_label']) ?></h2>
      <p>
        <?= e((string) $sourceType) ?>
        <?php if ($duration > 0): ?> • <?= $duration ?> min<?php endif; ?>
        <?php if ((int) ($mediaItem['season_number'] ?? 0) > 0): ?> • T<?= (int) $mediaItem['season_number'] ?><?php endif; ?>
        <?php if ((int) ($mediaItem['episode_number'] ?? 0) > 0): ?>E<?= (int) $mediaItem['episode_number'] ?><?php endif; ?>
      </p>
    </div>

    <div class="wp-watch-progress-box">
      <label for="watch-progress-range">Progresso de reproducao</label>
      <input id="watch-progress-range" type="range" min="0" max="100" step="1" value="0" data-watch-progress>
      <div class="wp-watch-progress-actions">
        <button type="button" class="wp-btn tiny ghost" data-watch-mark="25">25%</button>
        <button type="button" class="wp-btn tiny ghost" data-watch-mark="50">50%</button>
        <button type="button" class="wp-btn tiny ghost" data-watch-mark="80">80%</button>
        <button type="button" class="wp-btn tiny light" data-watch-mark="100">Concluir</button>
      </div>
      <input type="hidden" data-watch-csrf value="<?= e(App\core\Csrf::token()) ?>">
    </div>
  </section>

  <aside class="wp-next-up">
    <h3>Proximos episodios/licoes</h3>
    <?php if ($suggestions === []): ?>
      <div class="wp-empty-card"><p>Sem sugestoes no momento.</p></div>
    <?php else: ?>
      <div class="wp-next-list">
        <?php foreach ($suggestions as $suggestion): ?>
          <a href="/watch/<?= (int) $suggestion['id'] ?>" class="wp-next-item">
            <strong><?= e((string) $suggestion['item_label']) ?></strong>
            <small>
              <?php if ((int) ($suggestion['season_number'] ?? 0) > 0): ?>T<?= (int) $suggestion['season_number'] ?><?php endif; ?>
              <?php if ((int) ($suggestion['episode_number'] ?? 0) > 0): ?>E<?= (int) $suggestion['episode_number'] ?><?php endif; ?>
              <?php if ((int) ($suggestion['duration_minutes'] ?? 0) > 0): ?> • <?= (int) $suggestion['duration_minutes'] ?> min<?php endif; ?>
            </small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
</main>

<script src="https://cdn.jsdelivr.net/npm/mpegts.js@1.8.0/dist/mpegts.min.js"></script>
