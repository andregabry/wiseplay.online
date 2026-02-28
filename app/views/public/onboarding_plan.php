<?php
$plans = $plans ?? [];
$gatewayEnabled = $gatewayEnabled ?? false;
$gatewayProvider = (string) ($gatewayProvider ?? 'MOCK');
?>

<main class="wp-onboarding-page">
  <div class="wp-login-bg"></div>
  <section class="wp-onboarding-content">
    <a class="wp-logo login-logo" href="/onboarding">WISEPLAY</a>

    <header class="wp-onboarding-header">
      <h1>Escolha seu plano</h1>
      <p>Seu primeiro acesso precisa de um plano ativo. Planos pagos exigem gateway de pagamento habilitado.</p>
      <div class="wp-onboarding-gateway-pill <?= $gatewayEnabled ? 'ok' : 'off' ?>">
        Gateway <?= e($gatewayProvider) ?>: <?= $gatewayEnabled ? 'habilitado' : 'desabilitado' ?>
      </div>
    </header>

    <section class="wp-plan-select-grid">
      <?php if ($plans === []): ?>
        <div class="wp-empty-card">Nenhum plano disponivel.</div>
      <?php else: ?>
        <?php foreach ($plans as $plan): ?>
          <?php
            $price = (float) ($plan['price'] ?? 0);
            $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
            $selectable = ((int) ($plan['public_selectable'] ?? 0) === 1);
          ?>
          <article class="wp-plan-card <?= $selectable ? '' : 'disabled' ?>">
            <div class="wp-plan-card-head">
              <h2><?= e((string) ($plan['plan_name'] ?? 'Plano')) ?></h2>
              <span class="wp-plan-code">/<?= e((string) ($plan['slug'] ?? 'plan')) ?></span>
            </div>

            <div class="wp-plan-price">
              <strong><?= e((string) ($plan['currency'] ?? 'BRL')) ?> <?= e(number_format($price, 2, ',', '.')) ?></strong>
              <small>/<?= strtolower((string) ($plan['billing_cycle'] ?? 'MONTHLY')) ?></small>
            </div>

            <ul>
              <li><?= (int) ($plan['profile_limit'] ?? 1) ?> perfil(is)</li>
              <li><?= (int) ($plan['max_streams'] ?? 1) ?> stream(s) simultaneos</li>
              <li><?= e((string) ($plan['quality_level'] ?? '1080p')) ?></li>
              <li><?= $requiresPayment ? 'Requer pagamento' : 'Sem pagamento obrigatorio' ?></li>
            </ul>

            <?php if ($selectable): ?>
              <form method="post" action="/onboarding/plan/select">
                <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
                <input type="hidden" name="plan_id" value="<?= (int) ($plan['id'] ?? 0) ?>">
                <button type="submit" class="wp-btn danger">Selecionar plano</button>
              </form>
            <?php else: ?>
              <button type="button" class="wp-btn ghost" disabled>Indisponivel</button>
              <small class="wp-plan-unavailable-msg"><?= e((string) ($plan['public_unavailable_reason'] ?? 'Plano indisponivel no momento.')) ?></small>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </section>
</main>
