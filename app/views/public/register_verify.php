<main class="wp-login-page wp-register-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <h1>Verificar email</h1>
      <p>Enviamos um codigo de 6 digitos para <strong><?= e((string) $email) ?></strong>.</p>

      <form method="post" action="/register/verify" class="wp-login-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

        <label for="register-code">Codigo</label>
        <input id="register-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>

        <button type="submit" class="wp-btn danger">Validar codigo</button>
      </form>

      <form method="post" action="/register/resend-code" class="wp-register-resend-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <button
          type="submit"
          class="wp-btn ghost"
          id="register-resend-button"
          data-resend-countdown="<?= (int) ($cooldownRemaining ?? 0) ?>"
          <?= ((int) ($cooldownRemaining ?? 0) > 0) ? 'disabled' : '' ?>>
          Reenviar codigo
        </button>
        <small class="wp-register-hint">O codigo expira em poucos minutos.</small>
      </form>

      <div class="wp-login-help">
        <small>Email incorreto? <a href="/register">Voltar e alterar</a></small>
      </div>
    </div>
  </section>
</main>

