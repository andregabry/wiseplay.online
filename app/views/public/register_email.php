<main class="wp-login-page wp-register-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <h1>Criar conta</h1>
      <p>Digite seu email para receber um codigo de verificacao.</p>

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
    </div>
  </section>
</main>

