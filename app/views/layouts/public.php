<?php
$publicCssPath = BASE_PATH . '/public/assets/css/public.css';
$publicJsPath = BASE_PATH . '/public/assets/js/app.js';
$publicCssVersion = is_file($publicCssPath) ? (string) filemtime($publicCssPath) : (string) time();
$publicJsVersion = is_file($publicJsPath) ? (string) filemtime($publicJsPath) : (string) time();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'WisePlay') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="/assets/css/public.css?v=<?= e($publicCssVersion) ?>">
</head>
<body data-current-path="<?= e($currentPath ?? current_path()) ?>">
  <script>
    window.WISEPLAY = window.WISEPLAY || {};
    window.WISEPLAY.csrf = "<?= e(App\core\Csrf::token()) ?>";
    window.WISEPLAY.currentPath = "<?= e($currentPath ?? current_path()) ?>";
    window.WISEPLAY.watchProgressEndpoint = "/watch/progress";
  </script>

  <?php $flash = flash_get(); ?>
  <?php if ($flash): ?>
    <div id="flash-data" data-type="<?= e($flash['type']) ?>" data-message="<?= e($flash['message']) ?>"></div>
  <?php endif; ?>

  <?= $content ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/app.js?v=<?= e($publicJsVersion) ?>"></script>
</body>
</html>
