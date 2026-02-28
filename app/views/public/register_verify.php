<main class="wp-login-page wp-register-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <?php include BASE_PATH . '/app/views/partials/public_auth_steps.php'; ?>
      <h1>Verificar email</h1>
      <p>Enviamos um codigo de 6 digitos para <strong><?= e((string) $email) ?></strong>.</p>

      <div class="wp-auth-meta-card">
        <strong>Etapa 2 de 3</strong>
        <span>Use o codigo para confirmar que esse email e seu. Se precisar, voce pode reenviar apos o tempo de espera.</span>
      </div>

      <form method="post" action="/register/verify" class="wp-login-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

        <label for="register-code">Codigo</label>
        <input id="register-code" name="code" type="text" class="wp-code-input" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>

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
        <small>Email incorreto? <a href="/register?restart=1">Voltar e alterar</a></small>
      </div>

      <?php if (!empty($showDevMailHint)): ?>
        <div class="wp-auth-inline-hint">
          Se estiver em ambiente local, confira <code>storage/logs/mail.log</code> para visualizar o codigo enviado.
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
