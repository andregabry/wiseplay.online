<main class="admin-auth-wrap">
  <div class="card admin-auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="ri ri-tv-2-line text-primary icon-24px"></i>
        <h4 class="mb-0">WisePlay Admin</h4>
      </div>
      <p class="text-body-secondary mb-4">Acesse com credenciais administrativas.</p>
      <form method="post" action="/admin/login">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <div class="mb-3">
          <label for="aemail" class="form-label">Email</label>
          <input id="aemail" name="email" type="email" class="form-control" value="admin@wiseplay.local" required>
        </div>
        <div class="mb-4">
          <label for="apassword" class="form-label">Senha</label>
          <input id="apassword" name="password" type="password" class="form-control" value="123456" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Entrar no painel</button>
      </form>
    </div>
  </div>
</main>
