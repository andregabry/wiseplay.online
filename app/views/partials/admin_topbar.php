<?php
$avatar = trim((string) ($adminUser['account_avatar'] ?? ''));
if ($avatar === '' || str_ends_with($avatar, '.gitkeep')) {
    $avatar = '/assets/img/avatar-library/avatar-01.svg';
}
?>
<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme wp-admin-topbar">
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="icon-base ri ri-menu-line icon-22px"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <div class="navbar-nav align-items-center">
      <div class="nav-item d-flex align-items-center">
        <h4 class="fw-semibold wp-admin-page-title"><?= e($pageTitle ?? 'Admin') ?></h4>
      </div>
    </div>
    <ul class="navbar-nav flex-row align-items-center">
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            <img src="<?= e($avatar) ?>" alt="avatar" class="rounded-circle" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <div class="dropdown-item">
              <div class="d-flex flex-column">
                <h6 class="mb-0"><?= e((string) ($adminUser['name'] ?? 'Admin')) ?></h6>
                <small class="text-body-secondary"><?= e((string) ($adminUser['role'] ?? 'ADMIN')) ?></small>
              </div>
            </div>
          </li>
          <li><div class="dropdown-divider"></div></li>
          <li>
            <a class="dropdown-item" href="/admin/me">
              <i class="ri ri-user-settings-line me-2"></i>My Profile
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="/admin/settings">
              <i class="ri ri-settings-4-line me-2"></i>Settings
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
