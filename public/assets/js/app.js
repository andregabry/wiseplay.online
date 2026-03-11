(function ($) {
  'use strict';

  function fireAlert(options) {
    if (typeof Swal === 'undefined') {
      return null;
    }
    return Swal.fire($.extend(true, {
      scrollbarPadding: false
    }, options || {}));
  }

  function showFlash() {
    var $flash = $('#flash-data');
    if (!$flash.length || typeof Swal === 'undefined') {
      return;
    }

    var type = String($flash.data('type') || 'info');
    var message = String($flash.data('message') || '');
    var icon = type === 'success' ? 'success' : (type === 'error' ? 'error' : 'info');
    var title = type === 'success' ? 'Sucesso' : (type === 'error' ? 'Erro' : 'Aviso');

    fireAlert({
      icon: icon,
      title: title,
      text: message,
      confirmButtonText: 'OK'
    });
  }

  function bindRowNavigation() {
    var CAROUSEL_SELECTOR = '.js-row-carousel[id]';
    var EDGE_TRIGGER_PX = 116;
    var TRANSITION_MS = 420;
    var registry = {};
    var resizeTimer = null;

    function clamp(value, min, max) {
      return Math.max(min, Math.min(max, value));
    }

    function resolveTrack($viewport) {
      var selector = String($viewport.data('carouselTrack') || '').trim();
      if (selector !== '') {
        return $viewport.find(selector).first();
      }
      return $viewport.children('.wp-row-rail, .wp-continue-grid').first();
    }

    function applyOffset(state, immediate) {
      if (!state || !state.$track.length) {
        return;
      }

      var x = -Math.round(state.offset);
      if (immediate) {
        state.$track.css('transition', 'none');
        state.$track.css('transform', 'translate3d(' + x + 'px, 0, 0)');
        window.requestAnimationFrame(function () {
          state.$track.css('transition', '');
        });
        return;
      }

      state.$track.css('transform', 'translate3d(' + x + 'px, 0, 0)');
    }

    function updateButtons(state) {
      if (!state || !state.$prev.length || !state.$next.length) {
        return;
      }

      var canPrev = state.offset > 1;
      var canNext = state.offset < (state.maxOffset - 1);
      var showPrev = false;
      var showNext = false;

      if (state.hover && state.maxOffset > 1) {
        if (state.side === 'left' && canPrev) {
          showPrev = true;
        } else if (state.side === 'right' && canNext) {
          showNext = true;
        }
      }

      state.$prev.toggleClass('is-visible', showPrev);
      state.$next.toggleClass('is-visible', showNext);
    }

    function measure(state) {
      if (!state || !state.$viewport.length || !state.$track.length) {
        return;
      }

      var viewportEl = state.$viewport.get(0);
      var trackEl = state.$track.get(0);
      if (!viewportEl || !trackEl) {
        return;
      }

      state.viewportWidth = Math.round(state.$viewport.innerWidth() || viewportEl.clientWidth || 0);
      state.trackWidth = Math.round(trackEl.scrollWidth || state.$track.outerWidth() || 0);
      state.maxOffset = Math.max(0, state.trackWidth - state.viewportWidth);
      state.offset = clamp(state.offset, 0, state.maxOffset);

      applyOffset(state, true);
      updateButtons(state);
    }

    function stepSize(state) {
      var $first = state.$track.children().first();
      var cardWidth = Math.round($first.outerWidth(true) || 0);
      if (cardWidth <= 0) {
        return Math.max(260, Math.floor(state.viewportWidth * 0.9));
      }

      var visibleCards = Math.max(1, Math.floor(state.viewportWidth / cardWidth));
      var cardsPerMove = Math.max(1, visibleCards - 1);
      return Math.max(cardWidth, cardsPerMove * cardWidth);
    }

    function move(state, direction) {
      if (!state || state.maxOffset <= 0) {
        return;
      }

      var target = clamp(state.offset + (stepSize(state) * direction), 0, state.maxOffset);
      if (Math.abs(target - state.offset) < 1) {
        return;
      }

      state.offset = target;
      applyOffset(state, false);
      updateButtons(state);

      window.setTimeout(function () {
        measure(state);
      }, TRANSITION_MS + 40);
    }

    function updateSideByPointer(state, clientX) {
      if (!state || !state.$viewport.length) {
        return;
      }

      var rect = state.$viewport.get(0).getBoundingClientRect();
      var localX = clientX - rect.left;
      var zone = Math.min(EDGE_TRIGGER_PX, Math.floor(rect.width * 0.22));

      if (localX <= zone) {
        state.side = 'left';
      } else if (localX >= (rect.width - zone)) {
        state.side = 'right';
      } else {
        state.side = '';
      }
      updateButtons(state);
    }

    function setupCarousel($viewport) {
      var rowId = String($viewport.attr('id') || '');
      if (rowId === '') {
        return;
      }

      var $track = resolveTrack($viewport);
      if (!$track.length) {
        return;
      }

      var state = {
        rowId: rowId,
        $viewport: $viewport,
        $track: $track,
        $prev: $viewport.find('[data-row-prev="' + rowId + '"]').first(),
        $next: $viewport.find('[data-row-next="' + rowId + '"]').first(),
        hover: false,
        side: '',
        offset: 0,
        maxOffset: 0,
        viewportWidth: 0,
        trackWidth: 0
      };

      registry[rowId] = state;
      measure(state);

      var viewportEl = $viewport.get(0);
      if (viewportEl) {
        viewportEl.scrollLeft = 0;
        viewportEl.scrollTop = 0;
      }

      $viewport.on('mouseenter', function () {
        state.hover = true;
        updateButtons(state);
      });

      $viewport.on('mouseleave', function () {
        state.hover = false;
        state.side = '';
        updateButtons(state);
      });

      $viewport.on('mousemove', function (event) {
        if (state.maxOffset <= 0) {
          return;
        }
        updateSideByPointer(state, event.clientX);
      });

      $viewport.on('focusin', '.wp-row-edge-btn', function () {
        state.hover = true;
        state.side = $(this).is('[data-row-prev]') ? 'left' : 'right';
        updateButtons(state);
      });

      $viewport.find('img').on('load', function () {
        measure(state);
      });

      // Hard-stop any residual native scrolling inside the row viewport.
      $viewport.on('scroll wheel touchmove', function () {
        this.scrollLeft = 0;
        this.scrollTop = 0;
      });
    }

    $(CAROUSEL_SELECTOR).each(function () {
      setupCarousel($(this));
    });

    $(document).on('click', '[data-row-prev], [data-row-next]', function (event) {
      var rowId = String($(this).data('rowPrev') || $(this).data('rowNext') || '');
      if (rowId === '') {
        return;
      }

      var state = registry[rowId];
      if (!state) {
        return;
      }

      event.preventDefault();
      var direction = $(this).is('[data-row-prev]') ? -1 : 1;
      state.hover = true;
      state.side = direction < 0 ? 'left' : 'right';
      move(state, direction);
    });

    $(window).on('resize orientationchange', function () {
      if (resizeTimer) {
        window.clearTimeout(resizeTimer);
      }
      resizeTimer = window.setTimeout(function () {
        Object.keys(registry).forEach(function (key) {
          measure(registry[key]);
        });
      }, 140);
    });
  }

  function bindWatchProgress() {
    var $watchRoot = $('[data-watch-media-id]');
    if (!$watchRoot.length) {
      return;
    }

    var mediaId = parseInt($watchRoot.data('watchMediaId'), 10) || 0;
    if (mediaId <= 0) {
      return;
    }

    var endpoint = (window.WISEPLAY && window.WISEPLAY.watchProgressEndpoint) || '/watch/progress';
    var csrf = String($('[data-watch-csrf]').val() || (window.WISEPLAY && window.WISEPLAY.csrf) || '');
    var $range = $('[data-watch-progress]');
    var $video = $('#wp-video-element');
    var durationMinutes = parseInt(($('.wp-watch-meta p').text().match(/(\d+)\s*min/) || [])[1], 10) || 0;
    var lastSentAt = 0;

    function initTsPlayback() {
      if (!$video.length) {
        return;
      }

      var videoEl = $video.get(0);
      if (!videoEl) {
        return;
      }

      var sourceIsTs = String($video.data('sourceIsTs') || '0') === '1';
      if (!sourceIsTs) {
        return;
      }

      var sourceUrl = String($video.data('sourceUrl') || '');
      if (sourceUrl === '') {
        return;
      }

      var player = null;
      var fallbackApplied = false;
      function fallbackToNative(reason) {
        if (fallbackApplied) {
          return;
        }
        fallbackApplied = true;

        try {
          if (player && typeof player.destroy === 'function') {
            player.destroy();
          }
        } catch (destroyError) {
          // noop
        }

        try {
          videoEl.src = sourceUrl;
          videoEl.load();
        } catch (nativeError) {
          // noop
        }

        if (typeof console !== 'undefined' && console.warn) {
          console.warn('[WisePlay] fallback para player nativo .ts', reason || '');
        }
      }

      if (typeof window.mpegts === 'undefined' || typeof window.mpegts.createPlayer !== 'function') {
        fallbackToNative('mpegts.js indisponivel');
        return;
      }

      if (typeof window.mpegts.isSupported === 'function' && !window.mpegts.isSupported()) {
        fallbackToNative('MSE nao suportado');
        return;
      }

      try {
        player = window.mpegts.createPlayer({
          type: 'mse',
          isLive: false,
          url: sourceUrl,
          cors: true
        }, {
          enableWorker: true,
          lazyLoad: true,
          lazyLoadMaxDuration: 180
        });

        player.attachMediaElement(videoEl);
        player.load();

        if (window.mpegts.Events && typeof player.on === 'function') {
          player.on(window.mpegts.Events.ERROR, function () {
            if (typeof console !== 'undefined' && console.error) {
              console.error('[WisePlay] Falha no playback .ts via mpegts.js', arguments);
            }
            fallbackToNative('erro no mpegts.js');
          });
        }

        $(window).on('beforeunload', function () {
          try {
            if (player && typeof player.destroy === 'function') {
              player.destroy();
            }
          } catch (error) {
            // noop
          }
        });
      } catch (error) {
        if (typeof console !== 'undefined' && console.error) {
          console.error('[WisePlay] Nao foi possivel inicializar player .ts', error);
        }
        fallbackToNative('falha ao inicializar mpegts.js');
      }
    }

    function payload(progress, position) {
      return {
        _csrf: csrf,
        media_id: mediaId,
        progress_percent: Math.max(0, Math.min(100, parseInt(progress, 10) || 0)),
        last_position_seconds: Math.max(0, parseInt(position, 10) || 0)
      };
    }

    function ajaxSend(progress, position, force) {
      var now = Date.now();
      if (!force && now - lastSentAt < 8000) {
        return;
      }
      lastSentAt = now;

      $.ajax({
        url: endpoint,
        method: 'POST',
        dataType: 'json',
        data: payload(progress, position),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    }

    function currentPositionFromRange(progressValue) {
      if (durationMinutes <= 0) {
        return 0;
      }
      return Math.floor((durationMinutes * 60) * (progressValue / 100));
    }

    if ($range.length) {
      $range.on('input change', function () {
        var progress = parseInt($(this).val(), 10) || 0;

        if ($video.length && $video[0].duration && Number.isFinite($video[0].duration)) {
          var nextTime = Math.floor(($video[0].duration * progress) / 100);
          if (!Number.isNaN(nextTime)) {
            $video[0].currentTime = nextTime;
          }
          ajaxSend(progress, nextTime, true);
          return;
        }

        ajaxSend(progress, currentPositionFromRange(progress), true);
      });
    }

    initTsPlayback();

    $(document).on('click', '[data-watch-mark]', function () {
      var progress = parseInt($(this).data('watchMark'), 10) || 0;
      if ($range.length) {
        $range.val(progress).trigger('change');
      } else {
        ajaxSend(progress, currentPositionFromRange(progress), true);
      }
    });

    if ($video.length) {
      $video.on('timeupdate', function () {
        var video = this;
        if (!video.duration || !Number.isFinite(video.duration)) {
          return;
        }

        var progress = Math.floor((video.currentTime / video.duration) * 100);
        if ($range.length) {
          $range.val(progress);
        }
      });

      var ticker = window.setInterval(function () {
        var video = $video[0];
        if (!video || video.paused || !video.duration || !Number.isFinite(video.duration)) {
          return;
        }

        var progress = Math.floor((video.currentTime / video.duration) * 100);
        ajaxSend(progress, Math.floor(video.currentTime), false);
      }, 15000);

      $(window).on('beforeunload', function () {
        window.clearInterval(ticker);
        var video = $video[0];
        if (!video || !video.duration || !Number.isFinite(video.duration)) {
          return;
        }

        var progress = Math.floor((video.currentTime / video.duration) * 100);

        if (navigator.sendBeacon && csrf !== '') {
          var body = new URLSearchParams(payload(progress, Math.floor(video.currentTime))).toString();
          navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' }));
          return;
        }

        ajaxSend(progress, Math.floor(video.currentTime), true);
      });
      return;
    }

    window.setInterval(function () {
      if (!$range.length) {
        return;
      }
      var progress = parseInt($range.val(), 10) || 0;
      ajaxSend(progress, currentPositionFromRange(progress), false);
    }, 18000);
  }

  function bindWatchPlayerOverlay() {
    var $box = $('.wp-player-box');
    if (!$box.length) {
      return;
    }

    var $video = $('#wp-video-element');
    if (!$video.length) {
      return;
    }

    var $episodesToggle = $('.js-watch-episodes-toggle');
    var $episodesPanel = $('.wp-watch-episodes-panel');
    var $settingsToggle = $('.js-player-settings-toggle');
    var $settingsPanel = $('.wp-player-settings-panel');
    var $audioSelect = $('.js-player-audio-select');
    var $subtitleSelect = $('.js-player-subtitle-select');
    var $speedSelect = $('.js-player-speed-select');
    var $playToggle = $('.js-player-play');
    var $playIcon = $('.js-player-play-icon');
    var $muteToggle = $('.js-player-mute');
    var $muteIcon = $('.js-player-mute-icon');
    var $seekToggle = $('.js-player-seek');
    var $fullscreenToggle = $('.js-player-fullscreen');
    var video = $video.get(0);
    var controlsHideTimer = null;
    var CONTROL_HIDE_DELAY = 3000;

    if (!video) {
      return;
    }

    function clearHideTimer() {
      if (controlsHideTimer) {
        window.clearTimeout(controlsHideTimer);
        controlsHideTimer = null;
      }
    }

    function closeSettingsPanel() {
      $settingsPanel.attr('hidden', 'hidden');
      $settingsToggle.attr('aria-expanded', 'false').removeClass('is-active');
    }

    function openSettingsPanel() {
      $settingsPanel.removeAttr('hidden');
      $settingsToggle.attr('aria-expanded', 'true').addClass('is-active');
    }

    function hideControls() {
      $box.removeClass('is-controls-visible');
      closeSettingsPanel();
      clearHideTimer();
    }

    function showControls() {
      $box.addClass('is-controls-visible');
    }

    function scheduleControlsHide() {
      clearHideTimer();
      controlsHideTimer = window.setTimeout(function () {
        hideControls();
      }, CONTROL_HIDE_DELAY);
    }

    function userActivity() {
      showControls();
      scheduleControlsHide();
    }

    function updatePlayState() {
      var isPaused = video.paused || video.ended;
      $playToggle.toggleClass('is-active', !isPaused);
      $playToggle.attr('aria-label', isPaused ? 'Reproduzir' : 'Pausar');
      $playIcon.html(isPaused ? '&#9654;' : '&#10074;&#10074;');
    }

    function updateMuteState() {
      var isMuted = video.muted || video.volume === 0;
      $muteToggle.toggleClass('is-active', isMuted);
      $muteToggle.attr('aria-label', isMuted ? 'Ativar som' : 'Silenciar');
      $muteIcon.html(isMuted ? '&#128263;' : '&#128266;');
    }

    function togglePlay() {
      if (video.paused || video.ended) {
        var playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {});
        }
      } else {
        video.pause();
      }
    }

    function closeEpisodesPanel() {
      $episodesPanel.removeClass('is-open').attr('hidden', 'hidden');
      $episodesToggle.attr('aria-expanded', 'false');
    }

    function openEpisodesPanel() {
      $episodesPanel.removeAttr('hidden').addClass('is-open');
      $episodesToggle.attr('aria-expanded', 'true');
    }

    function populateSubtitleOptions() {
      if (!$subtitleSelect.length) {
        return;
      }

      $subtitleSelect.find('option:not([value="off"])').remove();

      var tracks = [];
      try {
        if (video.textTracks && video.textTracks.length) {
          for (var i = 0; i < video.textTracks.length; i += 1) {
            tracks.push(video.textTracks[i]);
          }
        }
      } catch (error) {
        tracks = [];
      }

      tracks.forEach(function (track, index) {
        var label = String(track.label || track.language || ('Legenda ' + (index + 1)));
        $subtitleSelect.append(
          $('<option></option>').val('track-' + index).text(label)
        );
      });

      var activeValue = 'off';
      tracks.forEach(function (track, index) {
        if (track.mode === 'showing') {
          activeValue = 'track-' + index;
        }
      });

      $subtitleSelect.prop('disabled', tracks.length === 0);
      $subtitleSelect.val(activeValue);
    }

    function applySubtitleSelection(value) {
      if (!video.textTracks || !video.textTracks.length) {
        return;
      }

      for (var i = 0; i < video.textTracks.length; i += 1) {
        video.textTracks[i].mode = 'disabled';
      }

      if (typeof value === 'string' && value.indexOf('track-') === 0) {
        var index = parseInt(value.replace('track-', ''), 10);
        if (!Number.isNaN(index) && video.textTracks[index]) {
          video.textTracks[index].mode = 'showing';
        }
      }
    }

    function setupAudioSelector() {
      if (!$audioSelect.length) {
        return;
      }

      var nativeAudioTracks = video.audioTracks || video.webkitAudioTracks || null;
      if (!nativeAudioTracks || !nativeAudioTracks.length) {
        $audioSelect.prop('disabled', $audioSelect.find('option').length <= 1);
        return;
      }

      $audioSelect.empty();
      var selectedValue = '0';

      for (var i = 0; i < nativeAudioTracks.length; i += 1) {
        var track = nativeAudioTracks[i];
        var trackLang = String(track.language || ('track-' + i));
        var trackLabel = String(track.label || track.language || ('Idioma ' + (i + 1)));
        var $option = $('<option></option>').val(String(i)).text(trackLabel + ' (' + trackLang + ')');
        $audioSelect.append($option);
        if (track.enabled) {
          selectedValue = String(i);
        }
      }

      $audioSelect.prop('disabled', false).val(selectedValue);
      $audioSelect.off('change.nativeAudio').on('change.nativeAudio', function () {
        var nextIndex = parseInt($(this).val(), 10);
        if (Number.isNaN(nextIndex)) {
          return;
        }
        for (var idx = 0; idx < nativeAudioTracks.length; idx += 1) {
          nativeAudioTracks[idx].enabled = idx === nextIndex;
        }
      });
    }

    $episodesToggle.on('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      userActivity();
      if ($episodesPanel.hasClass('is-open')) {
        closeEpisodesPanel();
      } else {
        openEpisodesPanel();
        closeSettingsPanel();
      }
    });

    $settingsToggle.on('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      userActivity();
      if ($settingsPanel.is(':hidden')) {
        openSettingsPanel();
        closeEpisodesPanel();
      } else {
        closeSettingsPanel();
      }
    });

    $subtitleSelect.on('change', function () {
      userActivity();
      applySubtitleSelection(String($(this).val() || 'off'));
    });

    $speedSelect.on('change', function () {
      userActivity();
      var nextRate = parseFloat($(this).val());
      if (!Number.isNaN(nextRate) && nextRate > 0) {
        video.playbackRate = nextRate;
      }
    });

    $audioSelect.on('change', function () {
      userActivity();
    });

    $playToggle.on('click', function (event) {
      event.preventDefault();
      userActivity();
      togglePlay();
    });

    $video.on('click', function () {
      userActivity();
      togglePlay();
    });

    $muteToggle.on('click', function (event) {
      event.preventDefault();
      userActivity();
      video.muted = !video.muted;
      updateMuteState();
    });

    $seekToggle.on('click', function (event) {
      event.preventDefault();
      userActivity();
      var offset = parseInt($(this).data('seek'), 10) || 0;
      if (!Number.isFinite(video.duration)) {
        video.currentTime = Math.max(0, video.currentTime + offset);
        return;
      }
      var target = Math.max(0, Math.min(video.duration, video.currentTime + offset));
      video.currentTime = target;
    });

    $fullscreenToggle.on('click', function (event) {
      event.preventDefault();
      userActivity();
      if (!document.fullscreenElement) {
        if (typeof $box.get(0).requestFullscreen === 'function') {
          $box.get(0).requestFullscreen().catch(function () {});
        }
        return;
      }
      if (typeof document.exitFullscreen === 'function') {
        document.exitFullscreen().catch(function () {});
      }
    });

    $(document).on('click', function (event) {
      if (!$(event.target).closest('.wp-watch-episodes-panel, .js-watch-episodes-toggle').length) {
        closeEpisodesPanel();
      }
      if (!$(event.target).closest('.wp-player-settings-panel, .js-player-settings-toggle').length) {
        closeSettingsPanel();
      }
    });

    $(document).on('keydown', function (event) {
      if (event.key === 'Escape') {
        closeEpisodesPanel();
        closeSettingsPanel();
      }
    });

    $box.on('mouseenter mousemove', function () {
      userActivity();
    });

    $box.on('mouseleave', function () {
      hideControls();
    });

    $box.on('touchstart click keydown', function () {
      userActivity();
    });

    $video.on('play pause ended', updatePlayState);
    $video.on('volumechange loadedmetadata', updateMuteState);
    $video.on('loadedmetadata', populateSubtitleOptions);
    $video.on('ratechange', function () {
      if (!$speedSelect.length) {
        return;
      }
      var currentRate = String(video.playbackRate || 1);
      if ($speedSelect.find('option[value="' + currentRate + '"]').length) {
        $speedSelect.val(currentRate);
      }
    });

    document.addEventListener('fullscreenchange', function () {
      $fullscreenToggle.toggleClass('is-active', !!document.fullscreenElement);
    });

    setupAudioSelector();
    populateSubtitleOptions();
    updatePlayState();
    updateMuteState();
    hideControls();
  }

  function bindLoopVideos(selector, forceMuted) {
    var $videos = $(selector);
    if (!$videos.length) {
      return;
    }

    $videos.each(function () {
      var video = this;
      var loopSeconds = parseInt($(video).data('loopSeconds'), 10) || 45;
      loopSeconds = Math.max(5, loopSeconds);

      if (forceMuted) {
        video.muted = true;
        video.defaultMuted = true;
      }
      video.playsInline = true;

      var tryPlay = function () {
        var playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {
            // Autoplay may be blocked until user interaction.
          });
        }
      };

      video.addEventListener('loadedmetadata', function () {
        if (video.currentTime >= loopSeconds) {
          video.currentTime = 0;
        }
        tryPlay();
      });

      video.addEventListener('timeupdate', function () {
        if (video.currentTime >= loopSeconds) {
          video.currentTime = 0;
          tryPlay();
        }
      });

      video.addEventListener('ended', function () {
        video.currentTime = 0;
        tryPlay();
      });

      tryPlay();
    });
  }

  function bindSeasonTrailers() {
    bindLoopVideos('.js-season-trailer', true);
  }

  function bindHeroPreviews() {
    bindLoopVideos('.js-hero-preview, .js-title-hero-preview', true);

    $('.js-hero-preview, .js-title-hero-preview').each(function () {
      var video = this;
      var $video = $(video);
      var $hero = $video.closest('.wp-hero--cinema, .wp-title-hero');
      if (!$hero.length) {
        return;
      }

      var engageTimer = null;

      function clearEngageTimer() {
        if (engageTimer) {
          window.clearTimeout(engageTimer);
          engageTimer = null;
        }
      }

      function armEngageState() {
        clearEngageTimer();
        engageTimer = window.setTimeout(function () {
          engageTimer = null;
          if (!video.paused && !video.ended && video.readyState >= 2) {
            $hero.addClass('is-trailer-engaged');
          }
        }, 2000);
      }

      function releaseEngageStateBeforeStart() {
        if (video.currentTime < 1.5) {
          $hero.removeClass('is-trailer-engaged');
        }
      }

      video.addEventListener('play', armEngageState);
      video.addEventListener('playing', armEngageState);

      video.addEventListener('pause', function () {
        clearEngageTimer();
        releaseEngageStateBeforeStart();
      });

      video.addEventListener('ended', function () {
        clearEngageTimer();
        releaseEngageStateBeforeStart();
      });

      video.addEventListener('emptied', function () {
        clearEngageTimer();
        $hero.removeClass('is-trailer-engaged');
      });
    });

    $(document).on('click', '.js-hero-sound-toggle', function (event) {
      event.preventDefault();
      var target = String($(this).data('target') || '');
      if (target === '') {
        return;
      }

      var video = $(target).first().get(0);
      if (!video) {
        return;
      }

      video.muted = !video.muted;
      video.defaultMuted = video.muted;
      $(this).toggleClass('is-on', !video.muted);
    });
  }

  function bindBrowseHoverPreview() {
    var CARD_SELECTOR = '.wp-title-card--cinema, .wp-continue-card--cinema';
    var $cards = $(CARD_SELECTOR);
    if (!$cards.length) {
      return;
    }

    var OPEN_DELAY_MS = 220;
    var OPEN_DELAY_WHEN_ACTIVE_MS = 140;
    var CLOSE_DELAY_MS = 150;
    var EDGE_PADDING_PX = 10;
    var HEADER_SAFE_TOP = 74;
    var FLYOUT_SELECTOR = '.wp-hover-flyout';

    var openTimer = null;
    var closeTimer = null;
    var activeCard = null;
    var activeFlyout = null;
    var flyoutHovered = false;

    var $root = $('#wp-hover-flyout-root');
    if (!$root.length) {
      $root = $('<div id="wp-hover-flyout-root" class="wp-hover-flyout-root" hidden></div>');
      $('body').append($root);
    }

    function getCardScale($card) {
      var isContinue = $card.hasClass('wp-continue-card--cinema');
      var compact = window.matchMedia && window.matchMedia('(max-width: 1100px)').matches;
      if (compact) {
        return isContinue ? 1.14 : 1.18;
      }
      return isContinue ? 1.28 : 1.3;
    }

    function stopFlyoutVideo($flyout) {
      if (!$flyout || !$flyout.length) {
        return;
      }
      var video = $flyout.find('.js-card-hover-video').get(0);
      if (!video) {
        return;
      }
      try {
        video.pause();
        video.currentTime = 0;
      } catch (error) {
        // noop
      }
    }

    function clearTimers() {
      if (openTimer) {
        window.clearTimeout(openTimer);
        openTimer = null;
      }
      if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
    }

    function closeFlyout(immediate) {
      clearTimers();
      var $flyout = activeFlyout;
      var $card = activeCard;
      activeFlyout = null;
      activeCard = null;
      flyoutHovered = false;
      if ($card && $card.length) {
        $card.removeClass('is-hover-source');
      }
      if (!$flyout || !$flyout.length) {
        $root.attr('hidden', 'hidden').empty();
        return;
      }

      stopFlyoutVideo($flyout);
      var removeNode = function () {
        $flyout.remove();
        if (!$root.children().length) {
          $root.attr('hidden', 'hidden').empty();
        }
      };

      if (immediate) {
        removeNode();
      } else {
        $flyout.removeClass('is-active');
        window.setTimeout(removeNode, 180);
      }
    }

    function scheduleClose() {
      if (closeTimer) {
        window.clearTimeout(closeTimer);
      }
      closeTimer = window.setTimeout(function () {
        if (flyoutHovered) {
          return;
        }
        closeFlyout(false);
      }, CLOSE_DELAY_MS);
    }

    function wireFlyoutVideo($flyout) {
      var video = $flyout.find('.js-card-hover-video').get(0);
      if (!video) {
        return;
      }

      var src = String($(video).attr('data-src') || '');
      var mime = String($(video).attr('data-mime') || '');
      if (src && !video.getAttribute('src')) {
        video.src = src;
        if (mime) {
          video.setAttribute('type', mime);
        }
      }

      video.muted = true;
      video.defaultMuted = true;
      video.playsInline = true;
      video.loop = false;

      var markReady = function () {
        $flyout.addClass('is-preview-ready');
      };
      video.addEventListener('loadeddata', markReady);
      video.addEventListener('canplay', markReady);
      video.addEventListener('playing', markReady);
      video.addEventListener('timeupdate', function () {
        if (video.currentTime >= 45) {
          video.currentTime = 0;
          var p = video.play();
          if (p && typeof p.catch === 'function') {
            p.catch(function () {});
          }
        }
      });
      video.addEventListener('ended', function () {
        video.currentTime = 0;
        var p = video.play();
        if (p && typeof p.catch === 'function') {
          p.catch(function () {});
        }
      });

      var playPromise = video.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          // Autoplay may be blocked.
        });
      }
    }

    function buildFlyoutForCard($card) {
      var cardEl = $card.get(0);
      if (!cardEl || typeof cardEl.getBoundingClientRect !== 'function') {
        return null;
      }
      var $surface = $card.find('.wp-title-card-surface').first().clone(true, true);
      if (!$surface.length) {
        return null;
      }

      var rect = cardEl.getBoundingClientRect();
      var scale = getCardScale($card);
      var expandedWidth = Math.round(rect.width * scale);

      var $tempMeasure = $('<div class="wp-hover-flyout wp-hover-flyout--measure"></div>').append($surface.clone(true, true));
      $tempMeasure.css({
        left: '-9999px',
        top: '-9999px',
        width: expandedWidth + 'px'
      });
      $root.removeAttr('hidden').append($tempMeasure);
      var panelHeight = Math.ceil($tempMeasure.find('.wp-title-hover-panel').outerHeight() || 0);
      var mediaHeight = Math.ceil($tempMeasure.find('.wp-title-cover').outerHeight() || Math.round(rect.height * scale));
      $tempMeasure.remove();

      var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
      var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;

      var left = Math.round(rect.left - ((expandedWidth - rect.width) / 2));
      if (left < EDGE_PADDING_PX) {
        left = EDGE_PADDING_PX;
      }
      if (left + expandedWidth > viewportWidth - EDGE_PADDING_PX) {
        left = Math.max(EDGE_PADDING_PX, viewportWidth - EDGE_PADDING_PX - expandedWidth);
      }

      var fullHeight = mediaHeight + panelHeight;
      var top = Math.round(rect.top - 14);
      if (top + fullHeight > viewportHeight - EDGE_PADDING_PX) {
        top = Math.max(HEADER_SAFE_TOP, viewportHeight - EDGE_PADDING_PX - fullHeight);
      }
      if (top < HEADER_SAFE_TOP) {
        top = HEADER_SAFE_TOP;
      }

      var $flyout = $('<div class="wp-hover-flyout"></div>');
      $flyout.css({
        left: left + 'px',
        top: top + 'px',
        width: expandedWidth + 'px'
      });
      $flyout.append($surface);

      return $flyout;
    }

    function openFlyout($card) {
      if (!$card || !$card.length) {
        return;
      }
      if (activeCard && activeCard.get(0) === $card.get(0) && activeFlyout && activeFlyout.length) {
        return;
      }

      closeFlyout(true);
      var $flyout = buildFlyoutForCard($card);
      if (!$flyout || !$flyout.length) {
        return;
      }

      activeCard = $card;
      activeFlyout = $flyout;
      $card.addClass('is-hover-source');

      $root.removeAttr('hidden').append($flyout);
      window.requestAnimationFrame(function () {
        if (activeFlyout && activeFlyout.get(0) === $flyout.get(0)) {
          $flyout.addClass('is-active');
        }
      });

      wireFlyoutVideo($flyout);
    }

    $(document).on('mouseenter focusin', CARD_SELECTOR, function () {
      var $current = $(this);
      if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
      if (openTimer) {
        window.clearTimeout(openTimer);
      }
      var delay = activeFlyout ? OPEN_DELAY_WHEN_ACTIVE_MS : OPEN_DELAY_MS;
      openTimer = window.setTimeout(function () {
        openFlyout($current);
        openTimer = null;
      }, delay);
    });

    $(document).on('mouseleave focusout', CARD_SELECTOR, function () {
      if (openTimer) {
        window.clearTimeout(openTimer);
        openTimer = null;
      }
      scheduleClose();
    });

    $root.on('mouseenter', FLYOUT_SELECTOR, function () {
      flyoutHovered = true;
      if (closeTimer) {
        window.clearTimeout(closeTimer);
        closeTimer = null;
      }
    });

    $root.on('mouseleave', FLYOUT_SELECTOR, function () {
      flyoutHovered = false;
      scheduleClose();
    });

    $root.on('click', '.js-card-sound-toggle', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var $flyout = $(this).closest(FLYOUT_SELECTOR);
      var video = $flyout.find('.js-card-hover-video').get(0);
      if (!video) {
        return;
      }
      video.muted = !video.muted;
      video.defaultMuted = video.muted;
      $(this).toggleClass('is-on', !video.muted);
    });

    $(window).on('scroll resize', function () {
      if (activeFlyout) {
        closeFlyout(true);
      }
    });
  }

  function bindBrowseTitleModal() {
    var $modal = $('#wp-title-info-modal');
    var $store = $('#wp-browse-meta-store');
    if (!$modal.length || !$store.length) {
      return;
    }

    var items = {};
    var raw = String($store.attr('data-items') || '');
    if (raw) {
      try {
        items = JSON.parse(raw);
      } catch (error) {
        items = {};
      }
    }

    var $media = $('#wp-title-modal-hero-media');
    var $heading = $('#wp-title-modal-heading');
    var $description = $('#wp-title-modal-description');
    var $watch = $('#wp-title-modal-watch');
    var $details = $('#wp-title-modal-details');
    var $badges = $('#wp-title-modal-badges');
    var $metaLine = $('#wp-title-modal-meta-line');
    var $hook = $('#wp-title-modal-hook');
    var $summarySide = $('#wp-title-modal-summary-side');
    var $seasonPick = $('#wp-title-modal-season-pick');
    var $episodesHeading = $('#wp-title-modal-episodes-heading');
    var $episodes = $('#wp-title-modal-episodes');
    var $episodesToggleWrap = $('#wp-title-modal-episodes-toggle-wrap');
    var $episodesToggle = $('#wp-title-modal-episodes-toggle');

    var EPISODE_COLLAPSE_LIMIT = 10;

    function closeModal() {
      var modalVideo = $modal.find('.js-title-modal-video').get(0);
      if (modalVideo) {
        try {
          modalVideo.pause();
          modalVideo.currentTime = 0;
        } catch (error) {
          // noop
        }
      }
      $modal.attr('hidden', 'hidden');
      $('body').removeClass('wp-modal-open');
    }

    function buildBadge(value) {
      return '<span>' + $('<div>').text(value).html() + '</span>';
    }

    function renderSummary(meta) {
      var summaryBits = [
        String(meta.year || ''),
        String(meta.type_label || meta.type || ''),
        String(meta.maturity_rating || ''),
        String((meta.hover_badges && meta.hover_badges[1]) || '')
      ].filter(function (value, index, array) {
        return value && array.indexOf(value) === index;
      });

      $metaLine.html(summaryBits.map(function (value) {
        return '<span>' + $('<div>').text(value).html() + '</span>';
      }).join(''));

      var tags = Array.isArray(meta.tags) && meta.tags.length ? meta.tags.join(' • ') : String(meta.genre || '');
      $hook.text(tags || 'A new highlight for your next session');

      var facts = Array.isArray(meta.facts) ? meta.facts : [];
      if (!facts.length) {
        $summarySide.empty();
        return;
      }

      var factHtml = facts.map(function (fact) {
        return (
          '<p><strong>' + $('<div>').text(String(fact.label || '')).html() + ':</strong> ' +
          '<span>' + $('<div>').text(String(fact.value || '')).html() + '</span></p>'
        );
      }).join('');

      $summarySide.html(factHtml);
    }

    function renderEpisodes(meta) {
      var seasons = Array.isArray(meta.seasons) ? meta.seasons : [];
      var type = String(meta.type || '').toUpperCase();
      var isCourse = type === 'COURSE';
      var isSeriesLike = type === 'SERIES' || isCourse;

      function normalizeItemLabel(label) {
        var value = String(label || '').trim();
        if (!value) {
          return isCourse ? 'Licao sem titulo' : 'Episodio sem titulo';
        }

        return value
          .replace(/^S\d+\s*E\d+\s*-\s*/i, '')
          .replace(/^M[oó]dulo\s*\d+\s*\/\s*L[ií]?[cç][aã]o\s*\d+\s*-\s*/i, '')
          .replace(/^Episode\s*\d+\s*-\s*/i, '')
          .replace(/^Epis[oó]dio\s*\d+\s*-\s*/i, '')
          .trim() || value;
      }

      function buildEpisodeSummary(item, index) {
        var explicit = String(item.summary || '').trim();
        if (explicit) {
          return explicit;
        }

        var base = normalizeItemLabel(item.label || '');
        if (isCourse) {
          return 'Aula ' + String(index + 1) + ': ' + base + '. Continue de onde voce parou.';
        }
        return 'Episodio ' + String(index + 1) + ': ' + base + '. Continue assistindo ou abra o titulo completo.';
      }

      if (!seasons.length) {
        $episodesHeading.text('Detalhes');
        $seasonPick.text('');
        $episodes.html('<div class="wp-title-modal-empty">Nenhum episodio ou licao disponivel para este titulo.</div>');
        $episodesToggleWrap.attr('hidden', 'hidden');
        $episodes.removeClass('is-collapsed');
        return;
      }

      var season = seasons[0] || {};
      var episodeItems = Array.isArray(season.items) ? season.items : [];
      if (type === 'MOVIE') {
        $episodesHeading.text('Conteudo disponivel');
        $seasonPick.text('');
      } else {
        $episodesHeading.text(isCourse ? 'Licoes' : 'Episodios');
        $seasonPick.text(String(season.label || (isCourse ? 'Modulo 1' : 'Temporada 1')));
      }

      var episodeHtml = episodeItems.map(function (item, index) {
        var thumb = String(item.thumbnail || '/assets/img/placeholders/image-placeholder.svg');
        var duration = String(item.duration_label || '');
        var itemLabel = normalizeItemLabel(item.label || '');
        var itemNumber = parseInt(item.number, 10) || (index + 1);
        var itemSummary = buildEpisodeSummary(item, index);
        var isFirst = index === 0;

        return (
          '<a class="wp-title-modal-episode' + (isFirst ? ' is-featured' : '') + '" href="' + $('<div>').text(String(item.watch_url || '/browse')).html() + '">' +
            '<div class="wp-title-modal-episode-index">' + $('<div>').text(String(itemNumber)).html() + '</div>' +
            '<div class="wp-title-modal-episode-thumb">' +
              '<img src="' + $('<div>').text(thumb).html() + '" alt="' + $('<div>').text(String(itemLabel || 'Episodio')).html() + '">' +
            '</div>' +
            '<div class="wp-title-modal-episode-copy' + (isFirst ? ' is-active' : '') + '">' +
              '<div class="wp-title-modal-episode-head">' +
                '<strong>' + $('<div>').text(itemLabel).html() + '</strong>' +
                (duration ? '<span>' + $('<div>').text(duration).html() + '</span>' : '') +
              '</div>' +
              '<p>' + $('<div>').text(itemSummary).html() + '</p>' +
            '</div>' +
          '</a>'
        );
      }).join('');

      $episodes.html(episodeHtml || '<div class="wp-title-modal-empty">Nenhum item reproduzivel disponivel.</div>');

      if (!isSeriesLike || episodeItems.length <= EPISODE_COLLAPSE_LIMIT) {
        $episodes.removeClass('is-collapsed');
        $episodesToggleWrap.attr('hidden', 'hidden');
        $episodesToggle.attr('aria-expanded', 'false');
        $episodesToggle.attr('aria-label', 'Mostrar mais episodios');
        $episodesToggle.attr('data-show-label', 'Mostrar mais episodios');
        $episodesToggle.attr('data-hide-label', 'Mostrar menos');
        return;
      }

      var showLabel = isCourse ? 'Mostrar mais licoes' : 'Mostrar mais episodios';
      $episodes.addClass('is-collapsed');
      $episodesToggleWrap.removeAttr('hidden');
      $episodesToggle.attr('aria-expanded', 'false');
      $episodesToggle.attr('aria-label', showLabel);
      $episodesToggle.attr('data-show-label', showLabel);
      $episodesToggle.attr('data-hide-label', 'Mostrar menos');
    }

    function renderMedia(meta, previewOverride) {
      var override = previewOverride || {};
      var previewUrl = String(meta.preview_url || '');
      if (previewUrl === '') {
        previewUrl = String(override.url || '');
      }
      var previewMime = String(meta.preview_mime || '');
      if (previewMime === '') {
        previewMime = String(override.mime_type || '');
      }
      var cover = String(meta.backdrop_image || meta.cover_image || '/assets/img/placeholders/bakground.jpg');
      var safeTitle = String(meta.title_name || 'Titulo');

      function startModalLoop(videoElement) {
        if (!videoElement) {
          return;
        }

        var loopSeconds = 45;
        videoElement.muted = true;
        videoElement.defaultMuted = true;
        videoElement.playsInline = true;

        var safePlay = function () {
          var playPromise = videoElement.play();
          if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {
              // noop
            });
          }
        };

        videoElement.addEventListener('loadedmetadata', function () {
          if (videoElement.currentTime >= loopSeconds) {
            videoElement.currentTime = 0;
          }
          safePlay();
        });

        videoElement.addEventListener('canplay', function () {
          safePlay();
        });

        videoElement.addEventListener('timeupdate', function () {
          if (videoElement.currentTime >= loopSeconds) {
            videoElement.currentTime = 0;
            safePlay();
          }
        });

        videoElement.addEventListener('ended', function () {
          videoElement.currentTime = 0;
          safePlay();
        });

        try {
          videoElement.load();
        } catch (error) {
          // noop
        }

        window.setTimeout(function () {
          safePlay();
        }, 120);

        safePlay();
      }

      if (previewUrl) {
        var sourceTag = '<source src="' + $('<div>').text(previewUrl).html() + '"' + (previewMime ? ' type="' + $('<div>').text(previewMime).html() + '"' : '') + '>';
        $media.html(
          '<video class="wp-title-modal-video js-title-modal-video" autoplay muted playsinline preload="metadata" poster="' + $('<div>').text(cover).html() + '">' +
            sourceTag +
          '</video>'
        );
        startModalLoop($media.find('.js-title-modal-video').get(0));
        return;
      }

      $media.html('<img src="' + $('<div>').text(cover).html() + '" alt="' + $('<div>').text(safeTitle).html() + '">');
    }

    function openModalForTitle(titleId, previewOverride) {
      var normalized = String(parseInt(titleId, 10) || 0);
      if (normalized === '0' || !items[normalized]) {
        return;
      }

      var meta = items[normalized];
      $modal.removeAttr('hidden');
      $('body').addClass('wp-modal-open');
      renderMedia(meta, previewOverride);
      $heading.text(String(meta.title_name || 'Titulo'));
      $description.text(String(meta.description || 'Sem descricao disponivel.'));
      $details.attr('href', '/browse');

      var watchMediaId = parseInt(meta.watch_media_id, 10) || 0;
      $watch.attr('href', watchMediaId > 0 ? '/watch/' + watchMediaId : '/browse');
      $watch.toggleClass('disabled', watchMediaId <= 0);
      $watch.attr('aria-disabled', watchMediaId <= 0 ? 'true' : 'false');

      var badgeHtml = [
        buildBadge(String(meta.type_label || meta.type || 'TITLE')),
        buildBadge(String(meta.genre || 'General')),
        buildBadge(String(meta.year || '-')),
        buildBadge(String(meta.maturity_rating || '16+'))
      ].join('');
      $badges.html(badgeHtml);
      renderSummary(meta);
      renderEpisodes(meta);
    }

    $(document).on('click', '.js-open-title-modal', function (event) {
      event.preventDefault();
      var titleId = parseInt($(this).data('titleId'), 10) || 0;
      if (titleId <= 0) {
        return;
      }
      var previewUrl = String($(this).attr('data-preview-url') || '');
      var previewMime = String($(this).attr('data-preview-mime') || '');
      var normalizedTitleId = String(parseInt(titleId, 10) || 0);
      if (previewUrl === '') {
        var $heroSource = $('.js-hero-preview source').first();
        var $heroVideo = $('.js-hero-preview').first();
        if ($heroSource.length) {
          previewUrl = String($heroSource.attr('src') || '');
          if (previewMime === '') {
            previewMime = String($heroSource.attr('type') || '');
          }
        } else if ($heroVideo.length) {
          previewUrl = String($heroVideo.attr('src') || '');
        }
      }

      var existingMeta = items[normalizedTitleId] || {};
      var existingPreviewUrl = String(existingMeta.preview_url || '');
      var existingPreviewMime = String(existingMeta.preview_mime || '');
      if (previewUrl === '' && existingPreviewUrl !== '') {
        previewUrl = existingPreviewUrl;
      }
      if (previewMime === '' && existingPreviewMime !== '') {
        previewMime = existingPreviewMime;
      }

      if (previewUrl !== '') {
        openModalForTitle(titleId, {
          url: previewUrl,
          mime_type: previewMime
        });
        return;
      }

      $.ajax({
        url: '/title/' + normalizedTitleId + '/preview',
        method: 'GET',
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).done(function (response) {
        if (response && response.ok) {
          previewUrl = String(response.url || '');
          previewMime = String(response.mime_type || '');
          if (items[normalizedTitleId]) {
            items[normalizedTitleId].preview_url = previewUrl;
            items[normalizedTitleId].preview_mime = previewMime;
          }
        }
      }).always(function () {
        openModalForTitle(titleId, {
          url: previewUrl,
          mime_type: previewMime
        });
      });
    });

    $(document).on('click', '.js-close-title-modal', function (event) {
      event.preventDefault();
      closeModal();
    });

    $episodesToggle.on('click', function () {
      var isCollapsed = $episodes.hasClass('is-collapsed');
      $episodes.toggleClass('is-collapsed', !isCollapsed);
      $episodesToggle.attr('aria-expanded', isCollapsed ? 'true' : 'false');
      var showLabel = String($episodesToggle.attr('data-show-label') || 'Mostrar mais episodios');
      var hideLabel = String($episodesToggle.attr('data-hide-label') || 'Mostrar menos');
      $episodesToggle.attr('aria-label', isCollapsed ? hideLabel : showLabel);
    });

    $(document).on('click', '#wp-title-info-modal', function (event) {
      if ($(event.target).is('#wp-title-info-modal')) {
        closeModal();
      }
    });

    $(document).on('keydown', function (event) {
      if (event.key === 'Escape' && !$modal.is('[hidden]')) {
        closeModal();
      }
    });
  }

  function bindPublicSoonActions() {
    $(document).on('click', '.js-public-soon', function (event) {
      var label = String($(this).data('label') || $(this).text() || 'This action');
      event.preventDefault();
      fireAlert({
        icon: 'info',
        title: label,
        text: 'This interaction is already designed in WisePlay, but the personalized public flow is still being finalized.'
      });
    });
  }

  function bindProfileManagement() {
    if (!$('.wp-profiles-page').length) {
      return;
    }

    var $page = $('.wp-profiles-page').first();
    var avatarFallback = String($page.data('avatarFallback') || '/assets/img/placeholders/image-placeholder.svg');
    var $modal = $('#public-profile-editor-modal');
    var $editorForm = $('#public-profile-editor-form');
    var $profileId = $('#public-profile-id');
    var $profileName = $('#public-profile-name');
    var $profileKids = $('#public-profile-is-kids');
    var $profileAvatar = $('#public-profile-avatar');
    var $profilePreview = $('#public-profile-editor-preview');
    var $profileDeleteButton = $('#public-profile-delete-button');
    var $profileSubmitButton = $('#public-profile-submit-button');
    var $profileTitle = $('#public-profile-editor-title');
    var $profileCopy = $('#public-profile-editor-copy');

    function activateAvatarOption(inputSelector, previewSelector, value) {
      var $input = $(inputSelector);
      if (!$input.length) {
        return;
      }

      $input.val(value);
      $(previewSelector).attr('src', value);

      $('.js-avatar-option[data-target="' + inputSelector + '"]').removeClass('active');
      $('.js-avatar-option[data-target="' + inputSelector + '"][data-value="' + value + '"]').addClass('active');
    }

    function openEditor(mode, profile) {
      if (!$modal.length || !$editorForm.length) {
        return;
      }

      var isEdit = mode === 'edit';
      var safeProfile = profile || {};
      var avatarValue = String(safeProfile.avatar_path || avatarFallback);

      $editorForm.attr('action', isEdit ? '/profiles/update' : '/profiles/create');
      $profileId.val(isEdit ? String(safeProfile.id || '') : '');
      $profileName.val(isEdit ? String(safeProfile.profile_name || '') : '');
      $profileKids.val(isEdit ? String(safeProfile.is_kids || 0) : '0');
      $profileAvatar.val(avatarValue);
      $profilePreview.attr('src', avatarValue);
      $profileTitle.text(isEdit ? 'Editar perfil' : 'Novo perfil');
      $profileCopy.text(isEdit
        ? 'Atualize o nome, avatar e o tipo do perfil.'
        : 'Crie um perfil adulto ou infantil para a conta.');
      $profileSubmitButton.text(isEdit ? 'Salvar perfil' : 'Criar perfil');
      $profileDeleteButton.prop('hidden', !isEdit).data('profileId', isEdit ? parseInt(safeProfile.id, 10) || 0 : 0).data('profileName', String(safeProfile.profile_name || 'perfil'));

      activateAvatarOption('#public-profile-avatar', '#public-profile-editor-preview', avatarValue);
      $modal.removeAttr('hidden');
      $('body').addClass('wp-modal-open');
      window.setTimeout(function () {
        $profileName.trigger('focus');
      }, 40);
    }

    function closeEditor() {
      if (!$modal.length) {
        return;
      }
      $modal.attr('hidden', 'hidden');
      $('body').removeClass('wp-modal-open');
    }

    $(document).on('click', '.js-avatar-option', function () {
      var target = String($(this).data('target') || '');
      var preview = String($(this).data('preview') || '');
      var value = String($(this).data('value') || '');
      if (!target || !preview || !value) {
        return;
      }
      activateAvatarOption(target, preview, value);
    });

    $(document).on('click', '.js-open-profile-editor', function () {
      var raw = $(this).attr('data-profile');
      if (!raw) {
        return;
      }

      var profile;
      try {
        profile = JSON.parse(raw);
      } catch (error) {
        return;
      }

      openEditor('edit', profile);
    });

    $(document).on('click', '.js-open-profile-create, .js-public-create-profile', function (event) {
      var reached = String($(this).data('limitReached') || '0') === '1';
      if (!reached) {
        if ($(this).hasClass('js-open-profile-create')) {
          event.preventDefault();
          openEditor('create', null);
        }
        return;
      }

      event.preventDefault();
      var limit = parseInt($(this).data('limitValue'), 10) || 0;

      fireAlert({
        icon: 'warning',
        title: 'Limite de perfis atingido',
        text: 'Seu plano permite ate ' + limit + ' perfil(is).'
      });
    });

    $(document).on('click', '.js-close-profile-editor', function () {
      closeEditor();
    });

    $(document).on('click', '.wp-profile-editor-backdrop', function () {
      closeEditor();
    });

    $(document).on('click', '#public-profile-delete-button', function () {
      var profileId = parseInt($(this).data('profileId'), 10) || 0;
      var profileName = String($(this).data('profileName') || 'perfil');
      if (profileId <= 0) {
        return;
      }

      var $form = $('#public-delete-profile-' + profileId);
      if (!$form.length) {
        return;
      }

      fireAlert({
        icon: 'warning',
        title: 'Excluir perfil',
        text: 'Deseja excluir o perfil "' + profileName + '"?',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }
        $form.trigger('submit');
      });
    });

    $(document).on('click', '.js-delete-public-profile', function (event) {
      event.preventDefault();
      var $button = $(this);
      var name = String($button.data('name') || 'perfil');
      var $form = $button.closest('form');
      if (!$form.length) {
        return;
      }

      fireAlert({
        icon: 'warning',
        title: 'Excluir perfil',
        text: 'Deseja excluir o perfil "' + name + '"?',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }
        $form.trigger('submit');
      });
    });

    $(document).on('keydown', function (event) {
      if (event.key === 'Escape') {
        closeEditor();
      }
    });

    if (window.location.hash === '#new-profile' || String($('.wp-profiles-page').data('profileAutoOpenCreate') || '0') === '1') {
      openEditor('create', null);
    }
  }

  function bindRegisterVerificationCountdown() {
    var $button = $('#register-resend-button');
    if (!$button.length) {
      return;
    }

    $('#register-code').on('input', function () {
      var digits = String($(this).val() || '').replace(/\D+/g, '').slice(0, 6);
      $(this).val(digits);
    });

    var remaining = parseInt($button.data('resendCountdown'), 10) || 0;
    if (remaining <= 0) {
      return;
    }

    var baseText = 'Reenviar codigo';
    $button.prop('disabled', true);
    $button.text(baseText + ' (' + remaining + 's)');

    var interval = window.setInterval(function () {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(interval);
        $button.prop('disabled', false);
        $button.text(baseText);
        return;
      }
      $button.text(baseText + ' (' + remaining + 's)');
    }, 1000);
  }

  function bindOnboardingPaymentForm() {
    var $form = $('form[action=\"/onboarding/payment/submit\"]');
    if (!$form.length) {
      return;
    }

    var $cardNumber = $form.find('input[name=\"card_number\"]');
    var $cvv = $form.find('input[name=\"cvv\"]');
    var $expMonth = $form.find('input[name=\"exp_month\"]');
    var $expYear = $form.find('input[name=\"exp_year\"]');

    $cardNumber.on('input', function () {
      var digits = String($(this).val() || '').replace(/\\D+/g, '').slice(0, 19);
      var pretty = digits.replace(/(.{4})/g, '$1 ').trim();
      $(this).val(pretty);
    });

    $cvv.on('input', function () {
      var digits = String($(this).val() || '').replace(/\\D+/g, '').slice(0, 4);
      $(this).val(digits);
    });

    $expMonth.on('input', function () {
      var digits = String($(this).val() || '').replace(/\\D+/g, '').slice(0, 2);
      $(this).val(digits);
    });

    $expYear.on('input', function () {
      var digits = String($(this).val() || '').replace(/\\D+/g, '').slice(0, 4);
      $(this).val(digits);
    });
  }

  function bindPublicChrome() {
    var $header = $('.js-stream-header');
    if (!$header.length) {
      return;
    }

    var $user = $('.js-stream-user');
    var $trigger = $('.js-stream-user-trigger');
    var $menu = $('.wp-stream-user-menu');
    var $notificationRoot = $('.js-stream-notifications');
    var $notificationTrigger = $('.js-stream-notification-trigger');
    var $notificationMenu = $('.wp-stream-notification-menu');
    var $hero = $('.wp-hero--cinema, .wp-title-hero').first();
    var solidThreshold = 36;

    function updateSolidThreshold() {
      if (!$hero.length) {
        solidThreshold = 36;
        return;
      }

      var heroHeight = Math.round($hero.outerHeight() || 0);
      if (heroHeight <= 0) {
        solidThreshold = 36;
        return;
      }

      solidThreshold = Math.max(42, Math.min(140, Math.round(heroHeight * 0.14)));
    }

    function syncHeaderState() {
      var menuOpen = $menu.is(':visible');
      var notificationOpen = $notificationMenu.is(':visible');
      $header.toggleClass('is-solid', $(window).scrollTop() > solidThreshold || menuOpen || notificationOpen);
    }

    function closeMenu() {
      $user.removeClass('is-open');
      $trigger.attr('aria-expanded', 'false');
      $menu.attr('hidden', 'hidden');
      syncHeaderState();
    }

    function openMenu() {
      closeNotifications();
      $user.addClass('is-open');
      $trigger.attr('aria-expanded', 'true');
      $menu.removeAttr('hidden');
      syncHeaderState();
    }

    function closeNotifications() {
      $notificationTrigger.attr('aria-expanded', 'false');
      $notificationMenu.attr('hidden', 'hidden');
      $notificationRoot.removeClass('is-open');
      syncHeaderState();
    }

    function openNotifications() {
      closeMenu();
      $notificationTrigger.attr('aria-expanded', 'true');
      $notificationMenu.removeAttr('hidden');
      $notificationRoot.addClass('is-open');
      syncHeaderState();
    }

    $trigger.on('click', function (event) {
      event.preventDefault();
      if ($user.hasClass('is-open')) {
        closeMenu();
        return;
      }
      openMenu();
    });

    $notificationTrigger.on('click', function (event) {
      event.preventDefault();
      if ($notificationRoot.hasClass('is-open')) {
        closeNotifications();
        return;
      }
      openNotifications();
    });

    $(document).on('click', function (event) {
      if (!$(event.target).closest('.js-stream-user').length) {
        closeMenu();
      }
      if (!$(event.target).closest('.js-stream-notifications').length) {
        closeNotifications();
      }
    });

    $(document).on('click', '.js-browse-jump', function (event) {
      var target = String($(this).attr('href') || '');
      if (!target || target.charAt(0) !== '#') {
        return;
      }

      var $target = $(target);
      if (!$target.length) {
        return;
      }

      event.preventDefault();
      $('html, body').animate({
        scrollTop: Math.max($target.offset().top - 88, 0)
      }, 260);
    });

    $(document).on('click', '.js-notification-read-all', function (event) {
      event.preventDefault();
      var $form = $(this).closest('form');
      if (!$form.length) {
        return;
      }

      var csrf = String($form.find('input[name="_csrf"]').val() || (window.WISEPLAY && window.WISEPLAY.csrf) || '');
      if (csrf === '') {
        $form.trigger('submit');
        return;
      }

      $.ajax({
        url: '/notifications/read-all',
        method: 'POST',
        dataType: 'json',
        data: { _csrf: csrf },
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).done(function (response) {
        if (!response || response.ok !== true) {
          return;
        }

        $('.wp-stream-notification-item.is-unread').removeClass('is-unread');
        $('.wp-stream-notifications .wp-stream-badge').remove();
      }).fail(function () {
        $form.trigger('submit');
      });
    });

    $(document).on('click', '.js-public-coming-soon', function (event) {
      var label = String($(this).data('label') || $(this).text() || 'Recurso');
      event.preventDefault();
      fireAlert({
        icon: 'info',
        title: 'Em breve',
        text: label + ' ainda nao possui fluxo publico completo.'
      });
    });

    $(window).on('scroll', syncHeaderState);
    $(window).on('resize', function () {
      updateSolidThreshold();
      syncHeaderState();
    });
    $(document).on('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
        closeNotifications();
      }
    });

    updateSolidThreshold();
    syncHeaderState();
  }

  $(function () {
    showFlash();
    bindProfileManagement();
    bindPublicChrome();
    bindRegisterVerificationCountdown();
    bindOnboardingPaymentForm();
    bindRowNavigation();
    bindWatchProgress();
    bindWatchPlayerOverlay();
    bindSeasonTrailers();
    bindHeroPreviews();
    bindBrowseHoverPreview();
    bindBrowseTitleModal();
    bindPublicSoonActions();
  });
})(jQuery);
