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
$imageFallback = '/assets/img/placeholders/image-placeholder.svg';

$assetOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};

$splitCourseHeroTitle = static function (string $title): array {
    $normalized = preg_replace('/\s+/u', ' ', trim($title));
    if (!is_string($normalized) || $normalized === '') {
        return ['Catalogo WisePlay'];
    }

    $words = preg_split('/\s+/u', $normalized) ?: [];
    if (count($words) <= 1) {
        return [$normalized];
    }

    $wordCount = count($words);
    $lineCount = ($wordCount > 4 || mb_strlen($normalized) > 26) ? 3 : 2;
    $lineCount = min($lineCount, $wordCount);

    if ($lineCount === 2) {
        $bestIndex = 1;
        $bestScore = PHP_INT_MAX;
        for ($index = 1; $index < $wordCount; $index++) {
            $left = implode(' ', array_slice($words, 0, $index));
            $right = implode(' ', array_slice($words, $index));
            $score = abs(mb_strlen($left) - mb_strlen($right));
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return [
            implode(' ', array_slice($words, 0, $bestIndex)),
            implode(' ', array_slice($words, $bestIndex)),
        ];
    }

    $lines = [];
    $remainingWords = $words;
    $remainingLines = $lineCount;
    while ($remainingLines > 1) {
        $remainingText = implode(' ', $remainingWords);
        $targetLength = (int) ceil(mb_strlen($remainingText) / $remainingLines);
        $currentLine = [];
        $currentLength = 0;

        while (count($remainingWords) > ($remainingLines - 1)) {
            $word = (string) $remainingWords[0];
            $nextLength = $currentLength + ($currentLength > 0 ? 1 : 0) + mb_strlen($word);
            if ($currentLine !== [] && $nextLength > $targetLength) {
                break;
            }

            $currentLine[] = array_shift($remainingWords);
            $currentLength = $nextLength;
        }

        if ($currentLine === []) {
            $currentLine[] = array_shift($remainingWords);
        }

        $lines[] = implode(' ', $currentLine);
        $remainingLines--;
    }

    $lines[] = implode(' ', $remainingWords);

    return array_values(array_filter($lines, static fn ($line): bool => trim((string) $line) !== ''));
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
$featuredMeta = $featured ? ($browseMetaByTitle[(int) $featured['id']] ?? []) : [];
$featuredIsLocked = (int) ($featuredMeta['is_locked'] ?? 0) === 1;
$featuredPayUrl = (string) ($featuredMeta['pay_url'] ?? ($featured ? '/title/' . (int) $featured['id'] . '/pay' : '/browse'));
$heroEyebrow = $featured ? strtoupper((string) ($featured['genre'] ?? 'WisePlay Original')) : 'WISEPLAY';
$heroTypeLabel = $featured
    ? ((string) ($featured['type'] ?? 'MOVIE') === 'MOVIE'
        ? 'O FILME'
        : (((string) ($featured['type'] ?? 'MOVIE') === 'COURSE' ? '' : 'A SERIE')))
    : 'ORIGINAL WISEPLAY';
$featuredTitleName = (string) ($featured['title_name'] ?? 'Catalogo WisePlay');
$featuredIsCourse = (string) ($featured['type'] ?? 'MOVIE') === 'COURSE';
$featuredHeroTitleLines = $featuredIsCourse ? $splitCourseHeroTitle($featuredTitleName) : [$featuredTitleName];
$heroCompactDescription = trim((string) (($featured['description'] ?? '') !== '' ? $featured['description'] : $appTagline));
$continueReference = $continueWatching[0]['title_name'] ?? null;
$featuredHeroPreviewUrl = is_array($featuredHeroTrailer) ? trim((string) ($featuredHeroTrailer['url'] ?? '')) : '';
$featuredHeroPreviewMime = is_array($featuredHeroTrailer) ? trim((string) ($featuredHeroTrailer['mime_type'] ?? '')) : '';
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
      <div class="wp-hero-logo-stack<?= $featuredIsCourse ? ' is-course' : '' ?>">
        <h1>
          <?php foreach ($featuredHeroTitleLines as $heroTitleLine): ?>
            <span class="wp-hero-title-line"><?= e((string) $heroTitleLine) ?></span>
          <?php endforeach; ?>
        </h1>
        <?php if ($heroTypeLabel !== ''): ?>
          <span><?= e($heroTypeLabel) ?></span>
        <?php endif; ?>
      </div>
      <p class="wp-hero-description"><?= e($heroCompactDescription) ?></p>

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
        <?php if ($featured): ?>
          <a class="wp-btn light wp-btn-hero-play" href="<?= e($featuredIsLocked ? $featuredPayUrl : ($featuredPlayable ? '/watch/' . (int) $featuredPlayable['id'] : '/title/' . (int) $featured['id'])) ?>">
            <span class="wp-btn-icon"><?= $featuredIsLocked ? '&#128274;' : '&#9658;' ?></span>
            <span><?= $featuredIsLocked ? 'Desbloquear' : 'Assistir' ?></span>
          </a>
        <?php endif; ?>
        <?php if ($featured): ?>
          <button
            type="button"
            class="wp-btn ghost wp-btn-hero-more js-open-title-modal"
            data-title-id="<?= (int) $featured['id'] ?>"
            <?= $featuredHeroPreviewUrl !== '' ? 'data-preview-url="' . e($featuredHeroPreviewUrl) . '"' : '' ?>
            <?= $featuredHeroPreviewMime !== '' ? 'data-preview-mime="' . e($featuredHeroPreviewMime) . '"' : '' ?>>
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
        $heading = $rowIndex === 0
            ? ('Porque voce viu ' . ($continueReference ? $continueReference : 'os seus favoritos'))
            : $rowLabel((string) $key);
      ?>
      <section class="wp-row-section<?= $rowIndex === 0 ? ' is-first' : '' ?>" id="<?= e($rowId) ?>">
        <div class="wp-row-head">
          <h2><?= e($heading) ?></h2>
        </div>

        <div class="wp-row-rail-scroll js-row-carousel" id="<?= e($railId) ?>" data-carousel-track=".wp-row-rail">
          <button type="button" class="wp-row-edge-btn wp-row-edge-btn--prev" data-row-prev="<?= e($railId) ?>" aria-label="Rolar para esquerda">&#10094;</button>
          <div class="wp-row-rail wp-row-rail--cinema">
            <?php foreach ($items as $itemIndex => $titleItem): ?>
            <?php
              $titleId = (int) $titleItem['id'];
              $cover = $assetOrFallback((string) ($titleItem['cover_image'] ?? ''), $imageFallback);
              $playable = $firstPlayableByTitle[$titleId] ?? null;
              $cardMeta = $browseMetaByTitle[$titleId] ?? [];
              $cardPreviewUrl = trim((string) ($cardMeta['preview_url'] ?? ''));
              $cardPreviewMime = trim((string) ($cardMeta['preview_mime'] ?? ''));
              $hoverBadges = (array) ($cardMeta['hover_badges'] ?? []);
              $hoverTags = (array) ($cardMeta['tags'] ?? []);
              $isLocked = (int) ($cardMeta['is_locked'] ?? 0) === 1;
              $payUrl = (string) ($cardMeta['pay_url'] ?? ('/title/' . $titleId . '/pay'));
              $primaryUrl = $isLocked ? $payUrl : ($playable ? '/watch/' . (int) $playable['id'] : '/title/' . $titleId);
              $isFeaturedTitle = (int) ($titleItem['featured'] ?? 0) === 1;
              $showTop10Badge = $rowIndex <= 1 && $itemIndex < 2;
              $highlightRibbon = '';
              if ($isFeaturedTitle) {
                  $highlightRibbon = 'Novidade';
              } elseif (($itemIndex % 4) === 1) {
                  $highlightRibbon = 'Nova temporada';
              }
            ?>
              <article class="wp-title-card wp-title-card--cinema<?= $cardPreviewUrl !== '' ? ' has-hover-preview' : '' ?>">
                <div class="wp-title-card-surface">
                  <a
                    href="<?= e($primaryUrl) ?>"
                    class="wp-title-cover<?= $isLocked ? ' is-locked' : ' js-open-title-modal' ?>"
                    data-title-id="<?= $titleId ?>"
                    <?= $cardPreviewUrl !== '' ? 'data-preview-url="' . e($cardPreviewUrl) . '"' : '' ?>
                    <?= $cardPreviewMime !== '' ? 'data-preview-mime="' . e($cardPreviewMime) . '"' : '' ?>>
                    <img src="<?= e($cover) ?>" alt="<?= e((string) $titleItem['title_name']) ?>" onerror="this.src='<?= e($imageFallback) ?>'">
                    <?php if ($cardPreviewUrl !== ''): ?>
                      <video class="wp-title-cover-preview js-card-hover-video" muted playsinline preload="none" poster="<?= e($cover) ?>" data-src="<?= e($cardPreviewUrl) ?>"<?= $cardPreviewMime !== '' ? ' data-mime="' . e($cardPreviewMime) . '"' : '' ?>></video>
                      <button type="button" class="wp-card-sound-toggle js-card-sound-toggle" aria-label="Ativar/desativar audio">&#128266;</button>
                    <?php endif; ?>
                    <?php if ($isLocked): ?><span class="wp-title-lock-pill">&#128274; Pago</span><?php endif; ?>
                    <?php if ($showTop10Badge): ?><span class="wp-title-badge-top10">TOP 10</span><?php endif; ?>
                    <?php if ($highlightRibbon !== ''): ?><span class="wp-title-badge-ribbon"><?= e($highlightRibbon) ?></span><?php endif; ?>
                  </a>
                  <div class="wp-title-hover-panel">
                    <div class="wp-title-hover-actions">
                      <a class="wp-hover-circle wp-hover-circle--play" href="<?= e($primaryUrl) ?>" aria-label="<?= $isLocked ? 'Desbloquear' : 'Assistir' ?>"><?= $isLocked ? '&#128274;' : '&#9658;' ?></a>
                      <button type="button" class="wp-hover-circle js-public-soon" data-label="Minha lista" aria-label="Adicionar a minha lista">+</button>
                      <button type="button" class="wp-hover-circle js-public-soon" data-label="Gostei" aria-label="Gostei deste titulo">&#128077;</button>
                      <button
                        type="button"
                        class="wp-hover-circle wp-hover-circle--chevron js-open-title-modal"
                        data-title-id="<?= $titleId ?>"
                        <?= $cardPreviewUrl !== '' ? 'data-preview-url="' . e($cardPreviewUrl) . '"' : '' ?>
                        <?= $cardPreviewMime !== '' ? 'data-preview-mime="' . e($cardPreviewMime) . '"' : '' ?>
                        aria-label="Mais informacoes">&#709;</button>
                    </div>
                    <?php if ($hoverBadges !== []): ?>
                      <div class="wp-title-hover-badges">
                        <?php foreach ($hoverBadges as $badge): ?>
                          <?php if (trim((string) $badge) === '') { continue; } ?>
                          <span><?= e((string) $badge) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($hoverTags !== []): ?>
                      <p class="wp-title-hover-tags"><?= e(implode(' • ', $hoverTags)) ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="wp-title-meta wp-title-meta--cinema">
                    <h3><?= e((string) $titleItem['title_name']) ?></h3>
                    <p><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?> • <?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></p>
                    <div class="wp-title-actions">
                      <a class="wp-btn tiny light" href="<?= e($primaryUrl) ?>"><?= $isLocked ? 'Desbloquear' : 'Assistir' ?></a>
                      <button
                        type="button"
                        class="wp-btn tiny ghost js-open-title-modal"
                        data-title-id="<?= $titleId ?>"
                        <?= $cardPreviewUrl !== '' ? 'data-preview-url="' . e($cardPreviewUrl) . '"' : '' ?>
                        <?= $cardPreviewMime !== '' ? 'data-preview-mime="' . e($cardPreviewMime) . '"' : '' ?>>Mais informacoes</button>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <button type="button" class="wp-row-edge-btn wp-row-edge-btn--next" data-row-next="<?= e($railId) ?>" aria-label="Rolar para direita">&#10095;</button>
        </div>
      </section>
      <?php $rowIndex++; ?>
    <?php endforeach; ?>

    <?php if ($continueWatching !== []): ?>
      <?php $continueRailId = 'wp-row-continue'; ?>
      <section class="wp-row-section" id="row-continue">
        <div class="wp-row-head">
          <h2>Continue assistindo</h2>
        </div>
        <div class="wp-continue-scroll js-row-carousel" id="<?= e($continueRailId) ?>" data-carousel-track=".wp-continue-grid">
          <button type="button" class="wp-row-edge-btn wp-row-edge-btn--prev" data-row-prev="<?= e($continueRailId) ?>" aria-label="Rolar para esquerda">&#10094;</button>
          <div class="wp-continue-grid">
            <?php foreach ($continueWatching as $item): ?>
            <?php
              $cover = $assetOrFallback((string) ($item['cover_image'] ?? ''), $imageFallback);
              $progress = max(0, min(100, (int) ($item['progress_percent'] ?? 0)));
              $titleId = (int) ($item['title_id'] ?? 0);
              $mediaId = (int) ($item['media_item_id'] ?? 0);
              $continueMeta = $browseMetaByTitle[$titleId] ?? [];
              $continuePreviewUrl = trim((string) ($continueMeta['preview_url'] ?? ''));
              $continuePreviewMime = trim((string) ($continueMeta['preview_mime'] ?? ''));
              $continueBadges = array_values(array_filter((array) ($continueMeta['hover_badges'] ?? []), static fn ($v) => trim((string) $v) !== ''));
            ?>
              <article class="wp-continue-card wp-continue-card--cinema wp-title-card wp-title-card--cinema<?= $continuePreviewUrl !== '' ? ' has-hover-preview' : '' ?>">
                <div class="wp-title-card-surface">
                  <a href="/watch/<?= $mediaId ?>" class="wp-title-cover">
                    <img src="<?= e($cover) ?>" alt="<?= e((string) $item['title_name']) ?>" onerror="this.src='<?= e($imageFallback) ?>'">
                    <?php if ($continuePreviewUrl !== ''): ?>
                      <video class="wp-title-cover-preview js-card-hover-video" muted playsinline preload="none" poster="<?= e($cover) ?>" data-src="<?= e($continuePreviewUrl) ?>"<?= $continuePreviewMime !== '' ? ' data-mime="' . e($continuePreviewMime) . '"' : '' ?>></video>
                      <button type="button" class="wp-card-sound-toggle js-card-sound-toggle" aria-label="Ativar/desativar audio">&#128266;</button>
                    <?php endif; ?>
                  </a>
                  <div class="wp-continue-body">
                    <h3><?= e((string) $item['title_name']) ?></h3>
                    <p><?= e((string) $item['item_label']) ?></p>
                    <div class="wp-progress"><span style="width: <?= $progress ?>%"></span></div>
                  </div>
                  <div class="wp-title-hover-panel wp-title-hover-panel--continue">
                    <div class="wp-title-hover-actions">
                      <a class="wp-hover-circle wp-hover-circle--play" href="/watch/<?= $mediaId ?>" aria-label="Assistir">&#9658;</a>
                      <button type="button" class="wp-hover-circle js-public-soon" data-label="Minha lista" aria-label="Adicionar a minha lista">+</button>
                      <button type="button" class="wp-hover-circle js-public-soon" data-label="Remover da lista" aria-label="Remover da minha lista">&times;</button>
                      <button type="button" class="wp-hover-circle js-public-soon" data-label="Gostei" aria-label="Gostei deste titulo">&#128077;</button>
                      <button
                        type="button"
                        class="wp-hover-circle wp-hover-circle--chevron js-open-title-modal"
                        data-title-id="<?= $titleId ?>"
                        <?= $continuePreviewUrl !== '' ? 'data-preview-url="' . e($continuePreviewUrl) . '"' : '' ?>
                        <?= $continuePreviewMime !== '' ? 'data-preview-mime="' . e($continuePreviewMime) . '"' : '' ?>
                        aria-label="Mais informacoes">&#709;</button>
                    </div>
                    <?php if ($continueBadges !== []): ?>
                      <div class="wp-title-hover-badges">
                        <?php foreach ($continueBadges as $badge): ?>
                          <span><?= e((string) $badge) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <div class="wp-continue-hover-copy">
                      <p><?= e((string) $item['item_label']) ?></p>
                      <div class="wp-continue-hover-progress-meta">
                        <div class="wp-continue-hover-progress-line">
                          <span class="bar" style="width: <?= $progress ?>%"></span>
                        </div>
                        <span><?= $progress ?>%</span>
                      </div>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <button type="button" class="wp-row-edge-btn wp-row-edge-btn--next" data-row-next="<?= e($continueRailId) ?>" aria-label="Rolar para direita">&#10095;</button>
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
        <div class="wp-title-modal-actions">
          <a class="wp-btn light" id="wp-title-modal-watch" href="/browse">Assistir</a>
          <button type="button" class="wp-hover-circle wp-hover-circle--light js-public-soon" data-label="Minha lista" aria-label="Adicionar a minha lista">+</button>
          <button type="button" class="wp-hover-circle wp-hover-circle--light js-public-soon" data-label="Gostei" aria-label="Gostei deste titulo">&#128077;</button>
          <a class="wp-btn ghost" id="wp-title-modal-details" href="/browse">Voltar ao catalogo</a>
        </div>
        <div class="wp-title-modal-badges" id="wp-title-modal-badges"></div>
      </div>
    </div>
    <div class="wp-title-modal-body">
      <div class="wp-title-modal-summary">
        <div class="wp-title-modal-summary-main">
          <div class="wp-title-modal-meta-line" id="wp-title-modal-meta-line"></div>
          <h3 id="wp-title-modal-hook">More information</h3>
          <p id="wp-title-modal-description"></p>
        </div>
        <div class="wp-title-modal-summary-side" id="wp-title-modal-summary-side"></div>
      </div>
      <div class="wp-title-modal-section-head">
        <h4 id="wp-title-modal-episodes-heading">Episodios</h4>
        <span id="wp-title-modal-season-pick"></span>
      </div>
      <div class="wp-title-modal-episodes" id="wp-title-modal-episodes"></div>
      <div class="wp-title-modal-episodes-toggle-wrap" id="wp-title-modal-episodes-toggle-wrap" hidden>
        <button
          type="button"
          class="wp-title-modal-episodes-toggle"
          id="wp-title-modal-episodes-toggle"
          aria-label="Mostrar mais episodios"
          aria-expanded="false">&#709;</button>
      </div>
    </div>
  </div>
</div>

<div id="wp-browse-meta-store" data-items='<?= e((string) json_encode($browseMetaByTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>' hidden></div>
