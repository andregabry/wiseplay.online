<?php
$profile = $profile ?? [];
$featured = $featured ?? null;
$rows = $rows ?? [];
$continueWatching = $continueWatching ?? [];
$subscription = $subscription ?? null;
$firstPlayableByTitle = $firstPlayableByTitle ?? [];
$appTagline = (string) ($appTagline ?? 'Seu streaming inteligente para filmes, series e cursos.');

$assetOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};

$rowLabel = static function (string $key): string {
    if ($key === 'MOVIE') {
        return 'Filmes';
    }
    if ($key === 'SERIES') {
        return 'Series';
    }
    if ($key === 'COURSE') {
        return 'Cursos';
    }
    if (str_starts_with($key, 'GENRE:')) {
        return 'Genero: ' . substr($key, 6);
    }
    return $key;
};

$featuredImage = $assetOrFallback((string) ($featured['backdrop_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
$featuredCover = $assetOrFallback((string) ($featured['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
$featuredPlayable = $featured ? ($firstPlayableByTitle[(int) $featured['id']] ?? null) : null;
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-browse-page">
  <section class="wp-hero" style="background-image: linear-gradient(77deg, rgba(9,10,15,0.96) 28%, rgba(9,10,15,0.54) 58%, rgba(9,10,15,0.92) 100%), url('<?= e($featuredImage) ?>');">
    <div class="wp-hero-content">
      <p class="eyebrow">WisePlay</p>
      <h1><?= e((string) ($featured['title_name'] ?? 'Catalogo WisePlay')) ?></h1>
      <p><?= e((string) (($featured['description'] ?? '') !== '' ? $featured['description'] : $appTagline)) ?></p>

      <div class="wp-hero-meta">
        <?php if ($featured): ?>
          <span><?= e((string) ($featured['type'] ?? 'TITLE')) ?></span>
          <span><?= e((string) ($featured['genre'] ?? 'General')) ?></span>
          <span><?= e((string) (($featured['year'] ?? '') !== '' ? $featured['year'] : '-')) ?></span>
          <span><?= e((string) ($featured['maturity_rating'] ?? '16')) ?></span>
        <?php endif; ?>
        <?php if ($subscription): ?>
          <span class="subscription">Plano <?= e((string) ($subscription['plan_name'] ?? 'Ativo')) ?></span>
        <?php endif; ?>
      </div>

      <div class="wp-hero-actions">
        <?php if ($featuredPlayable): ?>
          <a class="wp-btn light" href="/watch/<?= (int) $featuredPlayable['id'] ?>">Assistir agora</a>
        <?php endif; ?>
        <?php if ($featured): ?>
          <a class="wp-btn ghost" href="/title/<?= (int) $featured['id'] ?>">Mais detalhes</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="wp-hero-poster">
      <img src="<?= e($featuredCover) ?>" alt="Capa em destaque" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
    </div>
  </section>

  <?php if ($continueWatching !== []): ?>
    <section class="wp-row-section">
      <div class="wp-row-head">
        <h2>Continue assistindo</h2>
      </div>
      <div class="wp-continue-grid">
        <?php foreach ($continueWatching as $item): ?>
          <?php
            $cover = $assetOrFallback((string) ($item['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
            $progress = max(0, min(100, (int) ($item['progress_percent'] ?? 0)));
          ?>
          <article class="wp-continue-card">
            <a href="/watch/<?= (int) $item['media_item_id'] ?>">
              <img src="<?= e($cover) ?>" alt="<?= e((string) $item['title_name']) ?>" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
              <div class="wp-continue-body">
                <h3><?= e((string) $item['title_name']) ?></h3>
                <p><?= e((string) $item['item_label']) ?></p>
                <div class="wp-progress"><span style="width: <?= $progress ?>%"></span></div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php $rowIndex = 0; ?>
  <?php foreach ($rows as $key => $items): ?>
    <?php if ($items === []): continue; endif; ?>
    <?php $rowId = 'wp-row-' . $rowIndex++; ?>
    <section class="wp-row-section">
      <div class="wp-row-head">
        <h2><?= e($rowLabel((string) $key)) ?></h2>
        <div class="wp-row-nav">
          <button type="button" data-row-prev="<?= e($rowId) ?>" aria-label="Rolar para esquerda">&#10094;</button>
          <button type="button" data-row-next="<?= e($rowId) ?>" aria-label="Rolar para direita">&#10095;</button>
        </div>
      </div>

      <div class="wp-row-rail" id="<?= e($rowId) ?>">
        <?php foreach ($items as $titleItem): ?>
          <?php
            $titleId = (int) $titleItem['id'];
            $cover = $assetOrFallback((string) ($titleItem['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
            $playable = $firstPlayableByTitle[$titleId] ?? null;
          ?>
          <article class="wp-title-card">
            <a href="/title/<?= $titleId ?>" class="wp-title-cover">
              <img src="<?= e($cover) ?>" alt="<?= e((string) $titleItem['title_name']) ?>" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
            </a>
            <div class="wp-title-meta">
              <h3><?= e((string) $titleItem['title_name']) ?></h3>
              <p><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?> • <?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></p>
              <div class="wp-title-actions">
                <a class="wp-btn tiny ghost" href="/title/<?= $titleId ?>">Detalhes</a>
                <?php if ($playable): ?>
                  <a class="wp-btn tiny light" href="/watch/<?= (int) $playable['id'] ?>">Assistir</a>
                <?php else: ?>
                  <span class="wp-btn tiny disabled">Sem midia</span>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>
