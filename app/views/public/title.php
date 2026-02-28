<?php
$profile = $profile ?? [];
$titleItem = $titleItem ?? null;
$mediaHierarchy = $mediaHierarchy ?? [];
$firstPlayable = $firstPlayable ?? null;

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
