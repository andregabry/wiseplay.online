<?php
$wallItems = $wallItems ?? [];
$startingPlan = $startingPlan ?? null;

$priceLine = 'Planos flexiveis para filmes, series e cursos.';
if ($startingPlan) {
    $currency = (string) ($startingPlan['currency'] ?? 'EUR');
    $price = (float) ($startingPlan['price'] ?? 0);
    $priceLine = 'A partir de ' . $currency . ' ' . number_format($price, 2, ',', '.') . '. Cancele quando quiser.';
}
?>

<main class="wp-home-page">
  <div class="wp-home-wall" aria-hidden="true">
    <?php if ($wallItems !== []): ?>
      <?php foreach ($wallItems as $item): ?>
        <span class="wp-home-wall-card" style="background-image:url('<?= e((string) ($item['image'] ?? '/assets/img/placeholders/bakground.jpg')) ?>')" title="<?= e((string) ($item['title'] ?? 'WisePlay')) ?>"></span>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="wp-home-wall-card fallback" style="background-image:url('/assets/img/placeholders/bakground.jpg')"></span>
    <?php endif; ?>
  </div>

  <div class="wp-home-overlay"></div>

  <header class="wp-home-topbar">
    <a class="wp-logo wp-home-logo" href="/">WISEPLAY</a>
    <div class="wp-home-topbar-actions">
      <button type="button" class="wp-home-lang js-public-coming-soon" data-label="Idioma">
        <span>PT</span>
        <strong>Portugues</strong>
        <small>&#9662;</small>
      </button>
      <a class="wp-btn danger wp-home-login-btn" href="/login">Iniciar sessao</a>
    </div>
  </header>

  <section class="wp-home-hero">
    <div class="wp-home-hero-copy">
      <h1>Acesso ilimitado a filmes, series e muito mais</h1>
      <p class="wp-home-price-line"><?= e($priceLine) ?></p>
      <p class="wp-home-cta-copy">Quer comecar a ver? Introduza o seu e-mail para criar ou reativar a sua conta.</p>

      <form method="post" action="/register/send-code" class="wp-home-email-form">
        <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
        <input type="hidden" name="name" value="">
        <input type="email" name="email" placeholder="Endereco de e-mail" required autocomplete="email">
        <button type="submit" class="wp-btn danger wp-home-start-btn">
          <span>Comecar</span>
          <span aria-hidden="true">&#8250;</span>
        </button>
      </form>
    </div>
  </section>

  <div class="wp-home-curve" aria-hidden="true"></div>
</main>
