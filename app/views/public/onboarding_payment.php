<?php
$subscription = $subscription ?? [];
$gatewayEnabled = $gatewayEnabled ?? false;
$gatewayProvider = (string) ($gatewayProvider ?? 'MOCK');
$price = (float) ($subscription['price'] ?? 0);
?>

<main class="wp-onboarding-page">
  <div class="wp-login-bg"></div>
  <section class="wp-onboarding-content">
    <a class="wp-logo login-logo" href="/onboarding">WISEPLAY</a>

    <header class="wp-onboarding-header">
      <h1>Finalize seu pagamento</h1>
      <p>Seu plano <strong><?= e((string) ($subscription['plan_name'] ?? '')) ?></strong> sera ativado apos confirmacao do pagamento.</p>
      <div class="wp-onboarding-gateway-pill <?= $gatewayEnabled ? 'ok' : 'off' ?>">
        Gateway <?= e($gatewayProvider) ?>: <?= $gatewayEnabled ? 'habilitado' : 'desabilitado' ?>
      </div>
    </header>

    <section class="wp-payment-grid">
      <article class="wp-payment-summary">
        <h2>Resumo da assinatura</h2>
        <div class="wp-payment-line">
          <span>Plano</span>
          <strong><?= e((string) ($subscription['plan_name'] ?? '-')) ?></strong>
        </div>
        <div class="wp-payment-line">
          <span>Ciclo</span>
          <strong><?= e((string) ($subscription['billing_cycle'] ?? '-')) ?></strong>
        </div>
        <div class="wp-payment-line">
          <span>Valor</span>
          <strong><?= e((string) ($subscription['currency'] ?? 'BRL')) ?> <?= e(number_format($price, 2, ',', '.')) ?></strong>
        </div>
        <div class="wp-payment-line">
          <span>Status</span>
          <strong><?= e((string) ($subscription['payment_status'] ?? 'PENDING')) ?></strong>
        </div>
      </article>

      <article class="wp-payment-form-wrap">
        <h2>Metodo de pagamento</h2>
        <p class="wp-payment-note">Fluxo preparado para tokenizacao por gateway. Dados de cartao nao sao persistidos no WisePlay.</p>

        <form method="post" action="/onboarding/payment/submit" class="wp-login-form">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">

          <label for="card-holder">Nome no cartao</label>
          <input id="card-holder" name="card_holder" type="text" required maxlength="120" autocomplete="cc-name" <?= $gatewayEnabled ? '' : 'disabled' ?>>

          <label for="card-number">Numero do cartao</label>
          <input id="card-number" name="card_number" type="text" inputmode="numeric" required minlength="13" maxlength="19" autocomplete="cc-number" <?= $gatewayEnabled ? '' : 'disabled' ?>>

          <div class="wp-payment-inline">
            <div>
              <label for="exp-month">Mes</label>
              <input id="exp-month" name="exp_month" type="number" min="1" max="12" required autocomplete="cc-exp-month" <?= $gatewayEnabled ? '' : 'disabled' ?>>
            </div>
            <div>
              <label for="exp-year">Ano</label>
              <input id="exp-year" name="exp_year" type="number" min="<?= (int) date('Y') ?>" max="<?= (int) date('Y') + 20 ?>" required autocomplete="cc-exp-year" <?= $gatewayEnabled ? '' : 'disabled' ?>>
            </div>
            <div>
              <label for="card-cvv">CVV</label>
              <input id="card-cvv" name="cvv" type="password" inputmode="numeric" required minlength="3" maxlength="4" autocomplete="cc-csc" <?= $gatewayEnabled ? '' : 'disabled' ?>>
            </div>
          </div>

          <div class="wp-payment-actions">
            <a href="/onboarding/plan" class="wp-btn ghost">Alterar plano</a>
            <button type="submit" class="wp-btn danger" <?= $gatewayEnabled ? '' : 'disabled' ?>>Confirmar pagamento</button>
          </div>
        </form>
      </article>
    </section>
  </section>
</main>
