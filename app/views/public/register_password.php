<main class="wp-login-page wp-register-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <?php include BASE_PATH . '/app/views/partials/public_auth_steps.php'; ?>
      <h1>Criar senha</h1>
      <p>Email verificado para <strong><?= e((string) $email) ?></strong>. Defina sua senha de acesso.</p>

      <div class="wp-auth-meta-card">
        <strong>Etapa 3 de 3</strong>
        <span>Sua senha libera o login e o restante do onboarding: plano, perfis e preferencias.</span>
      </div>

      <form method="post" action="/register/password" class="wp-login-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

        <label for="register-password">Senha</label>
        <input id="register-password" name="password" type="password" minlength="<?= (int) ($passwordMinLength ?? 8) ?>" required autocomplete="new-password">

        <label for="register-password-confirm">Confirmar senha</label>
        <input id="register-password-confirm" name="password_confirm" type="password" minlength="<?= (int) ($passwordMinLength ?? 8) ?>" required autocomplete="new-password">

        <button type="submit" class="wp-btn danger">Concluir cadastro</button>
      </form>

      <div class="wp-login-help">
        <small>Minimo de <?= (int) ($passwordMinLength ?? 8) ?> caracteres. Depois disso, o login leva voce direto para a escolha de plano.</small>
      </div>
    </div>
  </section>
</main>
