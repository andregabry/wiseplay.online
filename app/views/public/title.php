<?php
$profile = $profile ?? [];
$titleItem = $titleItem ?? null;
$mediaHierarchy = $mediaHierarchy ?? [];
$firstPlayable = $firstPlayable ?? null;
$seasonTrailers = $seasonTrailers ?? [];
$heroTrailer = $heroTrailer ?? null;
$marketplaceAccess = $marketplaceAccess ?? ['is_locked' => 0, 'pay_url' => '/browse'];
$marketplaceOffers = $marketplaceOffers ?? [];

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

$splitCourseHeroTitle = static function (string $title): array {
    $normalized = preg_replace('/\s+/u', ' ', trim($title));
    if (!is_string($normalized) || $normalized === '') {
        return ['WisePlay'];
    }

    $words = preg_split('/\s+/u', $normalized) ?: [];
    if (count($words) <= 1) {
        return [$normalized];
    }

    $wordCount = count($words);
    $lineCount = ($wordCount > 4 || mb_strlen($normalized) > 26) ? 3 : 2;

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

    $totalLength = array_sum(array_map(static fn (string $word): int => mb_strlen($word), $words)) + max(0, $wordCount - 1);
    $targetLength = max(1, (int) ceil($totalLength / $lineCount));
    $lines = [];
    $currentLine = [];
    $currentLength = 0;
    $remainingLines = $lineCount;

    foreach ($words as $index => $word) {
        $wordLength = mb_strlen($word);
        $candidateLength = $currentLength === 0 ? $wordLength : $currentLength + 1 + $wordLength;
        $remainingWords = $wordCount - $index;
        $mustWrap = $currentLine !== [] && $remainingWords <= $remainingLines - 1;

        if (($candidateLength > $targetLength && $currentLine !== []) || $mustWrap) {
            $lines[] = implode(' ', $currentLine);
            $currentLine = [$word];
            $currentLength = $wordLength;
            $remainingLines--;
            continue;
        }

        $currentLine[] = $word;
        $currentLength = $candidateLength;
    }

    if ($currentLine !== []) {
        $lines[] = implode(' ', $currentLine);
    }

    return $lines;
};

$type = (string) ($titleItem['type'] ?? 'MOVIE');
$seasonLabel = $type === 'COURSE' ? 'Modulo' : 'Temporada';
$episodeLabel = $type === 'COURSE' ? 'Licao' : 'Episodio';
$episodesHeading = $type === 'MOVIE'
    ? 'Conteudo disponivel'
    : ($seasonLabel . 's e ' . strtolower($episodeLabel) . 's');
$backdrop = $assetOrFallback((string) ($titleItem['backdrop_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
$cover = $assetOrFallback((string) ($titleItem['cover_image'] ?? ''), '/assets/img/avatar-library/avatar-01.svg');
$isLocked = (int) ($marketplaceAccess['is_locked'] ?? 0) === 1;
$payUrl = (string) ($marketplaceAccess['pay_url'] ?? ('/title/' . (int) ($titleItem['id'] ?? 0) . '/pay'));
$heroActionUrl = $isLocked ? $payUrl : ($firstPlayable ? '/watch/' . (int) $firstPlayable['id'] : '/browse');
$heroActionLabel = $isLocked ? 'Desbloquear acesso' : 'Assistir';
$heroTitleLines = $type === 'COURSE'
    ? $splitCourseHeroTitle((string) ($titleItem['title_name'] ?? 'WisePlay'))
    : [(string) ($titleItem['title_name'] ?? 'WisePlay')];
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
      <h1 class="wp-title-hero-heading<?= $type === 'COURSE' ? ' is-course' : '' ?>">
        <?php foreach ($heroTitleLines as $heroTitleLine): ?>
          <span class="wp-hero-title-line"><?= e((string) $heroTitleLine) ?></span>
        <?php endforeach; ?>
      </h1>
      <div class="wp-title-hero-meta">
        <span><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?></span>
        <span><?= e((string) ($titleItem['genre'] ?? 'General')) ?></span>
        <span><?= e((string) (($titleItem['year'] ?? '') !== '' ? $titleItem['year'] : '-')) ?></span>
        <span><?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></span>
      </div>
      <p class="wp-title-hero-description"><?= e((string) (($titleItem['description'] ?? '') !== '' ? $titleItem['description'] : 'Sem descricao cadastrada.')) ?></p>

      <?php if ($isLocked): ?>
        <div class="wp-title-lock-note">
          <strong>Titulo bloqueado.</strong>
          <span>Escolha uma oferta para desbloquear este conteudo. <?= count($marketplaceOffers) ?> oferta(s) ativa(s) disponivel(is).</span>
        </div>
      <?php endif; ?>

      <div class="wp-title-hero-actions">
        <a class="wp-btn light" href="<?= e($heroActionUrl) ?>"><?= e($heroActionLabel) ?></a>
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
                $episodeHref = $isLocked ? $payUrl : ('/watch/' . (int) $episode['id']);
                $episodeCta = $isLocked ? 'Desbloquear' : 'Assistir';
              ?>
              <a class="wp-episode-item<?= $isLocked ? ' is-locked' : '' ?>" href="<?= e($episodeHref) ?>">
                <div class="wp-episode-order"><?= $type === 'MOVIE' ? 'FILME' : e($episodeLabel . ' ' . $epNo) ?></div>
                <div class="wp-episode-main">
                  <strong><?= e((string) $episode['item_label']) ?></strong>
                  <small>
                    <?= e((string) ($episode['source_type'] ?? 'URL')) ?>
                    <?php if ($duration > 0): ?> • <?= $duration ?> min<?php endif; ?>
                  </small>
                </div>
                <span class="wp-episode-cta"><?= e($episodeCta) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
