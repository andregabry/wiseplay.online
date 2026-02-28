<?php
$user = $user ?? [];
$avatar = trim((string) ($user['account_avatar'] ?? ''));
if ($avatar === '' || str_ends_with($avatar, '.gitkeep')) {
    $avatar = '/assets/img/avatar-library/avatar-01.svg';
}
$createdAt = (string) ($user['created_at'] ?? '');
$lastLoginAt = (string) ($user['last_login_at'] ?? '');
?>

<div class="card mb-4">
  <div class="card-body p-0">
    <div class="p-4" style="background:linear-gradient(125deg, rgba(115,103,240,.16), rgba(0,186,207,.1));">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="avatar avatar-xl">
          <img src="<?= e($avatar) ?>" class="rounded-circle" alt="Avatar" onerror="this.src='/assets/img/avatar-library/avatar-01.svg'">
        </div>
        <div class="flex-grow-1">
          <h4 class="mb-1"><?= e((string) ($user['name'] ?? 'Admin')) ?></h4>
          <div class="text-body-secondary"><?= e((string) ($user['email'] ?? '')) ?></div>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <span class="badge bg-label-primary"><?= e((string) ($user['role'] ?? 'ADMIN')) ?></span>
            <span class="badge <?= ((string) ($user['status'] ?? 'ACTIVE') === 'ACTIVE') ? 'bg-label-success' : 'bg-label-warning' ?>"><?= e((string) ($user['status'] ?? 'ACTIVE')) ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="p-3">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#me-tab-profile" type="button" role="tab">Profile</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#me-tab-security" type="button" role="tab">Security</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#me-tab-activity" type="button" role="tab">Activity</button></li>
      </ul>

      <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="me-tab-profile" role="tabpanel">
          <div class="form-errors text-danger small mb-2" data-form-errors="me-update"></div>
          <form id="me-update-form" class="js-ajax-form" data-endpoint="/admin/me/update" data-errors-key="me-update" data-has-files="1" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Avatar da conta (URL)</label>
                <input class="form-control" name="account_avatar" value="<?= e($avatar) ?>" data-preview-key="account_avatar">
                <div class="wp-image-preview-wrap mt-2">
                  <img src="<?= e($avatar) ?>" data-preview-key-view="account_avatar" alt="preview" onerror="this.src='/assets/img/placeholders/image-placeholder.svg'">
                  <small>Preview da identidade da conta</small>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Upload avatar</label>
                <input class="form-control" type="file" name="account_avatar_file" accept="image/png,image/jpeg,image/webp" data-preview-key="account_avatar">
              </div>
              <div class="col-md-4">
                <label class="form-label">Nome</label>
                <input class="form-control" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nova senha (opcional)</label>
                <input class="form-control" type="password" name="password" placeholder="Preencha para alterar">
              </div>
            </div>
          </form>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary btn-semantic-primary js-submit-form" data-form="#me-update-form">Salvar alteracoes</button>
            <a class="btn btn-outline-secondary btn-semantic-neutral" href="/admin">Voltar</a>
          </div>
        </div>

        <div class="tab-pane fade" id="me-tab-security" role="tabpanel">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="card border">
                <div class="card-body">
                  <h6 class="mb-1">Seguranca</h6>
                  <small class="text-body-secondary">Atualize sua senha regularmente e use credenciais fortes.</small>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border">
                <div class="card-body">
                  <h6 class="mb-1">Sessao atual</h6>
                  <small class="text-body-secondary">Ultimo login registrado: <?= e($lastLoginAt !== '' ? $lastLoginAt : '-') ?></small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="me-tab-activity" role="tabpanel">
          <div class="row g-3">
            <div class="col-md-4">
              <div class="card border">
                <div class="card-body">
                  <div class="text-body-secondary small">Conta criada em</div>
                  <h6 class="mb-0"><?= e($createdAt !== '' ? $createdAt : '-') ?></h6>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border">
                <div class="card-body">
                  <div class="text-body-secondary small">Ultimo login</div>
                  <h6 class="mb-0"><?= e($lastLoginAt !== '' ? $lastLoginAt : '-') ?></h6>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border">
                <div class="card-body">
                  <div class="text-body-secondary small">Status da conta</div>
                  <h6 class="mb-0"><?= e((string) ($user['status'] ?? 'ACTIVE')) ?></h6>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
