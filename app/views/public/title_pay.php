<?php
$profile = $profile ?? [];
$titleItem = $titleItem ?? null;
$offers = $offers ?? [];
$paymentGatewayEnabled = $paymentGatewayEnabled ?? false;
$checkoutReady = $checkoutReady ?? false;

if (!$titleItem) {
    echo '<main class="wp-generic"><p>Titulo nao encontrado.</p></main>';
    return;
}

$cover = trim((string) ($titleItem['cover_image'] ?? ''));
if ($cover === '' || str_ends_with($cover, '.gitkeep')) {
    $cover = '/assets/img/placeholders/image-placeholder.svg';
}

$offerTitle = static function (array $offer): string {
    $type = strtoupper((string) ($offer['offer_type'] ?? 'ONE_TIME'));
    $interval = strtoupper((string) ($offer['billing_interval'] ?? ''));
    if ($type === 'ONE_TIME') {
        return 'Pagamento unico';
    }
    return $interval === 'YEAR' ? 'Assinatura anual' : 'Assinatura mensal';
};
?>

<?php include BASE_PATH . '/app/views/partials/public_header.php'; ?>

<main class="wp-onboarding-page">
  <div class="wp-onboarding-content">
    <div class="wp-onboarding-header">
      <h1>Desbloquear "<?= e((string) ($titleItem['title_name'] ?? 'Titulo')) ?>"</h1>
      <p>Este titulo pertence ao marketplace WisePlay. Escolha uma oferta disponivel para liberar o acesso neste perfil.</p>
      <a class="wp-btn ghost" href="/title/<?= (int) ($titleItem['id'] ?? 0) ?>">Voltar ao titulo</a>
    </div>

    <div class="wp-payment-grid wp-marketplace-pay-grid">
      <aside class="wp-payment-summary">
        <img src="<?= e($cover) ?>" alt="<?= e((string) ($titleItem['title_name'] ?? 'Titulo')) ?>" class="wp-marketplace-pay-cover" onerror="this.src='/assets/img/placeholders/image-placeholder.svg'">
        <h2><?= e((string) ($titleItem['title_name'] ?? 'Titulo')) ?></h2>
        <p class="wp-payment-note"><?= e((string) (($titleItem['description'] ?? '') !== '' ? $titleItem['description'] : 'Sem descricao cadastrada.')) ?></p>
        <div class="wp-payment-line"><span>Tipo</span><strong><?= e((string) ($titleItem['type'] ?? 'TITLE')) ?></strong></div>
        <div class="wp-payment-line"><span>Genero</span><strong><?= e((string) ($titleItem['genre'] ?? 'General')) ?></strong></div>
        <div class="wp-payment-line"><span>Classificacao</span><strong><?= e((string) ($titleItem['maturity_rating'] ?? '16')) ?></strong></div>
      </aside>

      <section class="wp-payment-form-wrap">
        <h2>Ofertas disponiveis</h2>
        <?php if ($offers === []): ?>
          <div class="wp-empty-card"><p>Este titulo ainda nao possui ofertas ativas. Tente novamente mais tarde.</p></div>
        <?php else: ?>
          <div class="wp-marketplace-offers-grid">
            <?php foreach ($offers as $offer): ?>
              <?php
                $isSelectable = (int) ($offer['is_selectable'] ?? 0) === 1;
                $isEnabledForCheckout = $paymentGatewayEnabled && $checkoutReady && $isSelectable;
                $priceLabel = (string) ($offer['currency'] ?? 'BRL') . ' ' . number_format((float) ($offer['price'] ?? 0), 2, ',', '.');
              ?>
              <article class="wp-plan-card<?= !$isEnabledForCheckout ? ' disabled' : '' ?>">
                <div class="wp-plan-card-head">
                  <h2><?= e($offerTitle($offer)) ?></h2>
                  <span class="wp-plan-code"><?= e((string) ($offer['status'] ?? 'ACTIVE')) ?></span>
                </div>
                <div class="wp-plan-price"><strong><?= e($priceLabel) ?></strong></div>
                <ul>
                  <li><?= strtoupper((string) ($offer['offer_type'] ?? 'ONE_TIME')) === 'ONE_TIME' ? 'Acesso permanente ao titulo' : 'Acesso enquanto a assinatura estiver ativa' ?></li>
                  <li>Oferta vinculada a este produtor</li>
                  <li><?= $isSelectable ? 'Pronta para checkout' : 'Marcada como coming soon pelo produtor' ?></li>
                </ul>
                <?php if (!$paymentGatewayEnabled): ?>
                  <p class="wp-plan-unavailable-msg">Checkout ainda nao habilitado na configuracao global.</p>
                <?php elseif (!$checkoutReady): ?>
                  <p class="wp-plan-unavailable-msg">Checkout entra na fase 3 da integracao InfinitePay.</p>
                <?php elseif (!$isSelectable): ?>
                  <p class="wp-plan-unavailable-msg">Oferta visivel, mas indisponivel para compra no momento.</p>
                <?php endif; ?>
                <div class="wp-payment-actions">
                  <button class="wp-btn light" type="button" <?= $isEnabledForCheckout ? '' : 'disabled' ?>><?= $isEnabledForCheckout ? 'Continuar para pagamento' : 'Em breve' ?></button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>
