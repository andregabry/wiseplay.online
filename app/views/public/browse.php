<?php
$profile = $profile ?? [];
$availableProfiles = $availableProfiles ?? [$profile];
$selectedProfileId = (int) ($selectedProfileId ?? (int) ($profile['id'] ?? 0));
$featured = $featured ?? null;
$rows = $rows ?? [];
$continueWatching = $continueWatching ?? [];
$subscription = $subscription ?? null;
$firstPlayableByTitle = $firstPlayableByTitle ?? [];
$browseMetaByTitle = $browseMetaByTitle ?? [];
$featuredHeroTrailer = $featuredHeroTrailer ?? null;
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
    <?php if (is_array($featuredHeroTrailer) && ((string) ($featuredHeroTrailer['url'] ?? '')) !== ''): ?>
      <?php $featuredTrailerMime = trim((string) ($featuredHeroTrailer['mime_type'] ?? '')); ?>
      <div class="wp-hero-preview-wrap">
        <video
          class="wp-hero-preview-video js-loop-trailer js-hero-preview"
          data-loop-seconds="45"
          autoplay
          muted
          playsinline
          preload="metadata"
          poster="<?= e($featuredImage) ?>">
          <source src="<?= e((string) $featuredHeroTrailer['url']) ?>"<?= $featuredTrailerMime !== '' ? ' type="' . e($featuredTrailerMime) . '"' : '' ?>>
        </video>
      </div>
    <?php endif; ?>

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
          <button type="button" class="wp-btn ghost wp-btn-hero-more js-open-title-modal" data-title-id="<?= (int) $featured['id'] ?>">
            <span class="wp-btn-icon">i</span>
            <span>Mais informacoes</span>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="wp-hero-floating-meta">
      <button type="button" class="wp-hero-sound js-hero-sound-toggle" data-target=".js-hero-preview" aria-label="Ativar/desativar audio">
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
              <?php
                $cardMeta = $browseMetaByTitle[$titleId] ?? [];
                $cardPreviewUrl = trim((string) ($cardMeta['preview_url'] ?? ''));
                $cardPreviewMime = trim((string) ($cardMeta['preview_mime'] ?? ''));
              ?>
              <div class="wp-title-hover-panel">
                <div class="wp-title-hover-media">
                  <?php if ($cardPreviewUrl !== ''): ?>
                    <video class="wp-title-hover-video js-card-hover-video" muted playsinline preload="none" poster="<?= e($cover) ?>" data-src="<?= e($cardPreviewUrl) ?>"<?= $cardPreviewMime !== '' ? ' data-mime="' . e($cardPreviewMime) . '"' : '' ?>></video>
                  <?php else: ?>
                    <img src="<?= e($cover) ?>" alt="<?= e((string) $titleItem['title_name']) ?>" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
                  <?php endif; ?>
                </div>
                <div class="wp-title-hover-actions">
                  <?php if ($playable): ?>
                    <a class="wp-hover-circle" href="/watch/<?= (int) $playable['id'] ?>" aria-label="Assistir">&#9658;</a>
                  <?php endif; ?>
                  <button type="button" class="wp-hover-circle js-open-title-modal" data-title-id="<?= $titleId ?>" aria-label="Mais informacoes">i</button>
                </div>
              </div>
              <div class="wp-title-meta wp-title-meta--cinema">
                <h3><?= e((string) $titleItem['title_name']) ?></h3>
                <p><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?> • <?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></p>
                <div class="wp-title-actions">
                  <?php if ($playable): ?>
                    <a class="wp-btn tiny light" href="/watch/<?= (int) $playable['id'] ?>">Assistir</a>
                  <?php endif; ?>
                  <button type="button" class="wp-btn tiny ghost js-open-title-modal" data-title-id="<?= $titleId ?>">Mais informacoes</button>
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

<div class="wp-title-modal" id="wp-title-info-modal" hidden>
  <div class="wp-title-modal-backdrop js-close-title-modal"></div>
  <div class="wp-title-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wp-title-modal-heading">
    <button type="button" class="wp-title-modal-close js-close-title-modal" aria-label="Fechar">×</button>
    <div class="wp-title-modal-hero" id="wp-title-modal-hero">
      <div class="wp-title-modal-hero-media" id="wp-title-modal-hero-media"></div>
      <div class="wp-title-modal-hero-overlay"></div>
      <div class="wp-title-modal-hero-content">
        <h2 id="wp-title-modal-heading">Titulo</h2>
        <p id="wp-title-modal-description"></p>
        <div class="wp-title-modal-actions">
          <a class="wp-btn light" id="wp-title-modal-watch" href="/browse">Assistir</a>
          <a class="wp-btn ghost" id="wp-title-modal-details" href="/browse">Pagina do titulo</a>
        </div>
        <div class="wp-title-modal-badges" id="wp-title-modal-badges"></div>
      </div>
    </div>
  </div>
</div>

<div id="wp-browse-meta-store" data-items='<?= e((string) json_encode($browseMetaByTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>' hidden></div>
