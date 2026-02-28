<?php
$authStep = (string) ($authStep ?? 'account');
$steps = [
    'account' => ['label' => 'Conta', 'caption' => 'Seu email'],
    'verify' => ['label' => 'Verificacao', 'caption' => 'Codigo de 6 digitos'],
    'password' => ['label' => 'Senha', 'caption' => 'Defina o acesso'],
];
$order = array_keys($steps);
$activeIndex = array_search($authStep, $order, true);
if ($activeIndex === false) {
    $activeIndex = 0;
}
?>
<div class="wp-auth-steps" aria-label="Progresso do cadastro">
  <?php foreach ($steps as $key => $step): ?>
    <?php $index = array_search($key, $order, true); ?>
    <?php
      $state = 'upcoming';
      if ($index < $activeIndex) {
          $state = 'done';
      } elseif ($index === $activeIndex) {
          $state = 'active';
      }
    ?>
    <div class="wp-auth-step <?= e($state) ?>">
      <span class="wp-auth-step-index"><?= (int) ($index + 1) ?></span>
      <div class="wp-auth-step-copy">
        <strong><?= e((string) $step['label']) ?></strong>
        <small><?= e((string) $step['caption']) ?></small>
      </div>
    </div>
  <?php endforeach; ?>
</div>
