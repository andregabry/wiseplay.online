<?php
$profile = $profile ?? [];
$titleItem = $titleItem ?? null;
$mediaHierarchy = $mediaHierarchy ?? [];
$firstPlayable = $firstPlayable ?? null;
$seasonTrailers = $seasonTrailers ?? [];
$heroTrailer = $heroTrailer ?? null;

if (!$titleItem) {
    echo '<main class="wp-generic"><p>Titulo nao encontrado.</p></main>';
    return;
}

$assetOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};

$type = (string) ($titleItem['type'] ?? 'MOVIE');
$seasonLabel = $type === 'COURSE' ? 'Modulo' : 'Temporada';
$episodeLabel = $type === 'COURSE' ? 'Licao' : 'Episodio';
$episodesHeading = $type === 'MOVIE'
    ? 'Conteudo disponivel'
    : ($seasonLabel . 's e ' . strtolower($episodeLabel) . 's');
$backdrop = $assetOrFallback((string) ($titleItem['backdrop_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
$cover = $assetOrFallback((string) ($titleItem['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-title-page">
  <section class="wp-title-hero" style="background-image: linear-gradient(77deg, rgba(9,10,15,0.9) 20%, rgba(9,10,15,0.62) 56%, rgba(9,10,15,0.92) 100%), url('<?= e($backdrop) ?>');">
    <?php if (is_array($heroTrailer) && ((string) ($heroTrailer['url'] ?? '')) !== ''): ?>
      <?php $heroTrailerMime = trim((string) ($heroTrailer['mime_type'] ?? '')); ?>
      <div class="wp-title-hero-preview-wrap">
        <video
          class="wp-title-hero-preview-video js-loop-trailer js-title-hero-preview"
          data-loop-seconds="45"
          autoplay
          muted
          playsinline
          preload="metadata"
          poster="<?= e($backdrop) ?>">
          <source src="<?= e((string) $heroTrailer['url']) ?>"<?= $heroTrailerMime !== '' ? ' type="' . e($heroTrailerMime) . '"' : '' ?>>
        </video>
      </div>
      <button type="button" class="wp-hero-sound js-hero-sound-toggle wp-title-hero-sound" data-target=".js-title-hero-preview" aria-label="Ativar/desativar audio">
        <span>&#128266;</span>
      </button>
    <?php endif; ?>

    <img class="wp-title-poster" src="<?= e($cover) ?>" alt="<?= e((string) $titleItem['title_name']) ?>" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">

    <div class="wp-title-hero-body">
      <h1><?= e((string) $titleItem['title_name']) ?></h1>
      <div class="wp-title-hero-meta">
        <span><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?></span>
        <span><?= e((string) ($titleItem['genre'] ?? 'General')) ?></span>
        <span><?= e((string) (($titleItem['year'] ?? '') !== '' ? $titleItem['year'] : '-')) ?></span>
        <span><?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></span>
      </div>
      <p><?= e((string) (($titleItem['description'] ?? '') !== '' ? $titleItem['description'] : 'Sem descricao cadastrada.')) ?></p>

      <div class="wp-title-hero-actions">
        <?php if ($firstPlayable): ?>
          <a class="wp-btn light" href="/watch/<?= (int) $firstPlayable['id'] ?>">Assistir</a>
        <?php endif; ?>
        <a class="wp-btn ghost" href="/browse">Voltar ao catalogo</a>
      </div>
    </div>
  </section>

  <section class="wp-title-episodes">
    <h2><?= e($episodesHeading) ?></h2>

    <?php if ($mediaHierarchy === []): ?>
      <div class="wp-empty-card"><p>Nenhum item de midia publicado para este titulo.</p></div>
    <?php else: ?>
      <?php foreach ($mediaHierarchy as $seasonNumber => $episodes): ?>
        <article class="wp-season-block">
          <?php if ($type !== 'MOVIE'): ?>
            <h3><?= e($seasonLabel) ?> <?= (int) $seasonNumber ?></h3>
          <?php endif; ?>

          <?php $seasonTrailer = $seasonTrailers[(int) $seasonNumber] ?? null; ?>
          <?php if (is_array($seasonTrailer) && ((string) ($seasonTrailer['url'] ?? '')) !== ''): ?>
            <?php
              $trailerPoster = $assetOrFallback(
                  (string) ($seasonTrailer['poster'] ?? ''),
                  $backdrop
              );
              $trailerMime = trim((string) ($seasonTrailer['mime_type'] ?? ''));
              $trailerSourceIsTs = (int) ($seasonTrailer['source_is_ts'] ?? 0) === 1;
            ?>
            <div class="wp-season-trailer">
              <div class="wp-season-trailer-media">
                <video
                  class="wp-season-trailer-video js-season-trailer"
                  data-loop-seconds="45"
                  data-source-is-ts="<?= $trailerSourceIsTs ? '1' : '0' ?>"
                  autoplay
                  muted
                  playsinline
                  preload="metadata"
                  poster="<?= e($trailerPoster) ?>">
                  <source src="<?= e((string) $seasonTrailer['url']) ?>"<?= $trailerMime !== '' ? ' type="' . e($trailerMime) . '"' : '' ?>>
                </video>
              </div>
              <div class="wp-season-trailer-meta">
                <strong>Trailer automatico (45s)</strong>
                <small>
                  Baseado em: <?= e((string) ($seasonTrailer['item_label'] ?? 'Primeiro video')) ?>
                  <?php if ((int) ($seasonTrailer['episode_number'] ?? 0) > 0): ?>
                    • <?= e($episodeLabel) ?> <?= (int) $seasonTrailer['episode_number'] ?>
                  <?php endif; ?>
                  <?php if ((int) ($seasonTrailer['duration_minutes'] ?? 0) > 0): ?>
                    • <?= (int) $seasonTrailer['duration_minutes'] ?> min
                  <?php endif; ?>
                </small>
                <?php if ((int) ($seasonTrailer['media_id'] ?? 0) > 0): ?>
                  <a class="wp-btn tiny ghost" href="/watch/<?= (int) $seasonTrailer['media_id'] ?>">Assistir completo</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="wp-episode-list">
            <?php foreach ($episodes as $episode): ?>
              <?php
                $duration = (int) ($episode['duration_minutes'] ?? 0);
                $epNo = (int) ($episode['episode_number'] ?? 1);
              ?>
              <a class="wp-episode-item" href="/watch/<?= (int) $episode['id'] ?>">
                <div class="wp-episode-order"><?= $type === 'MOVIE' ? 'FILME' : e($episodeLabel . ' ' . $epNo) ?></div>
                <div class="wp-episode-main">
                  <strong><?= e((string) $episode['item_label']) ?></strong>
                  <small>
                    <?= e((string) ($episode['source_type'] ?? 'URL')) ?>
                    <?php if ($duration > 0): ?> • <?= $duration ?> min<?php endif; ?>
                  </small>
                </div>
                <span class="wp-episode-cta">Assistir</span>
              </a>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
