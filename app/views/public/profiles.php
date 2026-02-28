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

$stageTitle = $manageMode ? 'Gerenciar perfis:' : 'Quem esta assistindo?';
$showCreateModalOnLoad = $manageMode && ($currentCount === 0 || $onboardingNeedsProfile || (string) ($_GET['create'] ?? '') === '1');
?>

<main class="wp-profiles-page<?= $manageMode ? ' manage-mode' : '' ?>" data-profile-manage-mode="<?= $manageMode ? '1' : '0' ?>" data-profile-auto-open-create="<?= $showCreateModalOnLoad ? '1' : '0' ?>">
  <a class="wp-logo profile-logo" href="/browse">WISEPLAY</a>

  <section class="wp-profiles-content">
    <header class="wp-profiles-stage<?= $manageMode ? ' manage' : '' ?>">
      <h1><?= e($stageTitle) ?></h1>
      <?php if (!$manageMode): ?>
        <p>Selecione um perfil para entrar no WisePlay.</p>
      <?php endif; ?>
      <?php if (!$manageMode || $onboardingNeedsProfile): ?>
        <div class="wp-profiles-stage-meta">
          <span><?= e((string) ($plan['plan_name'] ?? 'Sem plano')) ?></span>
          <small><?= $currentCount ?>/<?= max(0, $planLimit) ?> perfis</small>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($onboardingNeedsProfile): ?>
      <div class="wp-onboarding-note wp-profiles-note">
        Seu primeiro acesso precisa de pelo menos um perfil antes de entrar no catalogo.
      </div>
    <?php endif; ?>

    <section class="wp-profile-grid wp-profile-grid-stage<?= $manageMode ? ' is-manage' : '' ?>">
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
        <article class="wp-profile-tile <?= $active ? 'active' : '' ?><?= $manageMode ? ' is-manage' : '' ?>">
          <?php if ($manageMode): ?>
            <button type="button" class="wp-profile-btn wp-profile-stage-btn js-open-profile-editor" data-profile='<?= e($payload) ?>'>
              <span class="wp-profile-avatar-frame">
                <img src="<?= e($avatar) ?>" alt="Avatar de <?= e((string) $profile['profile_name']) ?>" onerror="this.src='<?= e($avatarFallback) ?>'">
                <?php if ($isKids): ?><small>Criancas</small><?php endif; ?>
                <span class="wp-profile-edit-badge" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.7 5.3 18.7 9.3M4 20l4.2-1 10-10a1.6 1.6 0 0 0 0-2.3l-1-1a1.6 1.6 0 0 0-2.3 0l-10 10L4 20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              </span>
              <span class="wp-profile-name"><?= e((string) ($profile['profile_name'] ?? 'Perfil')) ?></span>
            </button>

            <form method="post" action="/profiles/delete" class="wp-hidden-delete-form js-public-delete-profile-form" id="public-delete-profile-<?= $profileId ?>">
              <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
              <input type="hidden" name="profile_id" value="<?= $profileId ?>">
            </form>
          <?php else: ?>
            <form method="post" action="/profiles/select" class="wp-profile-form">
              <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
              <input type="hidden" name="profile_id" value="<?= $profileId ?>">
              <button type="submit" class="wp-profile-btn wp-profile-stage-btn">
                <span class="wp-profile-avatar-frame">
                  <img src="<?= e($avatar) ?>" alt="Avatar de <?= e((string) $profile['profile_name']) ?>" onerror="this.src='<?= e($avatarFallback) ?>'">
                  <?php if ($isKids): ?><small>Criancas</small><?php endif; ?>
                </span>
                <span class="wp-profile-name"><?= e((string) ($profile['profile_name'] ?? 'Perfil')) ?></span>
              </button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <article class="wp-profile-tile wp-profile-tile-add <?= $canCreateProfile ? '' : 'disabled' ?><?= $manageMode ? ' is-manage' : '' ?>">
        <?php if ($manageMode): ?>
          <button
            type="button"
            class="wp-profile-btn wp-profile-add-card js-open-profile-create"
            data-limit-reached="<?= $canCreateProfile ? '0' : '1' ?>"
            data-limit-value="<?= max(0, $planLimit) ?>">
            <span class="wp-profile-add-circle">
              <span class="wp-profile-add-plus">+</span>
            </span>
            <span class="wp-profile-name">Adicionar perfil</span>
          </button>
        <?php else: ?>
          <a
            href="<?= $canCreateProfile ? '/profiles?manage=1#new-profile' : '#' ?>"
            class="wp-profile-btn wp-profile-add-card js-public-create-profile"
            data-limit-reached="<?= $canCreateProfile ? '0' : '1' ?>"
            data-limit-value="<?= max(0, $planLimit) ?>">
            <span class="wp-profile-add-circle">
              <span class="wp-profile-add-plus">+</span>
            </span>
            <span class="wp-profile-name">Adicionar perfil</span>
          </a>
        <?php endif; ?>
      </article>
    </section>

    <div class="wp-profiles-bottom-actions<?= $manageMode ? ' manage' : '' ?>">
      <?php if ($manageMode): ?>
        <a href="/profiles" class="wp-profiles-manage-btn is-solid">Concluido</a>
      <?php else: ?>
        <a href="/profiles?manage=1" class="wp-profiles-manage-btn">Gerenciar perfis</a>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($manageMode): ?>
    <div class="wp-profile-editor-modal" id="public-profile-editor-modal" hidden>
      <div class="wp-profile-editor-backdrop js-close-profile-editor"></div>
      <div class="wp-profile-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="public-profile-editor-title">
        <button type="button" class="wp-profile-editor-close js-close-profile-editor" aria-label="Fechar">
          <span>&times;</span>
        </button>

        <div class="wp-profile-editor-head">
          <div>
            <small class="wp-profile-editor-kicker">WisePlay</small>
            <h2 id="public-profile-editor-title">Editar perfil</h2>
            <p id="public-profile-editor-copy">Atualize o nome, avatar e o tipo do perfil.</p>
          </div>
          <div class="wp-profile-editor-preview">
            <img id="public-profile-editor-preview" src="<?= e($avatarFallback) ?>" alt="Preview do perfil">
          </div>
        </div>

        <form method="post" action="/profiles/update" enctype="multipart/form-data" id="public-profile-editor-form" class="wp-profile-editor-form">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <input type="hidden" name="profile_id" id="public-profile-id" value="">
          <input type="hidden" name="avatar_path" id="public-profile-avatar" value="<?= e($avatarFallback) ?>">

          <div class="wp-profile-editor-grid">
            <div>
              <label class="wp-form-label" for="public-profile-name">Nome do perfil</label>
              <input class="wp-form-input" id="public-profile-name" name="profile_name" required minlength="2" maxlength="40">
            </div>

            <div>
              <label class="wp-form-label" for="public-profile-is-kids">Tipo de perfil</label>
              <select class="wp-form-input" name="is_kids" id="public-profile-is-kids">
                <option value="0">Adulto</option>
                <option value="1">Criancas</option>
              </select>
            </div>
          </div>

          <div class="wp-profile-editor-upload-row">
            <div>
              <label class="wp-form-label" for="public-profile-avatar-file">Upload avatar (opcional)</label>
              <input class="wp-form-input" type="file" id="public-profile-avatar-file" name="avatar_file" accept="image/png,image/jpeg,image/webp">
            </div>
            <div class="wp-profile-editor-limit">
              <strong><?= $currentCount ?>/<?= max(0, $planLimit) ?></strong>
              <span>Perfis disponiveis no seu plano <?= e((string) ($plan['plan_name'] ?? 'WisePlay')) ?></span>
            </div>
          </div>

          <div>
            <label class="wp-form-label">Escolha um avatar</label>
            <div class="wp-avatar-library wp-avatar-library-modal">
              <?php foreach ($avatarOptions as $avatarOption): ?>
                <button type="button" class="wp-avatar-option js-avatar-option" data-target="#public-profile-avatar" data-preview="#public-profile-editor-preview" data-value="<?= e((string) $avatarOption) ?>">
                  <img src="<?= e((string) $avatarOption) ?>" alt="avatar" loading="lazy">
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="wp-profile-editor-actions">
            <button type="button" class="wp-btn ghost js-close-profile-editor">Cancelar</button>
            <button type="button" class="wp-btn ghost wp-profile-delete-trigger" id="public-profile-delete-button" hidden>Excluir perfil</button>
            <button type="submit" class="wp-btn light" id="public-profile-submit-button">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</main>
