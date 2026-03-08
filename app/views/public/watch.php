<?php
$profile = $profile ?? [];
$mediaItem = $mediaItem ?? null;
$episodeQueue = $episodeQueue ?? [];
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
$titleSummary = trim((string) ($mediaItem['title_description'] ?? ''));
$thumbFallback = trim((string) ($mediaItem['cover_image'] ?? ''));
if ($thumbFallback === '' || str_ends_with($thumbFallback, '.gitkeep')) {
    $thumbFallback = '/assets/img/placeholders/image-placeholder.svg';
}
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

$subtitleTracks = [];
$subtitlesJsonRaw = trim((string) ($mediaItem['subtitles_json'] ?? ''));
if ($subtitlesJsonRaw !== '') {
    $decodedSubtitles = json_decode($subtitlesJsonRaw, true);
    if (is_array($decodedSubtitles)) {
        foreach ($decodedSubtitles as $subtitleItem) {
            if (!is_array($subtitleItem)) {
                continue;
            }
            $trackSrc = trim((string) ($subtitleItem['downloadUrl'] ?? $subtitleItem['webUrl'] ?? $subtitleItem['url'] ?? $subtitleItem['source_path'] ?? ''));
            if ($trackSrc === '' || !preg_match('#^https?://#i', $trackSrc)) {
                continue;
            }
            $trackLang = trim((string) ($subtitleItem['language'] ?? 'und'));
            if ($trackLang === '') {
                $trackLang = 'und';
            }
            $trackLabel = trim((string) ($subtitleItem['name'] ?? strtoupper($trackLang)));
            if ($trackLabel === '') {
                $trackLabel = strtoupper($trackLang);
            }
            $subtitleTracks[] = [
                'src' => $trackSrc,
                'srclang' => $trackLang,
                'label' => $trackLabel,
            ];
        }
    }
}

$audioLangs = [];
$audioLangRaw = trim((string) ($mediaItem['audio_lang'] ?? ''));
if ($audioLangRaw !== '') {
    $parts = preg_split('/\s*,\s*/', $audioLangRaw);
    if (is_array($parts)) {
        foreach ($parts as $lang) {
            $lang = trim((string) $lang);
            if ($lang !== '') {
                $audioLangs[] = $lang;
            }
        }
    }
}
$audioLangs = array_values(array_unique($audioLangs));
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-watch-page" data-watch-media-id="<?= (int) $mediaItem['id'] ?>" data-watch-title-id="<?= (int) $mediaItem['title_id'] ?>">
  <section class="wp-player-wrap">
    <div class="wp-player-head">
      <a href="/browse" class="wp-btn tiny ghost">&larr; Voltar</a>
      <h1><?= e((string) $mediaItem['title_name']) ?></h1>
    </div>

    <div class="wp-player-box">
      <?php if ($canEmbedVideo): ?>
        <video
          preload="metadata"
          id="wp-video-element"
          data-source-url="<?= e($sourcePath) ?>"
          data-source-mime="<?= e($sourceMimeForTag) ?>"
          data-source-is-ts="<?= $isTsSource ? '1' : '0' ?>">
          <source src="<?= e($sourcePath) ?>"<?= $sourceMimeForTag !== '' ? ' type="' . e($sourceMimeForTag) . '"' : '' ?>>
          <?php foreach ($subtitleTracks as $index => $track): ?>
            <track
              kind="subtitles"
              src="<?= e((string) $track['src']) ?>"
              srclang="<?= e((string) $track['srclang']) ?>"
              label="<?= e((string) $track['label']) ?>"
              <?= $index === 0 ? 'default' : '' ?>>
          <?php endforeach; ?>
        </video>

        <div class="wp-player-chrome">
          <div class="wp-player-progress-wrap">
            <input id="wp-player-progress" class="wp-player-progress" type="range" min="0" max="100" step="1" value="0" data-watch-progress>
          </div>
          <div class="wp-player-controls-row">
            <div class="wp-player-controls-group is-left">
              <button type="button" class="wp-player-control js-player-play" aria-label="Reproduzir ou pausar">
                <span class="icon js-player-play-icon">&#9654;</span>
              </button>
              <button type="button" class="wp-player-control js-player-seek" data-seek="-10" aria-label="Voltar 10 segundos">
                <span class="icon">&#8634;</span>
              </button>
              <button type="button" class="wp-player-control js-player-seek" data-seek="10" aria-label="Avancar 10 segundos">
                <span class="icon">&#8635;</span>
              </button>
            </div>
            <div class="wp-player-controls-group is-center">
              <span class="wp-player-caption"><?= e((string) $mediaItem['item_label']) ?></span>
            </div>
            <div class="wp-player-controls-group is-right">
              <button type="button" class="wp-player-control js-player-mute" aria-label="Ativar ou desativar som">
                <span class="icon js-player-mute-icon">&#128266;</span>
              </button>
              <button
                type="button"
                class="wp-player-control js-watch-episodes-toggle"
                data-target="#wp-watch-episodes-panel"
                aria-controls="wp-watch-episodes-panel"
                aria-expanded="false"
                aria-label="Lista de episodios">
                <span class="icon">&#9776;</span>
              </button>
              <button type="button" class="wp-player-control js-player-settings-toggle" aria-label="Configuracoes do player" aria-expanded="false">
                <span class="icon">&#8942;</span>
              </button>
              <button type="button" class="wp-player-control js-player-fullscreen" aria-label="Tela cheia">
                <span class="icon">&#9974;</span>
              </button>
            </div>
          </div>

          <div class="wp-player-settings-panel" hidden>
            <div class="wp-player-setting-group">
              <label for="wp-player-audio-select">Idioma</label>
              <select id="wp-player-audio-select" class="js-player-audio-select">
                <option value="auto">Original</option>
                <?php foreach ($audioLangs as $audioLang): ?>
                  <option value="<?= e($audioLang) ?>"><?= e($audioLang) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="wp-player-setting-group">
              <label for="wp-player-subtitle-select">Legenda</label>
              <select id="wp-player-subtitle-select" class="js-player-subtitle-select">
                <option value="off">Desativada</option>
              </select>
            </div>
            <div class="wp-player-setting-group">
              <label for="wp-player-speed-select">Velocidade</label>
              <select id="wp-player-speed-select" class="js-player-speed-select">
                <option value="0.75">0.75x</option>
                <option value="1" selected>1x (Normal)</option>
                <option value="1.25">1.25x</option>
                <option value="1.5">1.5x</option>
                <option value="1.75">1.75x</option>
                <option value="2">2x</option>
              </select>
            </div>
          </div>
        </div>

        <div class="wp-watch-episodes-panel" id="wp-watch-episodes-panel" hidden>
          <div class="wp-watch-episodes-panel__head">
            <strong><?= e((string) (((string) ($mediaItem['type'] ?? 'SERIES')) === 'COURSE' ? 'Curso' : 'Minissérie')) ?></strong>
          </div>
          <div class="wp-watch-episodes-panel__list">
            <?php
              $panelItems = $episodeQueue !== [] ? $episodeQueue : array_merge([$mediaItem], $suggestions);
              $sequence = 1;
            ?>
            <?php foreach ($panelItems as $panelItem): ?>
              <?php
                $panelId = (int) ($panelItem['id'] ?? 0);
                if ($panelId <= 0) {
                    continue;
                }
                $isActiveEpisode = $panelId === (int) $mediaItem['id'];
                $episodeNumber = (int) ($panelItem['episode_number'] ?? 0);
                $seasonNumber = (int) ($panelItem['season_number'] ?? 0);
                $episodeIndex = $episodeNumber > 0 ? $episodeNumber : $sequence;
                $durationMinutes = (int) ($panelItem['duration_minutes'] ?? 0);
                $episodeThumb = trim((string) ($panelItem['thumbnail_url'] ?? ''));
                if ($episodeThumb === '' || str_ends_with($episodeThumb, '.gitkeep')) {
                    $episodeThumb = $thumbFallback;
                }
                $episodeSummary = trim((string) ($panelItem['summary'] ?? ''));
                if ($episodeSummary === '') {
                    $episodeSummary = $titleSummary !== '' ? $titleSummary : 'Sem descricao detalhada para este episodio/aula.';
                }
                if (strlen($episodeSummary) > 170) {
                    $episodeSummary = rtrim(substr($episodeSummary, 0, 167)) . '...';
                }
              ?>
              <a href="/watch/<?= $panelId ?>" class="wp-watch-episodes-item<?= $isActiveEpisode ? ' is-active' : '' ?>">
                <span class="wp-watch-episodes-item-index"><?= (int) $episodeIndex ?></span>
                <div class="wp-watch-episodes-item-main">
                  <strong>
                    <?= e((string) ($panelItem['item_label'] ?? 'Episodio')) ?>
                    <?php if ($durationMinutes > 0): ?><span class="wp-watch-episodes-item-duration"><?= $durationMinutes ?>min</span><?php endif; ?>
                  </strong>
                  <?php if ($isActiveEpisode): ?>
                    <div class="wp-watch-episodes-item-detail">
                      <img src="<?= e($episodeThumb) ?>" alt="<?= e((string) ($panelItem['item_label'] ?? 'Episodio')) ?>" onerror="this.src='<?= e($thumbFallback) ?>'">
                      <p><?= e($episodeSummary) ?></p>
                    </div>
                  <?php else: ?>
                    <small>
                      <?php if ($seasonNumber > 0): ?>T<?= $seasonNumber ?><?php endif; ?>
                      <?php if ($episodeNumber > 0): ?>E<?= $episodeNumber ?><?php endif; ?>
                    </small>
                  <?php endif; ?>
                </div>
                <span class="wp-watch-episodes-item-line"></span>
              </a>
              <?php $sequence++; ?>
            <?php endforeach; ?>
          </div>
        </div>
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
    <input type="hidden" data-watch-csrf value="<?= e(App\core\Csrf::token()) ?>">

    <div class="wp-watch-meta">
      <h2><?= e((string) $mediaItem['item_label']) ?></h2>
      <p>
        <?= e((string) $sourceType) ?>
        <?php if ($duration > 0): ?> • <?= $duration ?> min<?php endif; ?>
        <?php if ((int) ($mediaItem['season_number'] ?? 0) > 0): ?> • T<?= (int) $mediaItem['season_number'] ?><?php endif; ?>
        <?php if ((int) ($mediaItem['episode_number'] ?? 0) > 0): ?>E<?= (int) $mediaItem['episode_number'] ?><?php endif; ?>
      </p>
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
