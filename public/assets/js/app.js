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

    function syncHeaderState() {
      $header.toggleClass('is-solid', $(window).scrollTop() > 24 || $menu.is(':visible'));
    }

    function closeMenu() {
      $user.removeClass('is-open');
      $trigger.attr('aria-expanded', 'false');
      $menu.attr('hidden', 'hidden');
      syncHeaderState();
    }

    function openMenu() {
      $user.addClass('is-open');
      $trigger.attr('aria-expanded', 'true');
      $menu.removeAttr('hidden');
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

    $(document).on('click', function (event) {
      if (!$(event.target).closest('.js-stream-user').length) {
        closeMenu();
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

    $(document).on('click', '.js-public-coming-soon', function (event) {
      var label = String($(this).data('label') || $(this).text() || 'Recurso');
      event.preventDefault();
      fireAlert({
        icon: 'info',
        title: 'Em breve',
        text: label + ' ainda nao possui fluxo publico completo.'
      });
    });

    $(window).on('scroll resize', syncHeaderState);
    $(document).on('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
      }
    });

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
  });
})(jQuery);
