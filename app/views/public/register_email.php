<main class="wp-login-page wp-register-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <?php include BASE_PATH . '/app/views/partials/public_auth_steps.php'; ?>
      <h1>Criar conta</h1>
      <p>Comece com seu email. Vamos validar a conta antes de liberar o primeiro acesso.</p>

      <div class="wp-auth-meta-card">
        <strong>Etapa 1 de 3</strong>
        <span>Voce recebe um codigo de 6 digitos e pode retomar o fluxo depois sem perder o cadastro.</span>
      </div>

      <form method="post" action="/register/send-code" class="wp-login-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

        <label for="register-name">Nome (opcional)</label>
        <input id="register-name" name="name" type="text" value="<?= e((string) ($prefillName ?? '')) ?>" autocomplete="name" maxlength="120">

        <label for="register-email">Email</label>
        <input id="register-email" name="email" type="email" value="<?= e((string) ($prefillEmail ?? '')) ?>" required autocomplete="email">

        <button type="submit" class="wp-btn danger">Enviar codigo</button>
      </form>

      <div class="wp-login-help">
        <small>Ja possui conta? <a href="/login">Entrar</a></small>
      </div>

      <?php if (!empty($showDevMailHint)): ?>
        <div class="wp-auth-inline-hint">
          Ambiente local sem SMTP ativo. O codigo tambem fica registrado em <code>storage/logs/mail.log</code>.
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
