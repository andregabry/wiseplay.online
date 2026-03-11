<?php
$userAccount = $userAccount ?? [];
$subscription = $subscription ?? null;
$plans = $plans ?? [];
$paymentMethods = $paymentMethods ?? [];
$profiles = $profiles ?? [];

$subscriptionStatus = strtoupper((string) ($subscription['status'] ?? 'SEM_PLANO'));
$subscriptionStatusLabel = match ($subscriptionStatus) {
    'ACTIVE' => 'Ativa',
    'TRIALING' => 'Teste',
    'PAUSED' => 'Pausada',
    'PENDING' => 'Pendente',
    'PAYMENT_FAILED' => 'Pagamento falhou',
    'CANCELED' => 'Cancelada',
    'EXPIRED' => 'Expirada',
    default => 'Sem plano',
};

$nextBillingDate = '-';
if (is_array($subscription) && ((string) ($subscription['end_at'] ?? '')) !== '') {
    $timestamp = strtotime((string) $subscription['end_at']);
    if ($timestamp !== false) {
        $nextBillingDate = date('d/m/Y', $timestamp);
    }
}

$formatMoney = static function (float $price, string $currency): string {
    $symbol = strtoupper($currency) === 'BRL' ? 'R$' : strtoupper($currency);
    return $symbol . ' ' . number_format($price, 2, ',', '.');
};

$profileCount = count($profiles);
$currentPlanId = (int) ($subscription['plan_id'] ?? 0);
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-account-page">
  <div class="wp-account-shell">
    <aside class="wp-account-sidebar">
      <a class="wp-account-back" href="/browse">&larr; Voltar ao catalogo</a>
      <nav class="wp-account-side-nav" aria-label="Navegacao da conta">
        <a href="#overview">Visao geral</a>
        <a href="#subscription">Assinatura</a>
        <a href="#payment">Pagamento</a>
        <a href="#security">Seguranca</a>
        <a href="#profiles">Perfis</a>
        <a href="#danger">Conta</a>
      </nav>
    </aside>

    <section class="wp-account-main">
      <header class="wp-account-header" id="overview">
        <h1>Conta</h1>
        <p>Detalhes da assinatura e preferencias da conta WisePlay.</p>
      </header>

      <article class="wp-account-card" id="subscription">
        <header class="wp-account-card__head">
          <h2>Assinatura</h2>
          <span class="wp-account-pill status-<?= strtolower($subscriptionStatus) ?>"><?= e($subscriptionStatusLabel) ?></span>
        </header>

        <?php if ($subscription): ?>
          <div class="wp-account-subscription-summary">
            <div>
              <h3><?= e((string) ($subscription['plan_name'] ?? 'Plano ativo')) ?></h3>
              <p>
                <?= e((string) ($subscription['billing_cycle'] ?? 'MONTHLY')) ?>
                •
                <?= $formatMoney((float) ($subscription['price'] ?? 0), (string) ($subscription['currency'] ?? 'BRL')) ?>
              </p>
              <p>Proxima renovacao: <?= e($nextBillingDate) ?></p>
            </div>
          </div>

          <form method="post" action="/account/plan/change" class="wp-account-inline-form">
            <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
            <label for="account-plan-select">Alterar plano</label>
            <div class="wp-account-inline-form__row">
              <select id="account-plan-select" name="plan_id" required>
                <?php foreach ($plans as $plan): ?>
                  <option value="<?= (int) ($plan['id'] ?? 0) ?>" <?= (int) ($plan['id'] ?? 0) === $currentPlanId ? 'selected' : '' ?>>
                    <?= e((string) ($plan['plan_name'] ?? 'Plano')) ?> - <?= $formatMoney((float) ($plan['price'] ?? 0), (string) ($plan['currency'] ?? 'BRL')) ?> / <?= e(strtolower((string) ($plan['billing_cycle'] ?? 'monthly'))) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="wp-btn light">Salvar plano</button>
            </div>
          </form>
        <?php else: ?>
          <p class="wp-account-empty">Sua conta ainda nao possui assinatura ativa.</p>
        <?php endif; ?>
      </article>

      <article class="wp-account-card" id="payment">
        <header class="wp-account-card__head">
          <h2>Meios de pagamento</h2>
          <span class="wp-account-pill"><?= count($paymentMethods) ?> cadastrado(s)</span>
        </header>

        <div class="wp-account-payment-list">
          <?php if ($paymentMethods === []): ?>
            <p class="wp-account-empty">Nenhum meio de pagamento cadastrado.</p>
          <?php else: ?>
            <?php foreach ($paymentMethods as $method): ?>
              <div class="wp-account-payment-item">
                <div>
                  <strong><?= e((string) ($method['brand'] ?? 'CARD')) ?> •••• <?= e((string) ($method['last4'] ?? '----')) ?></strong>
                  <small>Expira em <?= sprintf('%02d', (int) ($method['exp_month'] ?? 0)) ?>/<?= (int) ($method['exp_year'] ?? 0) ?></small>
                  <?php if ((int) ($method['is_default'] ?? 0) === 1): ?>
                    <span class="wp-account-pill is-default">Padrao</span>
                  <?php endif; ?>
                </div>
                <div class="wp-account-payment-actions">
                  <?php if ((int) ($method['is_default'] ?? 0) !== 1): ?>
                    <form method="post" action="/account/payment-methods/default">
                      <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
                      <input type="hidden" name="method_id" value="<?= (int) ($method['id'] ?? 0) ?>">
                      <button type="submit" class="wp-btn tiny ghost">Definir padrao</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="/account/payment-methods/delete">
                    <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
                    <input type="hidden" name="method_id" value="<?= (int) ($method['id'] ?? 0) ?>">
                    <button type="submit" class="wp-btn tiny ghost">Remover</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <form method="post" action="/account/payment-methods/add" class="wp-account-form-grid">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <h3>Adicionar novo cartao</h3>
          <label>Nome do titular
            <input type="text" name="card_holder" maxlength="120" required>
          </label>
          <label>Numero do cartao
            <input type="text" name="card_number" maxlength="24" inputmode="numeric" required>
          </label>
          <label>Mes
            <input type="number" name="exp_month" min="1" max="12" required>
          </label>
          <label>Ano
            <input type="number" name="exp_year" min="<?= (int) date('Y') ?>" max="<?= (int) date('Y') + 20 ?>" required>
          </label>
          <label class="wp-account-checkbox">
            <input type="checkbox" name="set_default" value="1">
            Definir como padrao
          </label>
          <div class="wp-account-form-actions">
            <button type="submit" class="wp-btn light">Adicionar meio de pagamento</button>
          </div>
        </form>
      </article>

      <article class="wp-account-card" id="security">
        <header class="wp-account-card__head">
          <h2>Seguranca</h2>
        </header>

        <form method="post" action="/account/password/update" class="wp-account-form-grid">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <h3>Atualizar senha</h3>
          <label>Senha atual
            <input type="password" name="current_password" minlength="8" required>
          </label>
          <label>Nova senha
            <input type="password" name="new_password" minlength="8" required>
          </label>
          <label>Confirmar nova senha
            <input type="password" name="confirm_password" minlength="8" required>
          </label>
          <div class="wp-account-form-actions">
            <button type="submit" class="wp-btn light">Salvar nova senha</button>
          </div>
        </form>
      </article>

      <article class="wp-account-card" id="profiles">
        <header class="wp-account-card__head">
          <h2>Perfis</h2>
          <span class="wp-account-pill"><?= $profileCount ?> perfil(is)</span>
        </header>
        <p>Gerencie nomes, avatares e controle infantil dos perfis da sua conta.</p>
        <a href="/profiles?manage=1" class="wp-btn ghost">Gerenciar perfis</a>
      </article>

      <article class="wp-account-card is-danger" id="danger">
        <header class="wp-account-card__head">
          <h2>Zona de risco</h2>
        </header>
        <p>Esta acao remove sua conta, perfis e historico de forma permanente.</p>
        <form method="post" action="/account/delete" class="wp-account-form-grid">
          <input type="hidden" name="_csrf" value="<?= e(App\core\Csrf::token()) ?>">
          <label>Digite EXCLUIR para confirmar
            <input type="text" name="confirmation_text" maxlength="12" required>
          </label>
          <label>Senha atual
            <input type="password" name="current_password" minlength="8" required>
          </label>
          <div class="wp-account-form-actions">
            <button type="submit" class="wp-btn danger">Excluir conta</button>
          </div>
        </form>
      </article>
    </section>
  </div>
</main>
