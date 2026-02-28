<?php
$profile = $profile ?? [];
$availableProfiles = $availableProfiles ?? [$profile];
$selectedProfileId = (int) ($selectedProfileId ?? (int) ($profile['id'] ?? 0));
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
        return substr($key, 6);
    }
    return $key;
};

$rowAnchor = static function (string $key, int $index): string {
    return match ($key) {
        'SERIES' => 'row-series',
        'MOVIE' => 'row-movie',
        'COURSE' => 'row-course',
        default => 'row-' . $index,
    };
};

$featuredImage = $assetOrFallback((string) ($featured['backdrop_image'] ?? ''), '/assets/img/placeholders/bakground.jpg');
$featuredPlayable = $featured ? ($firstPlayableByTitle[(int) $featured['id']] ?? null) : null;
$heroEyebrow = $featured ? strtoupper((string) ($featured['genre'] ?? 'WisePlay Original')) : 'WISEPLAY';
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-browse-page wp-browse-page--cinema">
  <section class="wp-hero wp-hero--cinema" style="background-image: linear-gradient(77deg, rgba(0,0,0,0.78) 18%, rgba(0,0,0,0.32) 54%, rgba(0,0,0,0.82) 100%), linear-gradient(180deg, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.72) 94%), url('<?= e($featuredImage) ?>');">
    <div class="wp-hero-content wp-hero-content--cinema">
      <p class="eyebrow"><?= e($heroEyebrow) ?></p>
      <h1><?= e((string) ($featured['title_name'] ?? 'Catalogo WisePlay')) ?></h1>
      <p><?= e((string) (($featured['description'] ?? '') !== '' ? $featured['description'] : $appTagline)) ?></p>

      <div class="wp-hero-meta wp-hero-meta--cinema">
        <?php if ($featured): ?>
          <span><?= e((string) ($featured['type'] ?? 'TITLE')) ?></span>
          <span><?= e((string) ($featured['genre'] ?? 'General')) ?></span>
          <span><?= e((string) (($featured['year'] ?? '') !== '' ? $featured['year'] : '-')) ?></span>
          <span><?= e((string) ($featured['maturity_rating'] ?? '16+')) ?></span>
        <?php endif; ?>
        <?php if ($subscription): ?>
          <span class="subscription">Plano <?= e((string) ($subscription['plan_name'] ?? 'Ativo')) ?></span>
        <?php endif; ?>
      </div>

      <div class="wp-hero-actions wp-hero-actions--cinema">
        <?php if ($featuredPlayable): ?>
          <a class="wp-btn light wp-btn-hero-play" href="/watch/<?= (int) $featuredPlayable['id'] ?>">
            <span class="wp-btn-icon">&#9658;</span>
            <span>Assistir</span>
          </a>
        <?php endif; ?>
        <?php if ($featured): ?>
          <a class="wp-btn ghost wp-btn-hero-more" href="/title/<?= (int) $featured['id'] ?>">
            <span class="wp-btn-icon">i</span>
            <span>Mais informacoes</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="wp-hero-floating-meta">
      <button type="button" class="wp-hero-sound js-public-coming-soon" data-label="Controle de audio" aria-label="Controle de audio">
        <span>&#128266;</span>
      </button>
      <div class="wp-hero-rating"><?= e((string) ($featured['maturity_rating'] ?? '16+')) ?></div>
    </div>
  </section>

  <div class="wp-browse-rows-shell">
    <?php $rowIndex = 0; ?>
    <?php foreach ($rows as $key => $items): ?>
      <?php if ($items === []): continue; endif; ?>
      <?php
        $rowId = $rowAnchor((string) $key, $rowIndex);
        $railId = 'wp-row-' . $rowIndex;
        $heading = $rowIndex === 0 ? 'Principais escolhas do dia para voce' : $rowLabel((string) $key);
      ?>
      <section class="wp-row-section<?= $rowIndex === 0 ? ' is-first' : '' ?>" id="<?= e($rowId) ?>">
        <div class="wp-row-head">
          <h2><?= e($heading) ?></h2>
          <div class="wp-row-nav">
            <button type="button" data-row-prev="<?= e($railId) ?>" aria-label="Rolar para esquerda">&#10094;</button>
            <button type="button" data-row-next="<?= e($railId) ?>" aria-label="Rolar para direita">&#10095;</button>
          </div>
        </div>

        <div class="wp-row-rail wp-row-rail--cinema" id="<?= e($railId) ?>">
          <?php foreach ($items as $itemIndex => $titleItem): ?>
            <?php
              $titleId = (int) $titleItem['id'];
              $cover = $assetOrFallback((string) ($titleItem['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
              $playable = $firstPlayableByTitle[$titleId] ?? null;
              $cardBadge = ((int) ($titleItem['featured'] ?? 0) === 1 || $itemIndex < 2) ? 'Em destaque' : '';
            ?>
            <article class="wp-title-card wp-title-card--cinema">
              <a href="/title/<?= $titleId ?>" class="wp-title-cover">
                <img src="<?= e($cover) ?>" alt="<?= e((string) $titleItem['title_name']) ?>" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
                <?php if ($cardBadge !== ''): ?><span class="wp-title-badge"><?= e($cardBadge) ?></span><?php endif; ?>
              </a>
              <div class="wp-title-meta wp-title-meta--cinema">
                <h3><?= e((string) $titleItem['title_name']) ?></h3>
                <p><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?> • <?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></p>
                <div class="wp-title-actions">
                  <?php if ($playable): ?>
                    <a class="wp-btn tiny light" href="/watch/<?= (int) $playable['id'] ?>">Assistir</a>
                  <?php endif; ?>
                  <a class="wp-btn tiny ghost" href="/title/<?= $titleId ?>">Detalhes</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php $rowIndex++; ?>
    <?php endforeach; ?>

    <?php if ($continueWatching !== []): ?>
      <section class="wp-row-section" id="row-continue">
        <div class="wp-row-head">
          <h2>Continue assistindo</h2>
        </div>
        <div class="wp-continue-grid">
          <?php foreach ($continueWatching as $item): ?>
            <?php
              $cover = $assetOrFallback((string) ($item['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
              $progress = max(0, min(100, (int) ($item['progress_percent'] ?? 0)));
            ?>
            <article class="wp-continue-card wp-continue-card--cinema">
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
  </div>
</main>
