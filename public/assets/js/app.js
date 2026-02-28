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
    $(document).on('click', '[data-row-prev], [data-row-next]', function () {
      var rowId = $(this).data('rowPrev') || $(this).data('rowNext');
      if (!rowId) {
        return;
      }

      var $rail = $('#' + rowId);
      if (!$rail.length) {
        return;
      }

      var distance = Math.max(260, Math.floor($rail.outerWidth() * 0.8));
      var direction = $(this).is('[data-row-prev]') ? -1 : 1;
      $rail[0].scrollBy({ left: distance * direction, behavior: 'smooth' });
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

  function bindProfileManagement() {
    if (!$('.wp-profiles-page').length) {
      return;
    }

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

    $(document).on('click', '.js-avatar-option', function () {
      var target = String($(this).data('target') || '');
      var preview = String($(this).data('preview') || '');
      var value = String($(this).data('value') || '');
      if (!target || !preview || !value) {
        return;
      }
      activateAvatarOption(target, preview, value);
    });

    $(document).on('change', '#public-profile-create-form input[type=\"file\"][name=\"avatar_file\"], #public-profile-edit-form input[type=\"file\"][name=\"avatar_file\"]', function () {
      var input = this;
      if (!input.files || !input.files.length) {
        return;
      }

      var file = input.files[0];
      var allowed = ['image/jpeg', 'image/png', 'image/webp'];
      var maxBytes = 4 * 1024 * 1024;
      if (allowed.indexOf(file.type) === -1) {
        fireAlert({ icon: 'error', title: 'Erro', text: 'Imagem deve ser JPG, PNG ou WEBP.' });
        input.value = '';
        return;
      }
      if (file.size <= 0 || file.size > maxBytes) {
        fireAlert({ icon: 'error', title: 'Erro', text: 'Imagem excede limite de 4MB.' });
        input.value = '';
        return;
      }

      var $form = $(input).closest('form');
      var isEdit = $form.attr('id') === 'public-profile-edit-form';
      var hiddenTarget = isEdit ? '#public-edit-avatar' : '#public-create-avatar';
      var previewTarget = isEdit ? '#public-edit-avatar-preview' : '#public-create-avatar-preview';

      var reader = new FileReader();
      reader.onload = function (event) {
        var value = event && event.target && event.target.result ? String(event.target.result) : '';
        if (value === '') {
          return;
        }
        $(hiddenTarget).val(value);
        $(previewTarget).attr('src', value);
      };
      reader.readAsDataURL(file);
    });

    $(document).on('click', '.js-edit-public-profile', function () {
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

      $('#public-edit-profile-id').val(profile.id || '');
      $('#public-edit-profile-name').val(profile.profile_name || '');
      $('#public-edit-is-kids').val(profile.is_kids || 0);

      var avatarValue = profile.avatar_path || '/assets/img/avatar-library/avatar-01.svg';
      activateAvatarOption('#public-edit-avatar', '#public-edit-avatar-preview', avatarValue);

      $('html, body').animate({
        scrollTop: Math.max($('#public-profile-edit-form').offset().top - 24, 0)
      }, 220);
    });

    $(document).on('click', '.js-public-create-profile', function (event) {
      var reached = String($(this).data('limitReached') || '0') === '1';
      if (!reached) {
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

  $(function () {
    showFlash();
    bindProfileManagement();
    bindRegisterVerificationCountdown();
    bindOnboardingPaymentForm();
    bindRowNavigation();
    bindWatchProgress();
  });
})(jQuery);
