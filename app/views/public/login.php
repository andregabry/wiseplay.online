<main class="wp-login-page">
  <div class="wp-login-bg"></div>
  <section class="wp-login-panel">
    <a class="wp-logo login-logo" href="/login">WISEPLAY</a>

    <div class="wp-login-card">
      <h1>Entrar</h1>
      <p>Acesse sua conta para assistir filmes, series e cursos.</p>

      <form method="post" action="/login" class="wp-login-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="admin@wiseplay.local" required autocomplete="email">

        <label for="password">Senha</label>
        <input id="password" name="password" type="password" value="123456" required autocomplete="current-password">

        <button type="submit" class="wp-btn danger">Entrar</button>
      </form>

      <div class="wp-login-help">
        <small><a href="/register">Criar conta</a></small><br>
        <small>Seed dev: admin@wiseplay.local / 123456</small>
      </div>
    </div>
  </section>
</main>
