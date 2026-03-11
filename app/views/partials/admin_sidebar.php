<?php
$role = (string) ($adminUser['role'] ?? '');

if ($role === 'PRODUCER') {
    $menu = [
        '/admin' => ['label' => 'Dashboard', 'icon' => 'ri-home-smile-line'],
        '/admin/producer/titles' => ['label' => 'My Titles', 'icon' => 'ri-store-3-line'],
        '/admin/producer/offers' => ['label' => 'My Offers', 'icon' => 'ri-price-tag-3-line'],
        '/admin/me' => ['label' => 'My Profile', 'icon' => 'ri-user-settings-line'],
    ];
} else {
    $menu = [
        '/admin' => ['label' => 'Dashboard', 'icon' => 'ri-home-smile-line'],
        '/admin/users' => ['label' => 'Users', 'icon' => 'ri-user-line'],
        '/admin/profiles' => ['label' => 'Profiles', 'icon' => 'ri-team-line'],
        '/admin/titles' => ['label' => 'Titles', 'icon' => 'ri-movie-2-line'],
        '/admin/media' => ['label' => 'Media', 'icon' => 'ri-film-line'],
        '/admin/billing' => ['label' => 'Billing', 'icon' => 'ri-bank-card-line'],
        '/admin/settings' => ['label' => 'Settings', 'icon' => 'ri-settings-4-line'],
        '/admin/me' => ['label' => 'My Profile', 'icon' => 'ri-user-settings-line'],
    ];
}
?>
<aside id="layout-menu" class="layout-menu menu-vertical menu">
  <div class="app-brand demo">
    <a href="/admin" class="app-brand-link">
      <span class="app-brand-logo demo"><i class="ri ri-tv-2-line icon-28px"></i></span>
      <span class="app-brand-text demo menu-text fw-semibold ms-2">WisePlay</span>
    </a>
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto" aria-label="Toggle sidebar">
      <i class="ri ri-close-line d-none d-xl-block align-middle"></i>
      <i class="ri ri-menu-line d-xl-none align-middle"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>
  <ul class="menu-inner py-1">
    <?php foreach ($menu as $path => $entry): ?>
    <?php $active = $path === '/admin' ? ($currentPath === '/admin') : ($currentPath === $path || str_starts_with($currentPath, $path . '/')); ?>
    <li class="menu-item <?= $active ? 'active' : '' ?>">
      <a href="<?= e($path) ?>" class="menu-link">
        <i class="menu-icon icon-base ri <?= e($entry['icon']) ?>"></i>
        <div><?= e($entry['label']) ?></div>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</aside>
