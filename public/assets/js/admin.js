(function ($) {
  'use strict';

  var IMAGE_PLACEHOLDER = '/assets/img/placeholders/image-placeholder.svg';
  var openActionsState = null;
  var actionsScrollListenerBound = false;
  var modalIconByRole = {
    create: 'ri-add-circle-line',
    edit: 'ri-pencil-line',
    delete: 'ri-delete-bin-line',
    view: 'ri-information-line'
  };

  function csrfToken() {
    return String((window.WISEPLAY && window.WISEPLAY.csrf) || '');
  }

  function parsePayload(raw) {
    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw);
    } catch (error) {
      return null;
    }
  }

  function inferModalRole(titleText) {
    var value = String(titleText || '').toLowerCase();
    if (value.indexOf('editar') >= 0 || value.indexOf('alterar') >= 0) {
      return 'edit';
    }
    if (value.indexOf('excluir') >= 0 || value.indexOf('remover') >= 0) {
      return 'delete';
    }
    if (value.indexOf('detalhe') >= 0 || value.indexOf('info') >= 0 || value.indexOf('visualizar') >= 0) {
      return 'view';
    }
    return 'create';
  }

  function swalFire(options) {
    return Swal.fire($.extend(true, {
      customClass: {
        popup: 'wp-swal',
        confirmButton: 'btn btn-semantic-primary',
        cancelButton: 'btn btn-semantic-neutral'
      },
      buttonsStyling: false,
      scrollbarPadding: false
    }, options || {}));
  }

  function closeActionsDropdown() {
    if (!openActionsState) {
      return;
    }

    var state = openActionsState;
    openActionsState = null;

    state.$dropdown.removeClass('is-open');
    state.$toggle.attr('aria-expanded', 'false');
    state.$menu.removeClass('show wp-actions-floating').removeAttr('style');

    if (state.$dropdown.length && document.body.contains(state.$dropdown.get(0))) {
      state.$dropdown.append(state.$menu);
    }
  }

  function positionActionsDropdown(state) {
    if (!state || !state.$menu.length || !state.$toggle.length) {
      return;
    }

    var toggleEl = state.$toggle.get(0);
    if (!toggleEl || !document.body.contains(toggleEl)) {
      closeActionsDropdown();
      return;
    }

    var rect = toggleEl.getBoundingClientRect();
    var spacing = 8;
    var viewportPadding = 8;

    state.$menu.css({
      top: '0px',
      left: '0px',
      visibility: 'hidden'
    });

    var menuEl = state.$menu.get(0);
    var menuWidth = Math.ceil(menuEl.offsetWidth || menuEl.scrollWidth || 176);
    var menuHeight = Math.ceil(menuEl.offsetHeight || menuEl.scrollHeight || 44);

    var top = rect.bottom + spacing;
    if (top + menuHeight > window.innerHeight - viewportPadding) {
      top = rect.top - menuHeight - spacing;
    }
    if (top < viewportPadding) {
      top = viewportPadding;
    }

    var left = rect.right - menuWidth;
    if (left < viewportPadding) {
      left = viewportPadding;
    }
    if (left + menuWidth > window.innerWidth - viewportPadding) {
      left = Math.max(viewportPadding, window.innerWidth - menuWidth - viewportPadding);
    }

    state.$menu.css({
      top: Math.round(top) + 'px',
      left: Math.round(left) + 'px',
      visibility: 'visible'
    });
  }

  function bindActionsDropdowns() {
    $(document).on('click', '.wp-actions-dropdown .js-actions-toggle', function (event) {
      event.preventDefault();
      event.stopPropagation();

      var $toggle = $(this);
      var $dropdown = $toggle.closest('.wp-actions-dropdown');
      var $menu = $dropdown.find('.wp-actions-menu').first();
      if (!$dropdown.length || !$menu.length) {
        return;
      }

      if (openActionsState && openActionsState.$dropdown.get(0) === $dropdown.get(0)) {
        closeActionsDropdown();
        return;
      }

      closeActionsDropdown();

      $dropdown.addClass('is-open');
      $toggle.attr('aria-expanded', 'true');
      $menu.addClass('show wp-actions-floating');
      $('body').append($menu);

      openActionsState = {
        $dropdown: $dropdown,
        $toggle: $toggle,
        $menu: $menu
      };

      positionActionsDropdown(openActionsState);
    });

    $(document).on('click', '.wp-actions-menu .dropdown-item', function () {
      closeActionsDropdown();
    });

    $(document).on('click', function (event) {
      if (!openActionsState) {
        return;
      }

      var $target = $(event.target);
      if ($target.closest('.wp-actions-menu').length || $target.closest('.wp-actions-dropdown .js-actions-toggle').length) {
        return;
      }

      closeActionsDropdown();
    });

    $(window).on('resize', function () {
      if (!openActionsState) {
        return;
      }
      positionActionsDropdown(openActionsState);
    });

    if (!actionsScrollListenerBound) {
      document.addEventListener('scroll', function () {
        if (!openActionsState) {
          return;
        }
        positionActionsDropdown(openActionsState);
      }, true);
      actionsScrollListenerBound = true;
    }

    $(document).on('keydown', function (event) {
      if (event.key === 'Escape') {
        closeActionsDropdown();
      }
    });

    $(document).on('show.bs.modal shown.bs.modal hide.bs.modal', function () {
      closeActionsDropdown();
    });
  }

  function clearErrors(errorKey) {
    if (!errorKey) {
      return;
    }
    $('[data-form-errors="' + errorKey + '"]').html('');
  }

  function renderErrors(errorKey, message, errors) {
    if (!errorKey) {
      return;
    }

    var html = '';
    if (message) {
      html += '<p>' + $('<div>').text(message).html() + '</p>';
    }

    if (errors && typeof errors === 'object') {
      html += '<ul>';
      Object.keys(errors).forEach(function (field) {
        html += '<li>' + $('<div>').text(String(errors[field])).html() + '</li>';
      });
      html += '</ul>';
    }

    $('[data-form-errors="' + errorKey + '"]').html(html);
  }

  function openModal(selector) {
    var element = document.querySelector(selector);
    if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(element);
    modal.show();
  }

  function closeFormModal($form) {
    var modalEl = $form.closest('.modal').get(0);
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      return;
    }

    var modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) {
      modal.hide();
    }
  }

  function request(url, method, data, hasFiles) {
    return $.ajax({
      url: url,
      method: method || 'POST',
      dataType: 'json',
      data: data,
      processData: !hasFiles,
      contentType: hasFiles ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
  }

  function sweetError(message) {
    swalFire({
      icon: 'error',
      title: 'Erro',
      text: message || 'Ocorreu um erro inesperado.'
    });
  }

  function formDataWithCsrf($form) {
    var data = new FormData($form.get(0));
    if (!data.get('_csrf')) {
      data.append('_csrf', csrfToken());
    }
    return data;
  }

  function encodedDataWithCsrf($form) {
    var serialized = $form.serializeArray();
    var hasToken = serialized.some(function (entry) {
      return entry.name === '_csrf';
    });

    if (!hasToken) {
      serialized.push({ name: '_csrf', value: csrfToken() });
    }

    return $.param(serialized);
  }

  function submitAjaxForm($form) {
    var endpoint = String($form.data('endpoint') || '');
    if (endpoint === '') {
      return;
    }

    var errorKey = String($form.data('errorsKey') || '');
    var hasFiles = String($form.data('hasFiles') || '0') === '1';
    clearErrors(errorKey);

    var payload = hasFiles ? formDataWithCsrf($form) : encodedDataWithCsrf($form);

    request(endpoint, 'POST', payload, hasFiles)
      .done(function (response) {
        if (!response || response.ok !== true) {
          renderErrors(errorKey, (response && response.message) || 'Falha ao processar requisicao.', response && response.errors);
          sweetError((response && response.message) || 'Falha ao processar requisicao.');
          return;
        }

        closeFormModal($form);
        swalFire({
          icon: 'success',
          title: 'Sucesso',
          text: response.message || 'Operacao concluida.'
        }).then(function () {
          window.location.reload();
        });
      })
      .fail(function (xhr) {
        var json = xhr.responseJSON || {};
        renderErrors(errorKey, json.message || 'Falha na validacao.', json.errors || {});
        sweetError(json.message || 'Falha na requisicao.');
      });
  }

  function confirmAction(title, text, confirmText) {
    return swalFire({
      icon: 'warning',
      title: title,
      text: text,
      showCancelButton: true,
      confirmButtonText: confirmText || 'Confirmar',
      cancelButtonText: 'Cancelar',
      customClass: {
        confirmButton: 'btn btn-semantic-danger',
        cancelButton: 'btn btn-semantic-neutral'
      }
    });
  }

  function deleteByEndpoint(endpoint, id, name, entityLabel) {
    confirmAction('Confirmar exclusao', 'Deseja excluir ' + entityLabel + ' "' + name + '"?', 'Excluir')
      .then(function (result) {
        if (!result.isConfirmed) {
          return;
        }

        request(endpoint, 'POST', $.param({ id: id, _csrf: csrfToken() }), false)
          .done(function (response) {
            if (!response || response.ok !== true) {
              sweetError((response && response.message) || 'Nao foi possivel excluir.');
              return;
            }

            swalFire({
              icon: 'success',
              title: 'Excluido',
              text: response.message || 'Item removido.'
            }).then(function () {
              window.location.reload();
            });
          })
          .fail(function (xhr) {
            var json = xhr.responseJSON || {};
            sweetError(json.message || 'Falha ao excluir.');
          });
      });
  }

  function bulkDeleteByEndpoint(endpoint, ids) {
    var normalizedIds = Array.isArray(ids) ? ids.filter(function (id) {
      return parseInt(id, 10) > 0;
    }) : [];

    if (!normalizedIds.length) {
      sweetError('Selecione ao menos um usuario.');
      return;
    }

    confirmAction(
      'Confirmar exclusao em massa',
      'Deseja excluir ' + normalizedIds.length + ' usuario(s) selecionado(s)?',
      'Excluir selecionados'
    ).then(function (result) {
      if (!result.isConfirmed) {
        return;
      }

      var payload = normalizedIds.map(function (id) {
        return 'ids[]=' + encodeURIComponent(String(id));
      }).join('&') + '&_csrf=' + encodeURIComponent(csrfToken());

      request(endpoint, 'POST', payload, false)
        .done(function (response) {
          if (!response || response.ok !== true) {
            sweetError((response && response.message) || 'Nao foi possivel excluir os usuarios selecionados.');
            return;
          }

          var details = response && Array.isArray(response.skipped) && response.skipped.length
            ? '\n\nIgnorados:\n- ' + response.skipped.join('\n- ')
            : '';

          swalFire({
            icon: 'success',
            title: 'Exclusao concluida',
            text: (response.message || 'Usuarios removidos.') + details
          }).then(function () {
            window.location.reload();
          });
        })
        .fail(function (xhr) {
          var json = xhr.responseJSON || {};
          var detailText = Array.isArray(json.details) && json.details.length
            ? '\n\n' + json.details.join('\n')
            : '';
          sweetError((json.message || 'Falha ao excluir usuarios selecionados.') + detailText);
        });
    });
  }

  function decorateAllModals() {
    $('.modal').each(function () {
      var $modal = $(this);
      var $header = $modal.find('.modal-header').first();
      var $title = $header.find('.modal-title').first();
      if (!$header.length || !$title.length) {
        return;
      }

      var role = String($modal.data('modalRole') || inferModalRole($title.text()));
      if (['create', 'edit', 'delete', 'view'].indexOf(role) === -1) {
        role = 'create';
      }

      $header.addClass('wp-modal-head-' + role);

      if (!$header.find('.wp-modal-title-wrap').length) {
        var iconClass = modalIconByRole[role] || modalIconByRole.create;
        var $wrap = $('<div class="wp-modal-title-wrap"></div>');
        var $icon = $('<span class="wp-modal-title-icon wp-modal-icon-' + role + '"><i class="ri ' + iconClass + '"></i></span>');
        var $text = $('<span></span>').text($title.text());
        $wrap.append($icon, $text);
        $title.empty().append($wrap);
      }
    });
  }

  function normalizeImagePath(value) {
    var path = $.trim(String(value || ''));
    if (path === '') {
      return IMAGE_PLACEHOLDER;
    }
    return path;
  }

  function previewKeyForInput($input) {
    var explicit = String($input.data('previewKey') || '');
    if (explicit !== '') {
      return explicit;
    }

    var name = String($input.attr('name') || '').toLowerCase();
    if (name.indexOf('backdrop') >= 0) {
      return 'backdrop';
    }
    if (name.indexOf('cover') >= 0) {
      return 'cover';
    }
    if (name.indexOf('account_avatar') >= 0) {
      return 'account_avatar';
    }
    if (name.indexOf('avatar') >= 0) {
      return 'avatar';
    }
    return name || 'image';
  }

  function ensurePreviewNode($form, key, inputName) {
    var selector = '[data-preview-key-view="' + key + '"]';
    var $image = $form.find(selector).first();
    if ($image.length) {
      return $image;
    }

    var $input = $form.find('[name="' + inputName + '"]').first();
    if (!$input.length) {
      $input = $form.find('[data-preview-key="' + key + '"]').first();
    }
    if (!$input.length) {
      $input = $form.find('input[name*="' + key + '"]').first();
    }
    var $host = $input.closest('.col-md-12, .col-md-6, .col-md-4, .col-md-3, .mb-3').first();
    if (!$host.length) {
      $host = $input.parent();
    }

    var $wrap = $('<div class="wp-image-preview-wrap"><img src="' + IMAGE_PLACEHOLDER + '" alt="preview"><small>Preview da imagem</small></div>');
    $image = $wrap.find('img');
    $image.attr('data-preview-key-view', key);
    $host.append($wrap);

    return $image;
  }

  function setPreviewValue($form, key, value, inputName) {
    var $image = ensurePreviewNode($form, key, inputName || key);
    var path = normalizeImagePath(value);
    $image.attr('src', path);
    $image.off('error.wpPreview').on('error.wpPreview', function () {
      $(this).attr('src', IMAGE_PLACEHOLDER);
    });
  }

  function bindImagePreviewUX() {
    $(document).on('input change', 'form input[name$="_image"], form input[name="account_avatar"], form input[name="avatar_path"]', function () {
      var $input = $(this);
      var $form = $input.closest('form');
      if (!$form.length) {
        return;
      }
      var key = previewKeyForInput($input);
      setPreviewValue($form, key, $input.val(), String($input.attr('name') || key));
    });

    $(document).on('change', 'form input[type="file"][accept*="image"]', function () {
      var input = this;
      var $input = $(input);
      var $form = $input.closest('form');
      if (!$form.length) {
        return;
      }

      var key = previewKeyForInput($input);
      ensurePreviewNode($form, key, String($input.attr('name') || key));

      if (!input.files || !input.files.length) {
        return;
      }

      var file = input.files[0];
      var valid = ['image/jpeg', 'image/png', 'image/webp'];
      var maxBytes = 4 * 1024 * 1024;
      if (valid.indexOf(file.type) === -1) {
        sweetError('Imagem deve ser JPG, PNG ou WEBP.');
        input.value = '';
        return;
      }
      if (file.size <= 0 || file.size > maxBytes) {
        sweetError('Imagem excede limite de 4MB.');
        input.value = '';
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) {
        setPreviewValue($form, key, e.target && e.target.result ? e.target.result : IMAGE_PLACEHOLDER, String($input.attr('name') || key));
      };
      reader.readAsDataURL(file);
    });
  }

  function bindAdminUsersBulkActions() {
    var $module = $('[data-users-module]');
    if (!$module.length) {
      return;
    }

    function syncState($scope) {
      var $localScope = $scope && $scope.length ? $scope : $module;
      var $selectAll = $localScope.find('.js-users-select-all').first();
      var $checkboxes = $localScope.find('.js-user-row-checkbox');
      var $count = $localScope.find('.js-users-selected-count').first();
      var $bulkDelete = $localScope.find('.js-users-bulk-delete').first();
      var total = $checkboxes.length;
      var selected = $checkboxes.filter(':checked').length;

      $count.text(String(selected));
      $bulkDelete.prop('disabled', selected === 0);

      if (selected === 0) {
        $selectAll.prop('indeterminate', false).prop('checked', false);
        return;
      }

      if (selected === total) {
        $selectAll.prop('indeterminate', false).prop('checked', true);
        return;
      }

      $selectAll.prop('checked', false).prop('indeterminate', true);
    }

    function selectedIds($scope) {
      return $scope.find('.js-user-row-checkbox:checked').map(function () {
        return parseInt($(this).val(), 10) || 0;
      }).get().filter(function (id) {
        return id > 0;
      });
    }

    $(document).on('change', '[data-users-module] .js-users-select-all', function () {
      var $scope = $(this).closest('[data-users-module]');
      var checked = $(this).is(':checked');
      $scope.find('.js-user-row-checkbox').prop('checked', checked);
      syncState($scope);
    });

    $(document).on('change', '[data-users-module] .js-user-row-checkbox', function () {
      syncState($(this).closest('[data-users-module]'));
    });

    $(document).on('click', '[data-users-module] .js-users-bulk-delete', function () {
      var $scope = $(this).closest('[data-users-module]');
      var endpoint = String($scope.find('[data-bulk-toolbar]').data('endpoint') || '');
      if (endpoint === '') {
        sweetError('Endpoint de exclusao em massa nao configurado.');
        return;
      }

      bulkDeleteByEndpoint(endpoint, selectedIds($scope));
    });

    syncState($module);
  }

  function bindAdminChrome() {
    var FAVORITES_KEY = 'wiseplay-admin-favorites';
    var LANGUAGE_KEY = 'wiseplay-admin-language';
    var NOTIFICATIONS_KEY = 'wiseplay-admin-notifications';
    var TEMPLATE_NAME = String(document.documentElement.getAttribute('data-template') || 'wiseplay-admin');
    var customizerSyncTimer = null;

    function getStoredFavorites() {
      try {
        var raw = window.localStorage.getItem(FAVORITES_KEY);
        var parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    }

    function saveStoredFavorites(items) {
      window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(items || []));
    }

    function currentFavoriteEntry() {
      var path = String((window.WISEPLAY && window.WISEPLAY.currentPath) || '/admin');
      var title = $.trim($('.wp-admin-page-intro-text h1').first().text()) || document.title || 'Admin';
      return {
        path: path,
        title: title
      };
    }

    function renderFavorites() {
      var favorites = getStoredFavorites();
      var current = currentFavoriteEntry();
      var $list = $('.js-admin-favorites-list');
      var $toggle = $('.js-admin-favorite-toggle');
      if (!$list.length || !$toggle.length) {
        return;
      }

      var exists = favorites.some(function (item) {
        return String(item.path || '') === current.path;
      });

      $toggle.text(exists ? 'Remove current' : 'Save current');

      if (!favorites.length) {
        $list.html('<div class="dropdown-item-text text-body-secondary small">No saved pages yet.</div>');
        return;
      }

      var html = favorites.map(function (item) {
        var safeTitle = $('<div>').text(String(item.title || item.path || 'Admin')).html();
        var safePath = $('<div>').text(String(item.path || '/admin')).html();
        return '<div class="dropdown-item-text d-flex align-items-center justify-content-between gap-2 wp-admin-favorite-row">' +
          '<a class="text-body flex-grow-1 text-decoration-none" href="' + safePath + '">' + safeTitle + '</a>' +
          '<button type="button" class="btn btn-sm btn-icon text-danger js-admin-favorite-remove" data-path="' + safePath + '" aria-label="Remove favorite"><i class="ri ri-close-line"></i></button>' +
        '</div>';
      }).join('');

      $list.html(html);
    }

    function getNotificationState() {
      try {
        var parsed = JSON.parse(window.localStorage.getItem(NOTIFICATIONS_KEY) || '{}');
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (error) {
        return {};
      }
    }

    function saveNotificationState(state) {
      window.localStorage.setItem(NOTIFICATIONS_KEY, JSON.stringify(state || {}));
    }

    function syncNotificationsUI() {
      var state = getNotificationState();
      var unread = 0;
      var visible = 0;

      $('.js-admin-notification-item').each(function () {
        var $item = $(this);
        var key = String($item.data('notificationKey') || '');
        var itemState = state[key] || {};
        var removed = itemState.removed === true;
        var read = itemState.read === true;

        $item.toggleClass('d-none', removed);
        $item.toggleClass('is-read', read);

        if (!removed) {
          visible += 1;
          if (!read) {
            unread += 1;
          }
        }
      });

      var $badge = $('.js-admin-notification-badge');
      if ($badge.length) {
        if (unread > 0) {
          $badge.text(unread > 9 ? '9+' : String(unread)).removeClass('d-none');
        } else {
          $badge.addClass('d-none');
        }
      }

      var $list = $('.js-admin-notifications-list');
      if ($list.length) {
        $list.find('.js-admin-notifications-empty').remove();
        if (visible === 0) {
          $list.append('<div class="dropdown-item-text text-body-secondary small js-admin-notifications-empty">No recent notifications.</div>');
        }
      }
    }

    function updateNotification(key, patch) {
      if (!key) {
        return;
      }
      var state = getNotificationState();
      state[key] = $.extend({}, state[key] || {}, patch || {});
      saveNotificationState(state);
      syncNotificationsUI();
    }

    function applyAdminLanguage(lang) {
      var normalized = lang === 'pt-BR' ? 'pt-BR' : 'en-US';
      var customizerLanguage = normalized === 'pt-BR' ? 'pt' : 'en';
      document.documentElement.setAttribute('lang', normalized);
      window.localStorage.setItem(LANGUAGE_KEY, normalized);
      window.localStorage.setItem('templateCustomizer-' + TEMPLATE_NAME + '--Lang', customizerLanguage);
      $('.js-admin-topbar-search')
        .attr('placeholder', normalized === 'pt-BR' ? 'Buscar [CTRL + K]' : 'Search [CTRL + K]')
        .attr('aria-label', normalized === 'pt-BR' ? 'Buscar no admin' : 'Search admin');
      $('.js-admin-lang-option').removeClass('active');
      $('.js-admin-lang-option[data-lang="' + normalized + '"]').addClass('active');

      if (window.WisePlayAdminCustomizer && typeof window.WisePlayAdminCustomizer.boot === 'function') {
        window.WisePlayAdminCustomizer.boot();
        scheduleCustomizerSync();
      }
    }

    function syncThemeIcon() {
      var currentTheme = String(document.documentElement.getAttribute('data-bs-theme') || 'light').toLowerCase();
      $('.js-admin-theme-toggle').find('i')
        .removeClass('ri-sun-line ri-moon-clear-line')
        .addClass(currentTheme === 'dark' ? 'ri-moon-clear-line' : 'ri-sun-line');
    }

    function customizerSetting(key) {
      try {
        return window.localStorage.getItem('templateCustomizer-' + TEMPLATE_NAME + '--' + key);
      } catch (error) {
        return null;
      }
    }

    function syncCustomizerState() {
      syncThemeIcon();

      var $menu = $('#layout-menu');
      if ($menu.length) {
        var currentTheme = String(document.documentElement.getAttribute('data-bs-theme') || 'light').toLowerCase();
        var semiDark = customizerSetting('SemiDark') === 'true' || document.documentElement.getAttribute('data-semidark-menu') === 'true';

        if (semiDark) {
          document.documentElement.setAttribute('data-semidark-menu', 'true');
        } else {
          document.documentElement.removeAttribute('data-semidark-menu');
        }

        if (semiDark && currentTheme !== 'dark') {
          $menu.attr('data-bs-theme', 'dark');
        } else {
          $menu.removeAttr('data-bs-theme');
        }
      }
    }

    function scheduleCustomizerSync() {
      if (customizerSyncTimer) {
        window.clearTimeout(customizerSyncTimer);
      }
      customizerSyncTimer = window.setTimeout(syncCustomizerState, 40);
    }

    function ensureCustomizer() {
      if (window.templateCustomizer && typeof window.templateCustomizer.update === 'function') {
        window.templateCustomizer.update();
      }
      return $('.template-customizer-open-btn').first().length > 0;
    }

    $(document).on('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && String(event.key || '').toLowerCase() === 'k') {
        event.preventDefault();
        $('.js-admin-topbar-search').first().trigger('focus');
      }
    });

    $(document).on('keydown', '.js-admin-topbar-search', function (event) {
      if (event.key !== 'Enter') {
        return;
      }

      event.preventDefault();
      var value = $.trim($(this).val());
      var $targets = $('[name="q"], [name="plan_q"], [name="sub_q"]').filter(':enabled:visible');
      var $target = $targets.first();
      if (!$target.length) {
        sweetError('Nao existe filtro de busca disponivel nesta pagina.');
        return;
      }

      $target.val(value);
      var $form = $target.closest('form');
      if ($form.length) {
        $form.trigger('submit');
      }
    });

    $(document).on('click', '.js-admin-open-customizer', function (event) {
      event.preventDefault();
      ensureCustomizer();
      var $trigger = $('.template-customizer-open-btn').first();
      if (!$trigger.length) {
        sweetError('Painel de personalizacao do tema indisponivel nesta pagina.');
        return;
      }
      $trigger.trigger('click');
    });

    $(document).on('click', '.js-admin-theme-toggle', function (event) {
      event.preventDefault();
      var currentTheme = String(document.documentElement.getAttribute('data-bs-theme') || 'light').toLowerCase();
      var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

      if (window.templateCustomizer && typeof window.templateCustomizer.setTheme === 'function') {
        window.templateCustomizer.setTheme(nextTheme);
      } else {
        document.documentElement.setAttribute('data-bs-theme', nextTheme);
      }
      document.documentElement.setAttribute('data-bs-theme', nextTheme);
      scheduleCustomizerSync();
    });

    $(document).on('click', '.js-admin-lang-option', function (event) {
      event.preventDefault();
      var lang = String($(this).data('lang') || 'en-US');
      applyAdminLanguage(lang);
    });

    $(document).on('click', '.js-admin-favorite-toggle', function (event) {
      event.preventDefault();
      var favorites = getStoredFavorites();
      var current = currentFavoriteEntry();
      var next = favorites.filter(function (item) {
        return String(item.path || '') !== current.path;
      });

      if (next.length === favorites.length) {
        next.unshift(current);
      }

      saveStoredFavorites(next.slice(0, 8));
      renderFavorites();
    });

    $(document).on('click', '.js-admin-favorite-remove', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var path = String($(this).data('path') || '');
      if (path === '') {
        return;
      }

      var next = getStoredFavorites().filter(function (item) {
        return String(item.path || '') !== path;
      });
      saveStoredFavorites(next);
      renderFavorites();
    });

    $(document).on('click', '.js-admin-notification-open', function (event) {
      event.preventDefault();
      var $item = $(this).closest('.js-admin-notification-item');
      var key = String($item.data('notificationKey') || '');
      updateNotification(key, { read: true });

      swalFire({
        icon: 'info',
        title: String($item.data('action') || 'Notification'),
        html:
          '<div class="text-start">' +
            '<p><strong>Actor:</strong> ' + $('<div>').text(String($item.data('actor') || 'System')).html() + '</p>' +
            '<p><strong>Entity:</strong> ' + $('<div>').text(String($item.data('entityType') || '-')).html() + '</p>' +
            '<p><strong>ID:</strong> ' + $('<div>').text(String($item.data('entityId') || '-')).html() + '</p>' +
            '<p><strong>Date:</strong> ' + $('<div>').text(String($item.data('createdAt') || '-')).html() + '</p>' +
          '</div>',
        confirmButtonText: 'Close'
      });
    });

    $(document).on('click', '.js-admin-notification-read', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var $item = $(this).closest('.js-admin-notification-item');
      updateNotification(String($item.data('notificationKey') || ''), { read: true });
    });

    $(document).on('click', '.js-admin-notification-remove', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var $item = $(this).closest('.js-admin-notification-item');
      updateNotification(String($item.data('notificationKey') || ''), { removed: true, read: true });
    });

    $(document).on('click change', '#template-customizer input, #template-customizer .custom-option, .template-customizer-reset-btn, .template-customizer-open-btn, .template-customizer-close-btn', function () {
      scheduleCustomizerSync();
    });

    if (window.MutationObserver) {
      var rootObserver = new MutationObserver(function () {
        scheduleCustomizerSync();
      });
      rootObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme', 'class', 'data-skin', 'data-semidark-menu', 'dir']
      });
    }

    applyAdminLanguage(window.localStorage.getItem(LANGUAGE_KEY) || document.documentElement.getAttribute('lang') || 'en-US');
    syncCustomizerState();
    renderFavorites();
    syncNotificationsUI();
    ensureCustomizer();
  }

  function refreshFormImagePreviews($form) {
    if (!$form || !$form.length) {
      return;
    }
    $form.find('input[name$="_image"], input[name="account_avatar"], input[name="avatar_path"]').each(function () {
      var $input = $(this);
      var key = previewKeyForInput($input);
      setPreviewValue($form, key, $input.val(), String($input.attr('name') || key));
    });
  }

  function applySemanticButtons() {
    $('.js-submit-form, button[type="submit"]').each(function () {
      var $button = $(this);
      var text = $.trim($button.text()).toLowerCase();
      if (text.indexOf('cancel') >= 0 || text.indexOf('voltar') >= 0 || text.indexOf('fechar') >= 0) {
        $button.addClass('btn-semantic-neutral');
        return;
      }
      if (text.indexOf('excluir') >= 0 || text.indexOf('remover') >= 0) {
        $button.addClass('btn-semantic-danger');
        return;
      }
      if (text.indexOf('ativar') >= 0 || text.indexOf('publicar') >= 0 || text.indexOf('confirmar') >= 0) {
        $button.addClass('btn-semantic-success');
        return;
      }
      if (text.indexOf('desativar') >= 0 || text.indexOf('arquivar') >= 0) {
        $button.addClass('btn-semantic-warning');
        return;
      }
      if ($button.hasClass('btn-primary')) {
        $button.addClass('btn-semantic-primary');
      }
    });
  }

  function bindGenericFormSubmit() {
    $(document).on('submit', '.js-ajax-form', function (event) {
      if (String($(this).data('mediaUpload') || '0') === '1') {
        return;
      }
      event.preventDefault();
      submitAjaxForm($(this));
    });

    $(document).on('click', '.js-submit-form', function (event) {
      event.preventDefault();
      var selector = $(this).data('form');
      if (!selector) {
        return;
      }
      var $form = $(selector);
      if (!$form.length) {
        return;
      }
      $form.trigger('submit');
    });

    $(document).on('click', '.js-open-modal', function (event) {
      event.preventDefault();
      var target = String($(this).data('target') || '');
      if (!target) {
        return;
      }

      var $modal = $(target);
      if (!$modal.length) {
        return;
      }

      var $form = $modal.find('form').first();
      if ($form.length) {
        $form.get(0).reset();
        clearErrors(String($form.data('errorsKey') || ''));
        refreshFormImagePreviews($form);
      }

      openModal(target);
    });
  }

  function bindUsersModule() {
    $(document).on('click', '.js-edit-user', function (event) {
      event.preventDefault();
      var user = parsePayload($(this).attr('data-user'));
      if (!user) {
        return;
      }

      $('#u-e-id').val(user.id || '');
      $('#u-e-name').val(user.name || '');
      $('#u-e-email').val(user.email || '');
      $('#u-e-account-avatar').val(user.account_avatar || '');
      $('#u-e-role').val(user.role || 'VIEWER');
      $('#u-e-status').val(user.status || 'ACTIVE');
      $('#u-e-plan-id').val(user.plan_id || '');

      refreshFormImagePreviews($('#user-edit-form'));
      clearErrors('user-edit');
      openModal('#user-edit-modal');
    });

    $(document).on('click', '.js-view-user', function (event) {
      event.preventDefault();
      var user = parsePayload($(this).attr('data-user'));
      if (!user) {
        return;
      }

      $('[data-user-detail="name"]').text(user.name || '-');
      $('[data-user-detail="email"]').text(user.email || '-');
      $('[data-user-detail="role"]').text(user.role || '-');
      $('[data-user-detail="status"]').text(user.status || '-');
      openModal('#user-view-modal');
    });

    $(document).on('click', '.js-toggle-user', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var next = String($(this).data('next') || 'ACTIVE');
      var name = String($(this).data('name') || 'usuario');
      if (id <= 0) {
        return;
      }

      var actionLabel = next === 'ACTIVE' ? 'ativar' : 'desativar';
      confirmAction('Alterar status', 'Deseja ' + actionLabel + ' o usuario "' + name + '"?', 'Confirmar')
        .then(function (result) {
          if (!result.isConfirmed) {
            return;
          }

          request('/admin/users/toggle', 'POST', $.param({ id: id, _csrf: csrfToken() }), false)
            .done(function (response) {
              if (!response || response.ok !== true) {
                sweetError((response && response.message) || 'Falha ao atualizar status.');
                return;
              }

              swalFire({
                icon: 'success',
                title: 'Sucesso',
                text: response.message || 'Status atualizado.'
              }).then(function () {
                window.location.reload();
              });
            })
            .fail(function (xhr) {
              var json = xhr.responseJSON || {};
              sweetError(json.message || 'Falha ao atualizar status.');
            });
        });
    });

    $(document).on('click', '.js-delete-user', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'usuario');
      if (id <= 0) {
        return;
      }
      deleteByEndpoint('/admin/users/delete', id, name, 'o usuario');
    });
  }

  function bindProfilesModule() {
    $(document).on('click', '.js-edit-profile', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-profile'));
      if (!item) {
        return;
      }

      $('#p-e-id').val(item.id || '');
      $('#p-e-user').val(item.user_id || '');
      $('#p-e-owner').val((item.user_name || '-') + ' (' + (item.user_email || '-') + ')');
      $('#p-e-name').val(item.profile_name || '');
      $('#p-e-avatar').val(item.avatar_path || '');
      $('#p-e-kids').val(item.is_kids || 0);
      $('#p-e-maturity').val(item.maturity_rating || '16');
      $('#p-e-language').val(item.language || 'pt-BR');
      $('#p-e-autoplay').val(item.autoplay || 1);

      refreshFormImagePreviews($('#profile-edit-form'));
      clearErrors('profile-edit');
      openModal('#profile-edit-modal');
    });

    $(document).on('click', '.js-delete-profile', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'perfil');
      if (id <= 0) {
        return;
      }
      deleteByEndpoint('/admin/profiles/delete', id, name, 'o perfil');
    });
  }

  function bindTitlesModule() {
    $(document).on('click', '.js-edit-title', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-title'));
      if (!item) {
        return;
      }

      $('#t-e-id').val(item.id || '');
      $('#t-e-title-name').val(item.title_name || '');
      $('#t-e-slug').val(item.slug || '');
      $('#t-e-type').val(item.type || 'MOVIE');
      $('#t-e-description').val(item.description || '');
      $('#t-e-genre').val(item.genre || 'General');
      $('#t-e-year').val(item.year || '');
      $('#t-e-maturity-rating').val(item.maturity_rating || '16');
      $('#t-e-featured').val(item.featured || 0);
      $('#t-e-status').val(item.status || 'DRAFT');
      $('#t-e-cover-image').val(item.cover_image || '');
      $('#t-e-backdrop-image').val(item.backdrop_image || '');

      refreshFormImagePreviews($('#title-edit-form'));
      clearErrors('title-edit');
      openModal('#title-edit-modal');
    });

    $(document).on('click', '.js-delete-title', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'titulo');
      if (id <= 0) {
        return;
      }
      deleteByEndpoint('/admin/titles/delete', id, name, 'o titulo');
    });
  }

  function mediaSlug(value) {
    var normalized = String(value || '').toLowerCase();
    normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    normalized = normalized.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return normalized || 'untitled';
  }

  function mediaFormatBytes(bytes) {
    var value = parseInt(bytes, 10);
    if (!Number.isFinite(value) || value <= 0) {
      return '0 B';
    }
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var unit = 0;
    var amount = value;
    while (amount >= 1024 && unit < units.length - 1) {
      amount /= 1024;
      unit += 1;
    }
    var decimals = unit <= 1 ? 0 : 1;
    return amount.toFixed(decimals) + ' ' + units[unit];
  }

  function mediaDestinationPath($form) {
    var root = mediaSlug(String($form.data('onedriveRootFolder') || 'WisePlay'));
    var $title = $form.find('.js-media-title');
    var type = String($title.find(':selected').data('type') || 'MOVIE').toUpperCase();
    var slug = mediaSlug(String($title.find(':selected').data('slug') || 'untitled'));
    var season = parseInt($form.find('.js-media-season').val(), 10) || 0;
    var padded = String(Math.max(season, 1)).padStart(2, '0');

    if (type === 'MOVIE') {
      return '/' + root + '/movies/' + slug + '/video/';
    }
    if (type === 'COURSE') {
      return '/' + root + '/courses/' + slug + '/module-' + padded + '/lessons/';
    }
    return '/' + root + '/series/' + slug + '/season-' + padded + '/episodes/';
  }

  function setMediaDestinationPreview($form) {
    var path = mediaDestinationPath($form);
    var $preview = $form.find('#m-c-destination-preview');
    if ($preview.length) {
      $preview.text(path);
    }
  }

  function syncMediaFields($context) {
    var $titleSelect = $context.find('.js-media-title');
    var type = String($titleSelect.find(':selected').data('type') || 'MOVIE').toUpperCase();
    var sourceType = String($context.find('select[name="source_type"]').val() || 'ONEDRIVE').toUpperCase();

    var isMovie = type === 'MOVIE';
    var $seasonInput = $context.find('.js-media-season');
    var $episodeInput = $context.find('.js-media-episode');

    if (isMovie) {
      $seasonInput.val(0).prop('readonly', true).attr('min', '0');
      $episodeInput.val(0).prop('readonly', true).attr('min', '0');
    } else {
      if ((parseInt($seasonInput.val(), 10) || 0) < 1) {
        $seasonInput.val(1);
      }
      if ((parseInt($episodeInput.val(), 10) || 0) < 1) {
        $episodeInput.val(1);
      }
      $seasonInput.prop('readonly', false).attr('min', '1');
      $episodeInput.prop('readonly', false).attr('min', '1');
    }

    var onedrive = sourceType === 'ONEDRIVE';
    var $videoInput = $context.find('input[name="video_file"]');
    if ($videoInput.length) {
      $videoInput.prop('required', onedrive && String($context.attr('id') || '') === 'media-create-form');
    }

    setMediaDestinationPreview($context);
  }

  function updateMediaFilePreview($form) {
    var $list = $form.find('#m-c-file-preview-list');
    if (!$list.length) {
      return;
    }

    var entries = [];
    $form.find('input[type="file"]').each(function () {
      var input = this;
      if (!input.files || !input.files.length) {
        return;
      }
      Array.prototype.forEach.call(input.files, function (file) {
        entries.push('<li><strong>' + $('<div>').text(file.name).html() + '</strong> <small class=\"text-body-secondary\">(' + mediaFormatBytes(file.size) + ')</small></li>');
      });
    });

    if (entries.length === 0) {
      $list.html('<li class=\"text-body-secondary\">Nenhum arquivo selecionado.</li>');
    } else {
      $list.html(entries.join(''));
    }

    var $imagePreview = $form.find('#m-c-image-preview');
    if (!$imagePreview.length) {
      return;
    }

    var picked = null;
    $form.find('input[name=\"thumbnail_file\"], input[name=\"poster_file\"], input[name=\"backdrop_file\"]').each(function () {
      if (picked) {
        return;
      }
      if (this.files && this.files.length) {
        picked = this.files[0];
      }
    });

    if (!picked) {
      $imagePreview.attr('src', IMAGE_PLACEHOLDER);
      return;
    }

    var reader = new FileReader();
    reader.onload = function (e) {
      $imagePreview.attr('src', e.target && e.target.result ? e.target.result : IMAGE_PLACEHOLDER);
    };
    reader.readAsDataURL(picked);
  }

  var mediaUploadStates = {};

  function mediaProgressSelectors($form) {
    var create = String($form.attr('id') || '') === 'media-create-form';
    var prefix = create ? '#m-c-' : '#m-e-';
    return {
      wrap: $form.find(prefix + 'upload-progress-wrap'),
      status: $form.find(prefix + 'upload-status'),
      percent: $form.find(prefix + 'upload-percent'),
      bar: $form.find(prefix + 'upload-progress'),
      chunk: $form.find(prefix + 'upload-chunk'),
      bytes: $form.find(prefix + 'upload-bytes'),
      speed: $form.find(prefix + 'upload-speed'),
      eta: $form.find(prefix + 'upload-eta'),
      retries: $form.find(prefix + 'upload-retries'),
      pauseButton: $form.find('.js-media-upload-pause[data-form="#' + String($form.attr('id') || '') + '"]'),
      resumeButton: $form.find('.js-media-upload-resume[data-form="#' + String($form.attr('id') || '') + '"]'),
      cancelButton: $form.find('.js-media-upload-cancel[data-form="#' + String($form.attr('id') || '') + '"]')
    };
  }

  function mediaUploadStateKey($form) {
    return String($form.attr('id') || 'media-upload-default');
  }

  function mediaUploadState($form) {
    var key = mediaUploadStateKey($form);
    if (!mediaUploadStates[key]) {
      mediaUploadStates[key] = {
        active: false,
        paused: false,
        cancelRequested: false,
        abortController: null,
        uploadUrl: '',
        checkpointKey: '',
        lastPercent: 0,
        lastChunk: 0,
        lastTotalChunks: 0,
        lastMeta: null,
        submitLocked: false
      };
    }
    return mediaUploadStates[key];
  }

  function resetMediaUploadState($form) {
    var state = mediaUploadState($form);
    state.active = false;
    state.paused = false;
    state.cancelRequested = false;
    state.abortController = null;
    state.uploadUrl = '';
    state.checkpointKey = '';
    state.lastPercent = 0;
    state.lastChunk = 0;
    state.lastTotalChunks = 0;
    state.lastMeta = null;
    state.submitLocked = false;
  }

  function mediaEtaLabel(seconds) {
    var secs = Math.max(0, Math.floor(seconds || 0));
    if (secs <= 0) {
      return '--:--';
    }
    var hours = Math.floor(secs / 3600);
    var minutes = Math.floor((secs % 3600) / 60);
    var rem = secs % 60;
    if (hours > 0) {
      return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(rem).padStart(2, '0');
    }
    return String(minutes).padStart(2, '0') + ':' + String(rem).padStart(2, '0');
  }

  function updateMediaProgressControls($form, options) {
    var opts = options || {};
    var ui = mediaProgressSelectors($form);
    var active = Boolean(opts.active);
    var paused = Boolean(opts.paused);

    if (active) {
      ui.pauseButton.toggle(!paused);
      ui.resumeButton.toggle(paused);
      ui.cancelButton.show();
      return;
    }

    ui.pauseButton.hide();
    ui.resumeButton.hide();
    ui.cancelButton.hide();
  }

  function setMediaProgress($form, status, percent, currentChunk, totalChunks, meta) {
    var ui = mediaProgressSelectors($form);
    var pct = Math.max(0, Math.min(100, parseFloat(percent) || 0));
    var info = meta || {};
    var uploadedBytes = parseInt(info.uploadedBytes, 10) || 0;
    var totalBytes = parseInt(info.totalBytes, 10) || 0;
    var speedBytesPerSec = Number(info.speedBytesPerSec) || 0;
    var etaSeconds = Number(info.etaSeconds);
    var retryAttempt = parseInt(info.retryAttempt, 10) || 0;
    var maxRetries = parseInt(info.maxRetries, 10) || 0;

    ui.wrap.show();
    ui.status.text(String(status || 'Uploading'));
    ui.percent.text(pct.toFixed(1) + '%');
    ui.bar.css('width', pct.toFixed(1) + '%');
    ui.chunk.text(String(currentChunk || 0) + '/' + String(totalChunks || 0));
    ui.bytes.text(mediaFormatBytes(uploadedBytes) + ' / ' + mediaFormatBytes(totalBytes));
    ui.speed.text((speedBytesPerSec / (1024 * 1024)).toFixed(2) + ' MB/s');
    ui.eta.text(Number.isFinite(etaSeconds) ? mediaEtaLabel(etaSeconds) : '--:--');
    ui.retries.text(String(Math.max(0, retryAttempt)) + '/' + String(Math.max(0, maxRetries)));

    var state = mediaUploadState($form);
    state.lastPercent = pct;
    state.lastChunk = parseInt(currentChunk, 10) || 0;
    state.lastTotalChunks = parseInt(totalChunks, 10) || 0;
    state.lastMeta = {
      uploadedBytes: uploadedBytes,
      totalBytes: totalBytes,
      speedBytesPerSec: speedBytesPerSec,
      etaSeconds: etaSeconds,
      retryAttempt: retryAttempt,
      maxRetries: maxRetries,
      active: Boolean(info.active),
      paused: Boolean(info.paused)
    };

    updateMediaProgressControls($form, {
      active: Boolean(info.active),
      paused: Boolean(info.paused)
    });
  }

  function resetMediaProgress($form) {
    var ui = mediaProgressSelectors($form);
    ui.wrap.hide();
    ui.status.text('Preparing');
    ui.percent.text('0.0%');
    ui.bar.css('width', '0%');
    ui.chunk.text('0/0');
    ui.bytes.text('0 B / 0 B');
    ui.speed.text('0.00 MB/s');
    ui.eta.text('--:--');
    ui.retries.text('0/0');
    updateMediaProgressControls($form, { active: false, paused: false });
    resetMediaUploadState($form);
  }

  function uploadCheckpointKey($form, file, titleId, season, episode) {
    return 'wiseplay.onedrive.upload.' + [
      String($form.attr('id') || ''),
      String(titleId || 0),
      String(season || 0),
      String(episode || 0),
      String(file && file.name ? file.name : ''),
      String(file && file.size ? file.size : 0),
      String(file && file.lastModified ? file.lastModified : 0)
    ].join('|');
  }

  function readUploadCheckpoint(key) {
    if (!window.localStorage) {
      return null;
    }
    var raw = window.localStorage.getItem(key);
    if (!raw) {
      return null;
    }
    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function saveUploadCheckpoint(key, payload) {
    if (!window.localStorage) {
      return;
    }
    try {
      window.localStorage.setItem(key, JSON.stringify(payload || {}));
    } catch (error) {
      // ignore storage failures (quota/private mode)
    }
  }

  function clearUploadCheckpoint(key) {
    if (!window.localStorage) {
      return;
    }
    window.localStorage.removeItem(key);
  }

  async function fetchUploadSessionOffset(uploadUrl, fallbackOffset) {
    if (!uploadUrl) {
      return fallbackOffset;
    }

    try {
      var response = await fetch(uploadUrl, { method: 'GET' });
      if (!response.ok) {
        return fallbackOffset;
      }
      var body = await response.json();
      if (body && Array.isArray(body.nextExpectedRanges) && body.nextExpectedRanges.length) {
        return parseNextExpectedStart(body.nextExpectedRanges, fallbackOffset);
      }
    } catch (error) {
      return fallbackOffset;
    }

    return fallbackOffset;
  }

  async function cancelUploadSession(uploadUrl) {
    if (!uploadUrl) {
      return;
    }
    try {
      await fetch(uploadUrl, { method: 'DELETE' });
    } catch (error) {
      // ignore cancel errors
    }
  }

  function progressMeta(startedAt, uploadedBytes, totalBytes, retryAttempt, maxRetries, active, paused) {
    var elapsedSec = Math.max(0.001, (Date.now() - startedAt) / 1000);
    var speed = uploadedBytes > 0 ? (uploadedBytes / elapsedSec) : 0;
    var eta = speed > 0 ? ((Math.max(0, totalBytes - uploadedBytes)) / speed) : NaN;
    return {
      uploadedBytes: uploadedBytes,
      totalBytes: totalBytes,
      speedBytesPerSec: speed,
      etaSeconds: eta,
      retryAttempt: retryAttempt,
      maxRetries: maxRetries,
      active: active,
      paused: paused
    };
  }

  function applyMediaUploadResult($form, response) {
    var upload = response && response.upload ? response.upload : null;
    if (!upload) {
      return;
    }

    var create = String($form.attr('id') || '') === 'media-create-form';
    var prefix = create ? 'm-c' : 'm-e';

    $('#' + prefix + '-source-type').val(upload.source_type || 'ONEDRIVE');
    $('#' + prefix + '-external-id').val(upload.external_id || '');
    $('#' + prefix + '-source-path').val(upload.source_path || '');
    $('#' + prefix + '-stream-ref').val(upload.stream_ref || '');
    $('#' + prefix + '-stream-ref-view').val(upload.stream_ref || '');
    $('#' + prefix + '-stream-url').val(upload.stream_url || '');
    $('#' + prefix + '-download-url').val(upload.download_url || '');
    $('#' + prefix + '-drive-id').val(upload.drive_id || '');
    $('#' + prefix + '-parent-item-id').val(upload.parent_item_id || '');
    $('#' + prefix + '-mime-type').val(upload.mime_type || '');
    $('#' + prefix + '-file-size-bytes').val(upload.file_size_bytes || 0);
    $('#' + prefix + '-stream-url-expires-at').val(upload.stream_url_expires_at || '');
    $('#' + prefix + '-last-source-sync-at').val(upload.last_source_sync_at || '');
    $('#' + prefix + '-upload-status-field').val('READY');

    if (upload.chunks_total) {
      setMediaProgress($form, 'Completed', 100, upload.chunks_uploaded || upload.chunks_total, upload.chunks_total, {
        uploadedBytes: parseInt(upload.file_size_bytes, 10) || 0,
        totalBytes: parseInt(upload.file_size_bytes, 10) || 0,
        speedBytesPerSec: 0,
        etaSeconds: 0,
        retryAttempt: 0,
        maxRetries: 0,
        active: false,
        paused: false
      });
    }
  }

  function mediaDebugEnabled($form) {
    return String($form.data('uploadDebug') || '0') === '1';
  }

  function mediaDebugLog($form, step, payload) {
    if (!mediaDebugEnabled($form) || typeof console === 'undefined' || !console.debug) {
      return;
    }
    console.debug('[WisePlay OneDrive Upload]', step, payload || {});
  }

  function sendChunkTelemetry($form, payload) {
    if (!mediaDebugEnabled($form)) {
      return;
    }

    var body = $.extend({
      _csrf: csrfToken()
    }, payload || {});

    request('/admin/media/upload-session/chunk-log', 'POST', $.param(body), false);
  }

  function requestPromise(url, method, data, hasFiles) {
    return new Promise(function (resolve, reject) {
      request(url, method, data, hasFiles)
        .done(function (response) {
          resolve(response);
        })
        .fail(function (xhr) {
          reject(xhr);
        });
    });
  }

  function parseNextExpectedStart(nextExpectedRanges, fallbackStart) {
    if (!Array.isArray(nextExpectedRanges) || nextExpectedRanges.length === 0) {
      return fallbackStart;
    }

    var first = String(nextExpectedRanges[0] || '');
    var rangeStart = first.split('-')[0];
    var parsed = parseInt(rangeStart, 10);
    if (!Number.isFinite(parsed) || parsed < 0) {
      return fallbackStart;
    }
    return parsed;
  }

  function graphErrorMessage(responseBody, fallback) {
    if (responseBody && responseBody.error && responseBody.error.message) {
      return String(responseBody.error.message);
    }
    if (responseBody && responseBody.message) {
      return String(responseBody.message);
    }
    return String(fallback || 'Falha no upload do chunk para OneDrive.');
  }

  function sleepMs(ms) {
    return new Promise(function (resolve) {
      window.setTimeout(resolve, ms);
    });
  }

  function mapGraphItemToUploadResult(graphItem) {
    var item = graphItem && typeof graphItem === 'object' ? graphItem : {};
    var parentReference = item.parentReference && typeof item.parentReference === 'object' ? item.parentReference : {};
    var parentPathRaw = String(parentReference.path || '');
    var rootIndex = parentPathRaw.indexOf('/root:');
    var parentPath = rootIndex >= 0 ? parentPathRaw.slice(rootIndex + 6) : parentPathRaw;
    var sourcePath = (parentPath !== '' ? parentPath + '/' : '/') + String(item.name || '');
    var mimeType = item.file && item.file.mimeType ? String(item.file.mimeType) : '';
    var downloadUrl = String(item['@microsoft.graph.downloadUrl'] || '');
    var now = new Date();
    var expires = new Date(now.getTime() + (55 * 60 * 1000));

    return {
      source_type: 'ONEDRIVE',
      external_id: String(item.id || ''),
      source_path: sourcePath,
      stream_ref: item.id ? ('onedrive:item:' + String(item.id)) : '',
      stream_url: downloadUrl,
      stream_url_expires_at: expires.toISOString().slice(0, 19).replace('T', ' '),
      download_url: downloadUrl,
      drive_id: String(parentReference.driveId || ''),
      parent_item_id: String(parentReference.id || ''),
      mime_type: mimeType,
      file_size_bytes: parseInt(item.size, 10) || 0,
      upload_status: 'READY',
      last_source_sync_at: now.toISOString().slice(0, 19).replace('T', ' ')
    };
  }

  async function uploadVideoToOneDriveSession($form, file) {
    var formId = String($form.attr('id') || '');
    var isCreate = formId === 'media-create-form';
    var state = mediaUploadState($form);
    var titleId = parseInt($form.find('[name="title_id"]').val(), 10) || 0;
    var season = parseInt($form.find('[name="season_number"]').val(), 10) || 0;
    var episode = parseInt($form.find('[name="episode_number"]').val(), 10) || 0;
    var configuredChunkBytes = parseInt($form.data('chunkBytes'), 10) || (10 * 1024 * 1024);
    var configuredMaxRetries = parseInt($form.data('uploadMaxRetries'), 10) || 3;
    var configuredBackoffMs = parseInt($form.data('uploadRetryBackoffMs'), 10) || 500;
    var totalSize = parseInt(file.size, 10) || 0;
    var fileLimitBytes = parseInt($form.data('maxVideoBytes'), 10) || (10 * 1024 * 1024 * 1024);
    var quotaRemainingBytes = parseInt($form.data('quotaRemainingBytes'), 10) || 0;
    var startedAt = Date.now();

    if (totalSize <= 0) {
      throw new Error('Arquivo invalido para upload.');
    }
    if (totalSize > fileLimitBytes) {
      throw new Error('Arquivo excede limite configurado para OneDrive (' + mediaFormatBytes(fileLimitBytes) + ').');
    }
    if (quotaRemainingBytes > 0 && totalSize > quotaRemainingBytes) {
      throw new Error('Espaco insuficiente no OneDrive para este arquivo.');
    }

    mediaDebugLog($form, 'session.start.request', {
      title_id: titleId,
      season_number: season,
      episode_number: episode,
      file_name: file.name,
      file_size: file.size,
      mime_type: file.type
    });

    var uploadUrl = '';
    var expiresAt = '';
    var chunkBytes = configuredChunkBytes;
    var maxRetries = configuredMaxRetries;
    var retryBackoffMs = configuredBackoffMs;
    var checkpointKey = uploadCheckpointKey($form, file, titleId, season, episode);
    var checkpoint = readUploadCheckpoint(checkpointKey);
    var offset = 0;
    var sessionResponse = null;
    state.checkpointKey = checkpointKey;

    if (checkpoint && checkpoint.upload_url && checkpoint.expires_at) {
      var expiresTs = Date.parse(String(checkpoint.expires_at || ''));
      if (Number.isFinite(expiresTs) && expiresTs > Date.now()) {
        uploadUrl = String(checkpoint.upload_url);
        expiresAt = String(checkpoint.expires_at);
        offset = Math.max(0, parseInt(checkpoint.offset, 10) || 0);
        chunkBytes = Math.max(5 * 1024 * 1024, Math.min(20 * 1024 * 1024, parseInt(checkpoint.chunk_bytes, 10) || chunkBytes));
        maxRetries = Math.max(2, Math.min(6, parseInt(checkpoint.max_retries, 10) || maxRetries));
        retryBackoffMs = Math.max(200, Math.min(10000, parseInt(checkpoint.retry_backoff_ms, 10) || retryBackoffMs));
        offset = await fetchUploadSessionOffset(uploadUrl, offset);
        mediaDebugLog($form, 'session.resume.from_checkpoint', {
          offset: offset,
          upload_url_present: true
        });
      } else {
        clearUploadCheckpoint(checkpointKey);
      }
    }

    if (!uploadUrl) {
      sessionResponse = await requestPromise('/admin/media/upload-session/start', 'POST', $.param({
        _csrf: csrfToken(),
        title_id: titleId,
        season_number: season,
        episode_number: episode,
        file_name: file.name,
        file_size: file.size,
        mime_type: file.type || 'application/octet-stream'
      }), false);

      if (!sessionResponse || sessionResponse.ok !== true || !sessionResponse.session) {
        throw new Error((sessionResponse && sessionResponse.message) || 'Falha ao iniciar upload session.');
      }

      uploadUrl = String((sessionResponse.session && sessionResponse.session.upload_url) || '');
      expiresAt = String((sessionResponse.session && sessionResponse.session.expires_at) || '');
      chunkBytes = parseInt((sessionResponse.session && sessionResponse.session.chunk_bytes), 10) || chunkBytes;
      fileLimitBytes = parseInt((sessionResponse.session && sessionResponse.session.max_video_bytes), 10) || fileLimitBytes;
      maxRetries = parseInt((sessionResponse.session && sessionResponse.session.max_retries), 10) || maxRetries;
      retryBackoffMs = parseInt((sessionResponse.session && sessionResponse.session.retry_backoff_ms), 10) || retryBackoffMs;

      if (!uploadUrl) {
        throw new Error('Upload session sem uploadUrl valido.');
      }
    }

    chunkBytes = Math.max(5 * 1024 * 1024, Math.min(20 * 1024 * 1024, chunkBytes));
    maxRetries = Math.max(2, Math.min(6, maxRetries));
    retryBackoffMs = Math.max(200, Math.min(10000, retryBackoffMs));
    state.uploadUrl = uploadUrl;
    state.active = true;
    state.paused = false;
    state.cancelRequested = false;

    mediaDebugLog($form, 'session.start.response', {
      upload_url_present: uploadUrl !== '',
      expires_at: expiresAt,
      chunk_bytes: chunkBytes,
      max_retries: maxRetries,
      retry_backoff_ms: retryBackoffMs,
      destination_path: sessionResponse && sessionResponse.session ? (sessionResponse.session.destination_path || '') : ''
    });

    var totalChunks = Math.max(1, Math.ceil(totalSize / chunkBytes));
    var finalGraphItem = null;

    while (offset < totalSize) {
      if (state.cancelRequested) {
        throw new Error('Upload cancelado pelo usuario.');
      }

      while (state.paused && !state.cancelRequested) {
        setMediaProgress($form, 'Paused', ((offset / totalSize) * 100), Math.max(1, Math.ceil(Math.max(offset, 1) / chunkBytes)), totalChunks, progressMeta(startedAt, offset, totalSize, 0, maxRetries, true, true));
        await sleepMs(250);
      }
      if (state.cancelRequested) {
        throw new Error('Upload cancelado pelo usuario.');
      }

      var start = offset;
      var end = Math.min(start + chunkBytes, totalSize) - 1;
      var blob = file.slice(start, end + 1);
      var contentRange = 'bytes ' + start + '-' + end + '/' + totalSize;
      var chunkIndex = Math.max(1, Math.floor(start / chunkBytes) + 1);

      mediaDebugLog($form, 'chunk.start', {
        chunk_index: chunkIndex,
        chunk_total: totalChunks,
        start: start,
        end: end,
        content_length: blob.size,
        content_range: contentRange
      });
      sendChunkTelemetry($form, {
        phase: 'start',
        chunk_index: chunkIndex,
        chunk_total: totalChunks,
        start: start,
        end: end,
        content_length: blob.size,
        content_range: contentRange
      });

      setMediaProgress($form, 'Uploading', ((start / totalSize) * 100), chunkIndex, totalChunks, progressMeta(startedAt, start, totalSize, 0, maxRetries, true, false));
      var response = null;
      var responseText = '';
      var responseBody = null;
      var attempt = 0;
      var uploaded = false;

      while (attempt < maxRetries && !uploaded) {
        if (state.cancelRequested) {
          throw new Error('Upload cancelado pelo usuario.');
        }
        attempt += 1;
        try {
          state.abortController = new AbortController();
          response = await fetch(uploadUrl, {
            method: 'PUT',
            headers: {
              'Content-Length': String(blob.size),
              'Content-Range': contentRange,
              'Content-Type': 'application/octet-stream'
            },
            body: blob,
            signal: state.abortController.signal
          });
          state.abortController = null;

          responseText = await response.text();
          responseBody = null;
          if (responseText) {
            try {
              responseBody = JSON.parse(responseText);
            } catch (error) {
              responseBody = null;
            }
          }

          var retryableStatus = response.status === 408 || response.status === 429 || response.status >= 500;
          if (retryableStatus && attempt < maxRetries) {
            setMediaProgress($form, 'Retrying', ((start / totalSize) * 100), chunkIndex, totalChunks, progressMeta(startedAt, start, totalSize, attempt, maxRetries, true, false));
            mediaDebugLog($form, 'chunk.retryable_status', {
              chunk_index: chunkIndex,
              attempt: attempt,
              status: response.status
            });
            await sleepMs(retryBackoffMs * attempt);
            continue;
          }

          uploaded = true;
        } catch (networkError) {
          mediaDebugLog($form, 'chunk.network_error', {
            chunk_index: chunkIndex,
            attempt: attempt,
            error: networkError && networkError.message ? networkError.message : String(networkError)
          });
          sendChunkTelemetry($form, {
            phase: 'network_error',
            chunk_index: chunkIndex,
            chunk_total: totalChunks,
            start: start,
            end: end,
            content_length: blob.size,
            content_range: contentRange,
            message: networkError && networkError.message ? networkError.message : String(networkError)
          });
          var abortMessage = networkError && networkError.name ? String(networkError.name) : '';
          if (state.cancelRequested || abortMessage === 'AbortError') {
            throw new Error('Upload cancelado pelo usuario.');
          }
          if (attempt >= maxRetries) {
            throw new Error('Falha de rede ao enviar chunk ' + chunkIndex + '.');
          }
          setMediaProgress($form, 'Retrying', ((start / totalSize) * 100), chunkIndex, totalChunks, progressMeta(startedAt, start, totalSize, attempt, maxRetries, true, false));
          await sleepMs(retryBackoffMs * attempt);
        }
      }

      if (!response) {
        throw new Error('Upload sem resposta do OneDrive.');
      }

      mediaDebugLog($form, 'chunk.response', {
        chunk_index: chunkIndex,
        attempt: attempt,
        status: response.status,
        ok: response.ok,
        response_body: responseBody,
        response_text: responseBody ? '[json]' : responseText
      });
      sendChunkTelemetry($form, {
        phase: 'response',
        chunk_index: chunkIndex,
        chunk_total: totalChunks,
        start: start,
        end: end,
        content_length: blob.size,
        content_range: contentRange,
        status: response.status,
        response_excerpt: String(responseText || '').slice(0, 400)
      });

      if (response.status === 202) {
        var uploadedBytes = end + 1;
        var nextStart = parseNextExpectedStart(responseBody && responseBody.nextExpectedRanges, uploadedBytes);
        if (nextStart <= start) {
          nextStart = uploadedBytes;
        }
        offset = Math.min(nextStart, totalSize);
        setMediaProgress($form, 'Uploading', ((offset / totalSize) * 100), chunkIndex, totalChunks, progressMeta(startedAt, offset, totalSize, 0, maxRetries, true, false));
        saveUploadCheckpoint(checkpointKey, {
          upload_url: uploadUrl,
          expires_at: expiresAt,
          offset: offset,
          destination_path: sessionResponse && sessionResponse.session ? (sessionResponse.session.destination_path || '') : (checkpoint && checkpoint.destination_path ? checkpoint.destination_path : ''),
          chunk_bytes: chunkBytes,
          max_retries: maxRetries,
          retry_backoff_ms: retryBackoffMs
        });
        continue;
      }

      if (response.status === 416 && responseBody && responseBody.nextExpectedRanges) {
        var resumeStart = parseNextExpectedStart(responseBody.nextExpectedRanges, end + 1);
        if (resumeStart <= start) {
          throw new Error('Resposta 416 sem proximo range valido para retomar upload.');
        }
        offset = Math.min(resumeStart, totalSize);
        setMediaProgress($form, 'Uploading', ((offset / totalSize) * 100), chunkIndex, totalChunks, progressMeta(startedAt, offset, totalSize, 0, maxRetries, true, false));
        saveUploadCheckpoint(checkpointKey, {
          upload_url: uploadUrl,
          expires_at: expiresAt,
          offset: offset,
          destination_path: sessionResponse && sessionResponse.session ? (sessionResponse.session.destination_path || '') : (checkpoint && checkpoint.destination_path ? checkpoint.destination_path : ''),
          chunk_bytes: chunkBytes,
          max_retries: maxRetries,
          retry_backoff_ms: retryBackoffMs
        });
        continue;
      }

      if (response.status === 200 || response.status === 201) {
        finalGraphItem = responseBody;
        offset = totalSize;
        setMediaProgress($form, 'Finalizing', 99.4, totalChunks, totalChunks, progressMeta(startedAt, totalSize, totalSize, 0, maxRetries, true, false));
        break;
      }

      throw new Error(graphErrorMessage(responseBody, 'Falha no upload do chunk para OneDrive (HTTP ' + response.status + ').'));
    }

    if (!finalGraphItem || !finalGraphItem.id) {
      throw new Error('Upload concluido sem metadados finais do arquivo.');
    }

    mediaDebugLog($form, 'session.complete.response', {
      item_id: finalGraphItem.id,
      name: finalGraphItem.name,
      size: finalGraphItem.size
    });

    clearUploadCheckpoint(checkpointKey);
    state.active = false;
    state.paused = false;
    state.cancelRequested = false;
    state.abortController = null;

    var uploadResult = mapGraphItemToUploadResult(finalGraphItem);
    uploadResult.chunks_total = totalChunks;
    uploadResult.chunks_uploaded = totalChunks;
    uploadResult.expires_at = expiresAt;
    if (isCreate) {
      uploadResult.destination_path = sessionResponse && sessionResponse.session
        ? (sessionResponse.session.destination_path || '')
        : (checkpoint && checkpoint.destination_path ? checkpoint.destination_path : '');
    }
    return uploadResult;
  }

  function persistMediaMutation($form, omitVideoField) {
    var endpoint = String($form.data('endpoint') || '');
    var payload = new FormData($form.get(0));
    if (!payload.get('_csrf')) {
      payload.append('_csrf', csrfToken());
    }
    if (omitVideoField) {
      payload.delete('video_file');
    }

    return new Promise(function (resolve, reject) {
      $.ajax({
        url: endpoint,
        method: 'POST',
        dataType: 'json',
        data: payload,
        processData: false,
        contentType: false,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .done(function (response) {
          resolve(response);
        })
        .fail(function (xhr) {
          reject(xhr);
        });
    });
  }

  async function submitMediaUploadForm($form) {
    var endpoint = String($form.data('endpoint') || '');
    if (endpoint === '') {
      return;
    }

    var errorKey = String($form.data('errorsKey') || '');
    clearErrors(errorKey);

    var formId = String($form.attr('id') || '');
    var $submitButton = $('.js-submit-form[data-form=\"#' + formId + '\"]');
    var state = mediaUploadState($form);
    if (state.submitLocked) {
      return;
    }
    var sourceType = String($form.find('select[name=\"source_type\"]').val() || 'ONEDRIVE').toUpperCase();
    var videoInput = $form.find('input[name=\"video_file\"]').get(0);
    var hasVideo = Boolean(videoInput && videoInput.files && videoInput.files.length);
    var shouldDirectOneDriveUpload = sourceType === 'ONEDRIVE' && hasVideo;

    mediaDebugLog($form, 'submit.start', {
      endpoint: endpoint,
      source_type: sourceType,
      has_video: hasVideo,
      direct_onedrive: shouldDirectOneDriveUpload
    });

    state.submitLocked = true;
    $submitButton.prop('disabled', true);
    setMediaProgress($form, 'Preparing', 1, 0, 0, {
      uploadedBytes: 0,
      totalBytes: hasVideo ? (videoInput.files[0].size || 0) : 0,
      speedBytesPerSec: 0,
      etaSeconds: NaN,
      retryAttempt: 0,
      maxRetries: parseInt($form.data('uploadMaxRetries'), 10) || 3,
      active: shouldDirectOneDriveUpload,
      paused: false
    });
    $form.find('input[name="upload_status"]').val(shouldDirectOneDriveUpload ? 'UPLOADING' : 'READY');

    try {
      if (shouldDirectOneDriveUpload) {
        var file = videoInput.files[0];
        var uploadResult = await uploadVideoToOneDriveSession($form, file);
        applyMediaUploadResult($form, { upload: uploadResult });
        setMediaProgress($form, 'Finalizing', 99, uploadResult.chunks_total || 1, uploadResult.chunks_total || 1, {
          uploadedBytes: parseInt(uploadResult.file_size_bytes, 10) || file.size || 0,
          totalBytes: parseInt(uploadResult.file_size_bytes, 10) || file.size || 0,
          speedBytesPerSec: 0,
          etaSeconds: 0,
          retryAttempt: 0,
          maxRetries: parseInt($form.data('uploadMaxRetries'), 10) || 3,
          active: false,
          paused: false
        });
      }

      var persistResponse = await persistMediaMutation($form, shouldDirectOneDriveUpload);
      mediaDebugLog($form, 'persist.response', persistResponse);

      if (!persistResponse || persistResponse.ok !== true) {
        renderErrors(errorKey, (persistResponse && persistResponse.message) || 'Falha ao salvar midia.', persistResponse && persistResponse.errors);
        setMediaProgress($form, 'Failed', 100, 0, 0, {
          uploadedBytes: 0,
          totalBytes: hasVideo ? (videoInput.files[0].size || 0) : 0,
          speedBytesPerSec: 0,
          etaSeconds: 0,
          retryAttempt: 0,
          maxRetries: parseInt($form.data('uploadMaxRetries'), 10) || 3,
          active: false,
          paused: false
        });
        $form.find('input[name="upload_status"]').val('FAILED');
        sweetError((persistResponse && persistResponse.message) || 'Falha ao salvar midia.');
        return;
      }

      if (persistResponse.upload) {
        applyMediaUploadResult($form, persistResponse);
      }
      setMediaProgress($form, 'Completed', 100, 1, 1, {
        uploadedBytes: parseInt($('#' + (formId === 'media-create-form' ? 'm-c' : 'm-e') + '-file-size-bytes').val(), 10) || (hasVideo ? (videoInput.files[0].size || 0) : 0),
        totalBytes: parseInt($('#' + (formId === 'media-create-form' ? 'm-c' : 'm-e') + '-file-size-bytes').val(), 10) || (hasVideo ? (videoInput.files[0].size || 0) : 0),
        speedBytesPerSec: 0,
        etaSeconds: 0,
        retryAttempt: 0,
        maxRetries: parseInt($form.data('uploadMaxRetries'), 10) || 3,
        active: false,
        paused: false
      });
      $form.find('input[name="upload_status"]').val('READY');

      closeFormModal($form);
      swalFire({
        icon: 'success',
        title: 'Sucesso',
        text: persistResponse.message || 'Midia salva com sucesso.'
      }).then(function () {
        window.location.reload();
      });
    } catch (error) {
      var xhr = error && error.responseJSON !== undefined ? error : null;
      var message = 'Falha no upload da midia.';
      var errors = {};

      if (xhr) {
        var json = xhr.responseJSON || {};
        message = json.message || message;
        errors = json.errors || {};
      } else if (error && error.message) {
        message = String(error.message);
      }

      if (message.toLowerCase().indexOf('cancelado') >= 0 && state.uploadUrl) {
        await cancelUploadSession(state.uploadUrl);
        if (state.checkpointKey) {
          clearUploadCheckpoint(state.checkpointKey);
        }
      }

      mediaDebugLog($form, 'submit.failed', {
        message: message,
        error: error
      });
      renderErrors(errorKey, message, errors);
      setMediaProgress($form, 'Failed', 100, 0, 0, {
        uploadedBytes: hasVideo ? (videoInput.files[0].size || 0) : 0,
        totalBytes: hasVideo ? (videoInput.files[0].size || 0) : 0,
        speedBytesPerSec: 0,
        etaSeconds: 0,
        retryAttempt: 0,
        maxRetries: parseInt($form.data('uploadMaxRetries'), 10) || 3,
        active: false,
        paused: false
      });
      $form.find('input[name="upload_status"]').val('FAILED');
      sweetError(message);
    } finally {
      state.active = false;
      state.paused = false;
      state.cancelRequested = false;
      state.abortController = null;
      state.checkpointKey = '';
      state.submitLocked = false;
      $submitButton.prop('disabled', false);
    }
  }

  function bindMediaModule() {
    $(document).on('click', '.js-media-tree-title-toggle', function (event) {
      event.preventDefault();
      var $node = $(this).closest('.wp-media-tree-node-title');
      var $children = $node.children('.wp-media-tree-children');
      var isOpen = $node.hasClass('is-open');
      var $siblings = $node.siblings('.wp-media-tree-node-title');

      $siblings.removeClass('is-open');
      $siblings.children('.wp-media-tree-children').stop(true, true).slideUp(180);
      $siblings.find('.wp-media-tree-node-season').removeClass('is-open');
      $siblings.find('.wp-media-tree-node-season > .wp-media-tree-children').hide();
      $siblings.find('.js-media-tree-title-toggle').attr('aria-expanded', 'false');
      $siblings.find('.js-media-tree-season-toggle').attr('aria-expanded', 'false');

      if (isOpen) {
        $node.removeClass('is-open');
        $children.stop(true, true).slideUp(180);
        $(this).attr('aria-expanded', 'false');
        return;
      }

      $node.addClass('is-open');
      $children.stop(true, true).slideDown(180);
      $(this).attr('aria-expanded', 'true');
    });

    $(document).on('click', '.js-media-tree-season-toggle', function (event) {
      event.preventDefault();
      var $node = $(this).closest('.wp-media-tree-node-season');
      var $children = $node.children('.wp-media-tree-children');
      var isOpen = $node.hasClass('is-open');
      var $siblings = $node.siblings('.wp-media-tree-node-season');

      $siblings.removeClass('is-open');
      $siblings.children('.wp-media-tree-children').stop(true, true).slideUp(160);
      $siblings.find('.js-media-tree-season-toggle').attr('aria-expanded', 'false');

      if (isOpen) {
        $node.removeClass('is-open');
        $children.stop(true, true).slideUp(160);
        $(this).attr('aria-expanded', 'false');
        return;
      }

      $node.addClass('is-open');
      $children.stop(true, true).slideDown(160);
      $(this).attr('aria-expanded', 'true');
    });

    $(document).on('change', '.js-media-title, .js-media-season, .js-media-episode, #m-c-source-type, #m-e-source-type', function () {
      syncMediaFields($(this).closest('form'));
    });

    $(document).on('change', '#media-create-form input[type=\"file\"], #media-edit-form input[type=\"file\"]', function () {
      updateMediaFilePreview($(this).closest('form'));
    });

    $(document).on('click', '.js-media-upload-pause', function (event) {
      event.preventDefault();
      var selector = String($(this).data('form') || '');
      if (!selector) {
        return;
      }
      var $form = $(selector);
      if (!$form.length) {
        return;
      }
      var state = mediaUploadState($form);
      if (!state.active) {
        return;
      }
      state.paused = true;
      var last = state.lastMeta || {};
      setMediaProgress($form, 'Paused', state.lastPercent || 0, state.lastChunk || 0, state.lastTotalChunks || 0, {
        uploadedBytes: parseInt(last.uploadedBytes, 10) || 0,
        totalBytes: parseInt(last.totalBytes, 10) || 0,
        speedBytesPerSec: Number(last.speedBytesPerSec) || 0,
        etaSeconds: Number.isFinite(Number(last.etaSeconds)) ? Number(last.etaSeconds) : NaN,
        retryAttempt: parseInt(last.retryAttempt, 10) || 0,
        maxRetries: parseInt(last.maxRetries, 10) || (parseInt($form.data('uploadMaxRetries'), 10) || 3),
        active: true,
        paused: true
      });
    });

    $(document).on('click', '.js-media-upload-resume', function (event) {
      event.preventDefault();
      var selector = String($(this).data('form') || '');
      if (!selector) {
        return;
      }
      var $form = $(selector);
      if (!$form.length) {
        return;
      }
      var state = mediaUploadState($form);
      if (!state.active) {
        return;
      }
      state.paused = false;
      updateMediaProgressControls($form, { active: true, paused: false });
    });

    $(document).on('click', '.js-media-upload-cancel', function (event) {
      event.preventDefault();
      var selector = String($(this).data('form') || '');
      if (!selector) {
        return;
      }
      var $form = $(selector);
      if (!$form.length) {
        return;
      }
      var state = mediaUploadState($form);
      if (!state.active) {
        return;
      }
      state.cancelRequested = true;
      state.paused = false;
      if (state.abortController) {
        state.abortController.abort();
      }
      var last = state.lastMeta || {};
      setMediaProgress($form, 'Canceling', state.lastPercent || 0, state.lastChunk || 0, state.lastTotalChunks || 0, {
        uploadedBytes: parseInt(last.uploadedBytes, 10) || 0,
        totalBytes: parseInt(last.totalBytes, 10) || 0,
        speedBytesPerSec: 0,
        etaSeconds: NaN,
        retryAttempt: parseInt(last.retryAttempt, 10) || 0,
        maxRetries: parseInt(last.maxRetries, 10) || (parseInt($form.data('uploadMaxRetries'), 10) || 3),
        active: true,
        paused: false
      });
    });

    $('#media-create-modal, #media-edit-modal').on('shown.bs.modal', function () {
      var $form = $(this).find('form').first();
      if ($form.length) {
        syncMediaFields($form);
        updateMediaFilePreview($form);
        resetMediaProgress($form);
      }
    });

    $('#media-create-modal, #media-edit-modal').on('hidden.bs.modal', function () {
      var $form = $(this).find('form').first();
      if ($form.length) {
        var state = mediaUploadState($form);
        if (state.active) {
          state.cancelRequested = true;
          state.paused = false;
          if (state.abortController) {
            state.abortController.abort();
          }
          return;
        }
        resetMediaProgress($form);
      }
    });

    $(document).on('submit', 'form[data-media-upload=\"1\"]', function (event) {
      event.preventDefault();
      submitMediaUploadForm($(this));
    });

    $(document).on('click', '.js-edit-media', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-media'));
      if (!item) {
        return;
      }

      $('#m-e-id').val(item.id || '');
      $('#m-e-title-id').val(item.title_id || '');
      $('#m-e-season').val(item.season_number || 0);
      $('#m-e-episode').val(item.episode_number || 0);
      $('#m-e-label').val(item.item_label || '');
      $('#m-e-duration').val(item.duration_minutes || '');
      $('#m-e-source-type').val(item.source_type || 'ONEDRIVE');
      $('#m-e-source-path').val(item.source_path || '');
      $('#m-e-external-id').val(item.external_id || '');
      $('#m-e-stream-ref').val(item.stream_ref || '');
      $('#m-e-stream-ref-view').val(item.stream_ref || '');
      $('#m-e-stream-url').val(item.stream_url || '');
      $('#m-e-download-url').val(item.download_url || '');
      $('#m-e-drive-id').val(item.drive_id || '');
      $('#m-e-parent-item-id').val(item.parent_item_id || '');
      $('#m-e-mime-type').val(item.mime_type || '');
      $('#m-e-file-size-bytes').val(item.file_size_bytes || 0);
      $('#m-e-upload-status-field').val(item.upload_status || 'READY');
      $('#m-e-subtitles').val(item.subtitles || '');
      $('#m-e-audio-lang').val(item.audio_lang || '');
      $('#m-e-published').val(item.is_published || 1);
      $('#m-e-thumbnail-url').val(item.thumbnail_url || '');
      $('#m-e-backdrop-url').val(item.backdrop_url || '');
      $('#media-edit-form').attr('data-endpoint', '/admin/media/update');
      $('#media-edit-modal .modal-title').text('Editar midia');

      clearErrors('media-edit');
      openModal('#media-edit-modal');
      syncMediaFields($('#media-edit-form'));
      updateMediaFilePreview($('#media-edit-form'));
      resetMediaProgress($('#media-edit-form'));
    });

    $(document).on('click', '.js-replace-media-source', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-media'));
      if (!item) {
        return;
      }
      $('#m-e-id').val(item.id || '');
      $('#m-e-title-id').val(item.title_id || '');
      $('#m-e-season').val(item.season_number || 0);
      $('#m-e-episode').val(item.episode_number || 0);
      $('#m-e-label').val(item.item_label || '');
      $('#m-e-duration').val(item.duration_minutes || '');
      $('#m-e-source-type').val('ONEDRIVE');
      $('#m-e-source-path').val(item.source_path || '');
      $('#m-e-external-id').val(item.external_id || '');
      $('#m-e-stream-ref').val(item.stream_ref || '');
      $('#m-e-stream-ref-view').val(item.stream_ref || '');
      $('#m-e-stream-url').val(item.stream_url || '');
      $('#m-e-download-url').val(item.download_url || '');
      $('#m-e-drive-id').val(item.drive_id || '');
      $('#m-e-parent-item-id').val(item.parent_item_id || '');
      $('#m-e-mime-type').val(item.mime_type || '');
      $('#m-e-file-size-bytes').val(item.file_size_bytes || 0);
      $('#m-e-upload-status-field').val(item.upload_status || 'READY');
      $('#m-e-subtitles').val(item.subtitles || '');
      $('#m-e-audio-lang').val(item.audio_lang || '');
      $('#m-e-published').val(item.is_published || 1);
      $('#m-e-thumbnail-url').val(item.thumbnail_url || '');
      $('#m-e-backdrop-url').val(item.backdrop_url || '');
      $('#media-edit-form').attr('data-endpoint', '/admin/media/replace-source');
      $('#media-edit-modal .modal-title').text('Substituir video da midia');

      clearErrors('media-edit');
      openModal('#media-edit-modal');
      syncMediaFields($('#media-edit-form'));
      updateMediaFilePreview($('#media-edit-form'));
      resetMediaProgress($('#media-edit-form'));

      setTimeout(function () {
        $('#media-edit-form input[name=\"video_file\"]').trigger('click');
      }, 250);
    });

    $(document).on('click', '.js-delete-media', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'midia');
      if (id <= 0) {
        return;
      }

      swalFire({
        icon: 'warning',
        title: 'Excluir midia',
        text: 'Escolha se deseja remover tambem os arquivos no OneDrive.',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Excluir DB + OneDrive',
        denyButtonText: 'Excluir so DB',
        cancelButtonText: 'Cancelar',
        customClass: {
          confirmButton: 'btn btn-semantic-danger',
          denyButton: 'btn btn-semantic-warning',
          cancelButton: 'btn btn-semantic-neutral'
        }
      }).then(function (result) {
        if (!result.isConfirmed && !result.isDenied) {
          return;
        }

        var deleteSource = result.isConfirmed ? 1 : 0;
        request('/admin/media/delete', 'POST', $.param({
          id: id,
          delete_source: deleteSource,
          _csrf: csrfToken()
        }), false)
          .done(function (response) {
            if (!response || response.ok !== true) {
              sweetError((response && response.message) || 'Falha ao excluir midia.');
              return;
            }
            swalFire({
              icon: 'success',
              title: 'Excluida',
              text: response.message || ('Midia \"' + name + '\" excluida.')
            }).then(function () {
              window.location.reload();
            });
          })
          .fail(function (xhr) {
            var json = xhr.responseJSON || {};
            sweetError(json.message || 'Falha ao excluir midia.');
          });
      });
    });
  }

  function bindBillingModule() {
    $(document).on('click', '.js-edit-plan', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-plan'));
      if (!item) {
        return;
      }

      $('#plan-e-id').val(item.id || '');
      $('#plan-e-name').val(item.plan_name || '');
      $('#plan-e-slug').val(item.slug || '');
      $('#plan-e-price').val(item.price || '');
      $('#plan-e-currency').val(item.currency || 'BRL');
      $('#plan-e-cycle').val(item.billing_cycle || 'MONTHLY');
      $('#plan-e-profile-limit').val(item.profile_limit || 1);
      $('#plan-e-max-streams').val(item.max_streams || 1);
      $('#plan-e-quality').val(item.quality_level || '1080p');
      $('#plan-e-active').val(typeof item.active !== 'undefined' ? item.active : 1);
      $('#plan-e-selectable').val(typeof item.is_selectable !== 'undefined' ? item.is_selectable : 1);
      $('#plan-e-requires-payment').val(typeof item.requires_payment_method !== 'undefined' ? item.requires_payment_method : 0);
      $('#plan-e-sort-order').val(typeof item.sort_order !== 'undefined' ? item.sort_order : 100);

      clearErrors('plan-edit');
      openModal('#plan-edit-modal');
    });

    $(document).on('click', '.js-delete-plan', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'plano');
      if (id <= 0) {
        return;
      }
      deleteByEndpoint('/admin/billing/plans/delete', id, name, 'o plano');
    });
  }

  function updateOneDriveQuotaWidgets(view) {
    if (!view || typeof view !== 'object') {
      return;
    }

    var badgeClasses = 'bg-label-success bg-label-warning bg-label-danger bg-label-secondary bg-label-info';
    var barClasses = 'bg-success bg-warning bg-danger bg-secondary bg-info';
    var percentage = parseFloat(view.percentage);
    if (!Number.isFinite(percentage)) {
      percentage = 0;
    }
    percentage = Math.max(0, Math.min(100, percentage));

    $('[data-onedrive-quota-widget]').each(function () {
      var $widget = $(this);

      $widget.find('[data-quota-field="state_label"]').text(String(view.state_label || 'Unknown'));

      var $badge = $widget.find('[data-quota-field="state_badge"]');
      $badge.removeClass(badgeClasses).addClass(String(view.state_badge_class || 'bg-label-secondary'));

      $widget.find('[data-quota-field="used_human"]').text(String(view.used_human || '-'));
      $widget.find('[data-quota-field="used_human_secondary"]').text(String(view.used_human || '-'));
      $widget.find('[data-quota-field="total_human"]').text(String(view.total_human || '-'));
      $widget.find('[data-quota-field="remaining_human"]').text(String(view.remaining_human || '-'));
      $widget.find('[data-quota-field="percentage_label"]').text(String(view.percentage_label || '0.0%'));
      $widget.find('[data-quota-field="plan_hint"]').text(String(view.plan_hint || ''));
      $widget.find('[data-quota-field="message"]').text(String(view.message || ''));
      $widget.find('[data-quota-field="last_updated"]').text(String(view.last_updated || '-'));

      var $bar = $widget.find('[data-quota-field="progress_bar"]');
      $bar.removeClass(barClasses).addClass(String(view.progress_bar_class || 'bg-secondary'));
      $bar.css('width', percentage.toFixed(1) + '%');
      $bar.attr('aria-valuenow', percentage.toFixed(1));
    });
  }

  function bindSettingsModule() {
    $(document).on('click', '.js-view-setting', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-setting'));
      if (!item) {
        return;
      }

      $('#s-v-category').val(item.category || 'general');
      $('#s-v-key-name').val(item.key_name || '');
      $('#s-v-value-text').val(item.value_text || '');
      $('#s-v-is-secret').val((item.is_secret || 0) === 1 ? 'Sim' : 'Nao');

      openModal('#setting-view-modal');
    });

    $(document).on('click', '.js-edit-setting', function (event) {
      event.preventDefault();
      var item = parsePayload($(this).attr('data-setting'));
      if (!item) {
        return;
      }

      $('#s-e-id').val(item.id || '');
      $('#s-e-category').val(item.category || 'general');
      $('#s-e-key-name').val(item.key_name || '');
      $('#s-e-value-text').val(item.value_text || '');
      $('#s-e-is-secret').val(item.is_secret || 0);

      clearErrors('setting-edit');
      openModal('#setting-edit-modal');
    });

    $(document).on('click', '.js-delete-setting', function (event) {
      event.preventDefault();
      var id = parseInt($(this).data('id'), 10) || 0;
      var name = String($(this).data('name') || 'chave');
      if (id <= 0) {
        return;
      }
      deleteByEndpoint('/admin/settings/delete', id, name, 'a configuracao');
    });

    $(document).on('click', '.js-onedrive-test', function (event) {
      event.preventDefault();
      if ($(this).is(':disabled')) {
        return;
      }

      request('/admin/settings/onedrive/test', 'POST', $.param({ _csrf: csrfToken() }), false)
        .done(function (response) {
          if (!response || response.ok !== true) {
            sweetError((response && response.message) || 'Falha no teste OneDrive.');
            return;
          }

          swalFire({
            icon: 'success',
            title: 'Conexao OK',
            text: response.message || 'Endpoint OneDrive acessivel.'
          });
        })
        .fail(function (xhr) {
          var json = xhr.responseJSON || {};
          sweetError(json.message || 'Falha no teste OneDrive.');
        });
    });

    $(document).on('click', '.js-onedrive-quota-refresh', function (event) {
      event.preventDefault();
      var $button = $(this);
      if ($button.is(':disabled')) {
        return;
      }

      $button.prop('disabled', true);

      request('/admin/settings/onedrive/quota', 'POST', $.param({
        _csrf: csrfToken(),
        force: 1
      }), false)
        .done(function (response) {
          if (response && response.quota) {
            updateOneDriveQuotaWidgets(response.quota);
          }

          if (!response || response.ok !== true) {
            sweetError((response && response.message) || 'Falha ao atualizar quota OneDrive.');
            return;
          }

          swalFire({
            icon: 'success',
            title: 'Quota atualizado',
            text: response.message || 'Quota OneDrive atualizado com sucesso.'
          });
        })
        .fail(function (xhr) {
          var json = xhr.responseJSON || {};
          sweetError(json.message || 'Falha ao atualizar quota OneDrive.');
        })
        .always(function () {
          $button.prop('disabled', false);
        });
    });
  }

  $(function () {
    if (typeof Swal === 'undefined') {
      return;
    }

    bindActionsDropdowns();
    bindAdminChrome();
    bindAdminUsersBulkActions();
    decorateAllModals();
    applySemanticButtons();
    bindImagePreviewUX();
    bindGenericFormSubmit();
    bindUsersModule();
    bindProfilesModule();
    bindTitlesModule();
    bindMediaModule();
    bindBillingModule();
    bindSettingsModule();
    refreshFormImagePreviews($('form'));
  });
})(jQuery);
