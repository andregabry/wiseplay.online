<?php
$profile = $profile ?? null;
$current = current_path();
$isKids = ((int) ($profile['is_kids'] ?? 0) === 1);
?>
<header class="wp-nav">
  <div class="wp-nav-left">
    <a class="wp-logo" href="/browse" aria-label="WisePlay">WISEPLAY</a>
    <nav class="wp-links" aria-label="Navegacao principal">
      <a href="/browse" class="<?= $current === '/browse' ? 'active' : '' ?>">Inicio</a>
      <a href="/profiles" class="<?= $current === '/profiles' ? 'active' : '' ?>">Perfis</a>
    </nav>
  </div>

  <div class="wp-nav-right">
    <?php if ($profile): ?>
      <div class="wp-profile-pill">
        <img src="<?= e((string) (($profile['avatar_path'] ?? '') !== '' ? $profile['avatar_path'] : '/assets/img/avatar-library/avatar-01.svg')) ?>" alt="Avatar do perfil" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
        <span><?= e((string) ($profile['profile_name'] ?? 'Perfil')) ?></span>
        <?php if ($isKids): ?><span class="kids-dot">KIDS</span><?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/logout" class="wp-logout-form">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <button type="submit" class="wp-btn ghost">Sair</button>
    </form>
  </div>
</header>
