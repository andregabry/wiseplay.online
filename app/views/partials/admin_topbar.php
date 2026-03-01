<?php
$avatar = trim((string) ($adminUser['account_avatar'] ?? ''));
if ($avatar === '' || str_ends_with($avatar, '.gitkeep')) {
    $avatar = '/assets/img/avatar-library/avatar-01.svg';
}
$notifications = $adminNotifications ?? [];
$notificationCount = count($notifications);
?>
<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme wp-admin-topbar">
  <div class="wp-admin-topbar-search flex-grow-1">
    <i class="ri ri-search-line"></i>
    <input type="search" class="form-control js-admin-topbar-search" placeholder="Search [CTRL + K]" aria-label="Buscar no admin">
  </div>

  <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
    <ul class="navbar-nav flex-row align-items-center wp-admin-topbar-actions">
      <li class="nav-item dropdown">
        <a class="nav-link wp-admin-topbar-icon dropdown-toggle hide-arrow" href="javascript:void(0)" data-bs-toggle="dropdown" aria-label="Idiomas">
          <i class="ri ri-translate-2"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end wp-admin-topbar-menu">
          <div class="dropdown-header">Language</div>
          <button type="button" class="dropdown-item js-admin-lang-option" data-lang="en-US">English</button>
          <button type="button" class="dropdown-item js-admin-lang-option" data-lang="pt-BR">Portugues (Brasil)</button>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link wp-admin-topbar-icon js-admin-theme-toggle" href="javascript:void(0)" aria-label="Tema">
          <i class="ri ri-sun-line"></i>
        </a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link wp-admin-topbar-icon dropdown-toggle hide-arrow" href="javascript:void(0)" data-bs-toggle="dropdown" aria-label="Favoritos">
          <i class="ri ri-star-line"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end wp-admin-topbar-menu wp-admin-topbar-menu-wide">
          <div class="dropdown-header d-flex align-items-center justify-content-between gap-2">
            <span>Favorites</span>
            <button type="button" class="btn btn-sm btn-outline-primary js-admin-favorite-toggle">Save current</button>
          </div>
          <div class="wp-admin-favorites-list js-admin-favorites-list">
            <div class="dropdown-item-text text-body-secondary small">No saved pages yet.</div>
          </div>
        </div>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link wp-admin-topbar-icon dropdown-toggle hide-arrow position-relative" href="javascript:void(0)" data-bs-toggle="dropdown" aria-label="Notificacoes">
          <i class="ri ri-notification-3-line"></i>
          <?php if ($notificationCount > 0): ?>
            <span class="badge rounded-pill bg-danger wp-admin-topbar-badge"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end wp-admin-topbar-menu wp-admin-topbar-menu-wide">
          <div class="dropdown-header">Recent activity</div>
          <?php if ($notifications === []): ?>
            <div class="dropdown-item-text text-body-secondary small">No recent notifications.</div>
          <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
              <div class="dropdown-item-text wp-admin-notification-item">
                <div class="fw-medium"><?= e((string) ($notification['action'] ?? 'event')) ?></div>
                <div class="small text-body-secondary">
                  <?= e((string) ($notification['actor_name'] ?? 'System')) ?> • <?= e((string) ($notification['entity_type'] ?? '-')) ?>
                </div>
                <div class="small text-body-secondary"><?= e((string) ($notification['created_at'] ?? '')) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link wp-admin-topbar-icon wp-admin-topbar-accent js-admin-open-customizer" href="javascript:void(0)" aria-label="Personalizar layout">
          <i class="ri ri-settings-5-line"></i>
        </a>
      </li>
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown" aria-label="Abrir menu do usuario">
          <div class="avatar avatar-online">
            <img src="<?= e($avatar) ?>" alt="avatar" class="rounded-circle" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <div class="dropdown-item">
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm">
                  <img src="<?= e($avatar) ?>" alt="avatar" class="rounded-circle" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
                </div>
                <div class="d-flex flex-column">
                <h6 class="mb-0"><?= e((string) ($adminUser['name'] ?? 'Admin')) ?></h6>
                <small class="text-body-secondary"><?= e((string) ($adminUser['role'] ?? 'ADMIN')) ?></small>
                </div>
              </div>
            </div>
          </li>
          <li><div class="dropdown-divider"></div></li>
          <li>
            <a class="dropdown-item" href="/admin/me">
              <i class="ri ri-user-settings-line me-2"></i>Meu perfil
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="/admin/settings">
              <i class="ri ri-settings-4-line me-2"></i>Configuracoes
            </a>
          </li>
          <li><div class="dropdown-divider"></div></li>
          <li>
            <form method="post" action="/admin/logout" class="px-3 py-1">
              <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
              <button type="submit" class="btn btn-sm btn-danger w-100 btn-semantic-danger">
                <i class="ri ri-logout-box-r-line me-1"></i>Sair
              </button>
            </form>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
