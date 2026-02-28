<?php
$profiles = $profiles ?? [];
$selectedProfileId = (int) ($selectedProfileId ?? 0);
$manageMode = $manageMode ?? false;
$plan = $plan ?? null;
$planLimit = (int) ($planLimit ?? 0);
$currentCount = (int) ($currentCount ?? count($profiles));
$canCreateProfile = $canCreateProfile ?? false;
$avatarOptions = $avatarOptions ?? [];
$onboardingNeedsProfile = $onboardingNeedsProfile ?? false;

$avatarFallback = '/assets/img/avatar-library/avatar-01.svg';
$avatarOrFallback = static function (?string $value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '' || str_ends_with($value, '.gitkeep')) {
        return $fallback;
    }
    return $value;
};
?>

<main class="wp-profiles-page">
  <a class="wp-logo profile-logo" href="/profiles">WISEPLAY</a>

  <section class="wp-profiles-content">
    <header class="wp-profiles-stage">
      <h1>Quem est&aacute; assistindo?</h1>
      <p>
        <?= $manageMode ? 'Escolha, edite ou crie perfis para continuar.' : 'Selecione um perfil para entrar no WisePlay.' ?>
      </p>
      <div class="wp-profiles-stage-meta">
        <span><?= e((string) ($plan['plan_name'] ?? 'Sem plano')) ?></span>
        <small><?= $currentCount ?>/<?= max(0, $planLimit) ?> perfis</small>
      </div>
    </header>

    <?php if ($onboardingNeedsProfile): ?>
      <div class="wp-onboarding-note wp-profiles-note">
        Seu primeiro acesso precisa de pelo menos um perfil selecionado.
      </div>
    <?php endif; ?>

    <section class="wp-profile-grid wp-profile-grid-stage">
      <?php foreach ($profiles as $profile): ?>
        <?php
          $profileId = (int) $profile['id'];
          $active = $selectedProfileId === $profileId;
          $isKids = ((int) ($profile['is_kids'] ?? 0) === 1);
          $avatar = $avatarOrFallback((string) ($profile['avatar_path'] ?? ''), $avatarFallback);
          $payload = json_encode([
              'id' => $profileId,
              'profile_name' => (string) ($profile['profile_name'] ?? ''),
              'avatar_path' => $avatar,
              'is_kids' => $isKids ? 1 : 0,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <article class="wp-profile-tile <?= $active ? 'active' : '' ?>">
          <form method="post" action="/profiles/select" class="wp-profile-form">
            <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
            <input type="hidden" name="profile_id" value="<?= $profileId ?>">
            <button type="submit" class="wp-profile-btn wp-profile-stage-btn">
              <span class="wp-profile-avatar-frame">
                <img src="<?= e($avatar) ?>" alt="Avatar de <?= e((string) $profile['profile_name']) ?>" onerror="this.src='<?= e($avatarFallback) ?>'">
                <?php if ($isKids): ?><small>KIDS</small><?php endif; ?>
              </span>
              <span class="wp-profile-name"><?= e((string) ($profile['profile_name'] ?? 'Perfil')) ?></span>
            </button>
          </form>

          <?php if ($manageMode): ?>
            <div class="wp-profile-admin-actions wp-profile-stage-actions">
              <button type="button" class="wp-btn tiny ghost js-edit-public-profile" data-profile='<?= e($payload) ?>'>Editar</button>
              <form method="post" action="/profiles/delete" class="js-public-delete-profile-form">
                <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
                <input type="hidden" name="profile_id" value="<?= $profileId ?>">
                <button type="submit" class="wp-btn tiny ghost js-delete-public-profile" data-name="<?= e((string) ($profile['profile_name'] ?? 'perfil')) ?>">Excluir</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <article class="wp-profile-tile wp-profile-tile-add <?= $canCreateProfile ? '' : 'disabled' ?>">
        <a
          href="<?= $canCreateProfile ? '/profiles?manage=1#create-profile-studio' : '#' ?>"
          class="wp-profile-btn wp-profile-add-card js-public-create-profile"
          data-limit-reached="<?= $canCreateProfile ? '0' : '1' ?>"
          data-limit-value="<?= max(0, $planLimit) ?>">
          <span class="wp-profile-add-circle">
            <span class="wp-profile-add-plus">+</span>
          </span>
          <span class="wp-profile-name">Adicionar perfil</span>
        </a>
      </article>
    </section>

    <div class="wp-profiles-bottom-actions">
      <?php if ($manageMode): ?>
        <a href="/profiles" class="wp-profiles-manage-btn">Concluir gerenciamento</a>
      <?php else: ?>
        <a href="/profiles?manage=1" class="wp-profiles-manage-btn">Gerenciar perfis</a>
      <?php endif; ?>
    </div>

    <?php if ($manageMode): ?>
      <section class="wp-profile-manage-grid">
        <div class="wp-manage-card" id="create-profile-studio">
          <h2>Criar perfil</h2>
          <p>Novo perfil adulto ou kids. Limite do plano: <?= max(0, $planLimit) ?>.</p>

          <form method="post" action="/profiles/create" enctype="multipart/form-data" id="public-profile-create-form">
            <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
            <input type="hidden" name="avatar_path" id="public-create-avatar" value="<?= e($avatarFallback) ?>">

            <label class="wp-form-label">Nome do perfil</label>
            <input class="wp-form-input" name="profile_name" required minlength="2">

            <label class="wp-form-label">Tipo</label>
            <select class="wp-form-input" name="is_kids">
              <option value="0">Adulto</option>
              <option value="1">Kids</option>
            </select>

            <label class="wp-form-label">Upload avatar (opcional)</label>
            <input class="wp-form-input" type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp">

            <label class="wp-form-label">Avatares da biblioteca</label>
            <div class="wp-avatar-library">
              <?php foreach ($avatarOptions as $avatarOption): ?>
                <button type="button" class="wp-avatar-option js-avatar-option" data-target="#public-create-avatar" data-preview="#public-create-avatar-preview" data-value="<?= e((string) $avatarOption) ?>">
                  <img src="<?= e((string) $avatarOption) ?>" alt="avatar" loading="lazy">
                </button>
              <?php endforeach; ?>
            </div>

            <div class="wp-avatar-preview-row">
              <span>Selecionado:</span>
              <img id="public-create-avatar-preview" src="<?= e($avatarFallback) ?>" alt="avatar selecionado">
            </div>

            <button
              type="submit"
              class="wp-btn danger js-public-create-profile"
              data-limit-reached="<?= $canCreateProfile ? '0' : '1' ?>"
              data-limit-value="<?= max(0, $planLimit) ?>">
              Criar perfil
            </button>
          </form>
        </div>

        <div class="wp-manage-card" id="edit-profile-studio">
          <h2>Editar perfil</h2>
          <p>Selecione um perfil acima para editar nome, tipo e avatar.</p>

          <form method="post" action="/profiles/update" enctype="multipart/form-data" id="public-profile-edit-form">
            <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
            <input type="hidden" name="profile_id" id="public-edit-profile-id" value="">
            <input type="hidden" name="avatar_path" id="public-edit-avatar" value="<?= e($avatarFallback) ?>">

            <label class="wp-form-label">Nome do perfil</label>
            <input class="wp-form-input" name="profile_name" id="public-edit-profile-name" required minlength="2" placeholder="Escolha um perfil para editar">

            <label class="wp-form-label">Tipo</label>
            <select class="wp-form-input" name="is_kids" id="public-edit-is-kids">
              <option value="0">Adulto</option>
              <option value="1">Kids</option>
            </select>

            <label class="wp-form-label">Upload avatar (opcional)</label>
            <input class="wp-form-input" type="file" name="avatar_file" accept="image/png,image/jpeg,image/webp">

            <label class="wp-form-label">Avatares da biblioteca</label>
            <div class="wp-avatar-library">
              <?php foreach ($avatarOptions as $avatarOption): ?>
                <button type="button" class="wp-avatar-option js-avatar-option" data-target="#public-edit-avatar" data-preview="#public-edit-avatar-preview" data-value="<?= e((string) $avatarOption) ?>">
                  <img src="<?= e((string) $avatarOption) ?>" alt="avatar" loading="lazy">
                </button>
              <?php endforeach; ?>
            </div>

            <div class="wp-avatar-preview-row">
              <span>Selecionado:</span>
              <img id="public-edit-avatar-preview" src="<?= e($avatarFallback) ?>" alt="avatar selecionado">
            </div>

            <button type="submit" class="wp-btn light">Salvar alteracoes</button>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <form method="post" action="/logout" class="wp-profiles-logout">
      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
      <button type="submit" class="wp-btn ghost">Sair da conta</button>
    </form>
  </section>
</main>
