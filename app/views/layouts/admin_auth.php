<!doctype html>
<html
  lang="pt-BR"
  class="layout-navbar-fixed"
  dir="ltr"
  data-skin="default"
  data-bs-theme="light"
  data-assets-path="/assets/materialize-admin/"
  data-template="wiseplay-admin-auth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'WisePlay Admin Login') ?></title>
  <link rel="icon" type="image/x-icon" href="/assets/materialize-admin/img/favicon/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/fonts/iconify-icons.css">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/css/core.css">
  <link rel="stylesheet" href="/assets/materialize-admin/css/demo.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-auth-page">
  <?php $flash = flash_get(); ?>
  <?php if ($flash): ?>
    <div id="flash-data" data-type="<?= e($flash['type']) ?>" data-message="<?= e($flash['message']) ?>"></div>
  <?php endif; ?>
  <?= $content ?>
  <script src="/assets/materialize-admin/vendor/libs/jquery/jquery.js"></script>
  <script src="/assets/materialize-admin/vendor/libs/popper/popper.js"></script>
  <script src="/assets/materialize-admin/vendor/js/bootstrap.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/admin.js"></script>
</body>
</html>
