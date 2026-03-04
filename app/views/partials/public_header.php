<?php
$profile = $profile ?? null;
$availableProfiles = $availableProfiles ?? ($profile ? [$profile] : []);
$selectedProfileId = (int) ($selectedProfileId ?? (int) ($profile['id'] ?? 0));
$current = current_path();
$browseTargetPrefix = $current === '/browse' ? '' : '/browse';
$isKids = ((int) ($profile['is_kids'] ?? 0) === 1);
$avatarFallback = (new \App\services\AvatarLibraryService())->defaultProfileAvatar();
$profileAvatar = trim((string) ($profile['avatar_path'] ?? ''));
if ($profileAvatar === '' || str_ends_with($profileAvatar, '.gitkeep')) {
    $profileAvatar = $avatarFallback;
}
?>
<header class="wp-stream-header js-stream-header">
  <div class="wp-stream-header__inner">
    <div class="wp-stream-header__left">
      <a class="wp-stream-brand" href="/browse" aria-label="WisePlay">WISEPLAY</a>
      <nav class="wp-stream-nav" aria-label="Navegacao principal">
        <a href="/browse" class="<?= $current === '/browse' ? 'active' : '' ?>">Inicio</a>
        <a href="<?= e($browseTargetPrefix) ?>#row-series" class="js-browse-jump">Series</a>
        <a href="<?= e($browseTargetPrefix) ?>#row-movie" class="js-browse-jump">Filmes</a>
        <button type="button" class="wp-stream-link-button js-public-coming-soon" data-label="Jogos">Jogos</button>
        <button type="button" class="wp-stream-link-button js-public-coming-soon" data-label="Bombando">Bombando</button>
        <button type="button" class="wp-stream-link-button js-public-coming-soon" data-label="Minha lista">Minha lista</button>
        <button type="button" class="wp-stream-link-button js-public-coming-soon" data-label="Navegar por idiomas">Navegar por idiomas</button>
      </nav>
    </div>

    <div class="wp-stream-header__right">
      <button type="button" class="wp-stream-icon-btn js-public-coming-soon" data-label="Busca" aria-label="Buscar">
        <span class="wp-stream-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M15.5 14h-.79l-.28-.27a6 6 0 1 0-.71.71l.27.28v.79L20 21.49 21.49 20l-5.99-6zm-5.5 0A4.5 4.5 0 1 1 10 5a4.5 4.5 0 0 1 0 9z"></path>
          </svg>
        </span>
      </button>
      <button type="button" class="wp-stream-kids-link<?= $isKids ? ' active' : '' ?> js-public-coming-soon" data-label="Modo infantil">
        Infantil
      </button>
      <button type="button" class="wp-stream-icon-btn js-public-coming-soon has-badge" data-label="Notificacoes" aria-label="Notificacoes">
        <span class="wp-stream-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M12 22a2.2 2.2 0 0 0 2.2-2.2h-4.4A2.2 2.2 0 0 0 12 22zm6.4-6.1v-4.5c0-3.4-1.8-6.2-5.1-6.9v-.7a1.3 1.3 0 1 0-2.6 0v.7C7.4 5.2 5.6 8 5.6 11.4v4.5L4 17.5v1.2h16v-1.2l-1.6-1.6z"></path>
          </svg>
        </span>
        <span class="wp-stream-badge">3</span>
      </button>

      <div class="wp-stream-user js-stream-user">
        <button type="button" class="wp-stream-user-trigger js-stream-user-trigger" aria-haspopup="true" aria-expanded="false">
          <img src="<?= e($profileAvatar) ?>" alt="Avatar do perfil" onerror="this.src='<?= e($avatarFallback) ?>'">
          <span class="wp-stream-caret"></span>
        </button>

        <div class="wp-stream-user-menu" hidden>
          <div class="wp-stream-user-menu__profiles">
            <?php foreach ($availableProfiles as $menuProfile): ?>
              <?php
                $menuProfileId = (int) ($menuProfile['id'] ?? 0);
                $menuAvatar = trim((string) ($menuProfile['avatar_path'] ?? ''));
                if ($menuAvatar === '' || str_ends_with($menuAvatar, '.gitkeep')) {
                    $menuAvatar = $avatarFallback;
                }
              ?>
              <form method="post" action="/profiles/select" class="wp-stream-profile-switch">
                <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
                <input type="hidden" name="profile_id" value="<?= $menuProfileId ?>">
                <button type="submit" class="wp-stream-profile-switch__button<?= $menuProfileId === $selectedProfileId ? ' active' : '' ?>">
                  <img src="<?= e($menuAvatar) ?>" alt="Avatar do perfil" onerror="this.src='<?= e($avatarFallback) ?>'">
                  <span><?= e((string) ($menuProfile['profile_name'] ?? 'Perfil')) ?></span>
                </button>
              </form>
            <?php endforeach; ?>
          </div>

          <div class="wp-stream-user-menu__links">
            <a href="/profiles?manage=1">Gerenciar perfis</a>
            <button type="button" class="js-public-coming-soon" data-label="Transferir perfil">Transferir perfil</button>
            <button type="button" class="js-public-coming-soon" data-label="Conta">Conta</button>
            <button type="button" class="js-public-coming-soon" data-label="Central de ajuda">Central de Ajuda</button>
          </div>

          <div class="wp-stream-user-menu__footer">
            <form method="post" action="/logout">
              <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
              <button type="submit">Sair da WisePlay</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>
