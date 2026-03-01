<!doctype html>
<html
  lang="pt-BR"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="ltr"
  data-skin="default"
  data-bs-theme="light"
  data-assets-path="/assets/materialize-admin/"
  data-template="wiseplay-admin">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'WisePlay Admin') ?></title>
  <link rel="icon" type="image/x-icon" href="/assets/materialize-admin/img/favicon/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/fonts/iconify-icons.css">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/libs/node-waves/node-waves.css">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/libs/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="/assets/materialize-admin/vendor/css/core.css">
  <link rel="stylesheet" href="/assets/materialize-admin/css/demo.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <script src="/assets/materialize-admin/vendor/js/helpers.js"></script>
  <script src="/assets/materialize-admin/vendor/js/template-customizer.js"></script>
  <script src="/assets/materialize-admin/js/config.js"></script>
</head>
<body data-admin-page="<?= e($adminPage ?? '') ?>">
  <script>
    window.WISEPLAY = window.WISEPLAY || {};
    window.WISEPLAY.csrf = "<?= e(App\core\Csrf::token()) ?>";
    window.WISEPLAY.currentPath = "<?= e($currentPath ?? '') ?>";
  </script>
  <?php $flash = flash_get(); ?>
  <?php if ($flash): ?>
    <div id="flash-data" data-type="<?= e($flash['type']) ?>" data-message="<?= e($flash['message']) ?>"></div>
  <?php endif; ?>

  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php include BASE_PATH . '/app/views/partials/admin_sidebar.php'; ?>

      <div class="layout-page">
        <?php include BASE_PATH . '/app/views/partials/admin_topbar.php'; ?>

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <?php include BASE_PATH . '/app/views/partials/admin_page_intro.php'; ?>
            <?= $content ?>
          </div>

          <footer class="content-footer footer bg-footer-theme">
            <div class="container-xxl">
              <div class="footer-container d-flex align-items-center justify-content-between py-3 flex-md-row flex-column">
                <div>WisePlay Admin</div>
                <div class="d-none d-lg-inline-block">PHP + MySQL + jQuery</div>
              </div>
            </div>
          </footer>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>
    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>
  </div>

  <script src="/assets/materialize-admin/vendor/libs/jquery/jquery.js"></script>
  <script src="/assets/materialize-admin/vendor/libs/popper/popper.js"></script>
  <script src="/assets/materialize-admin/vendor/js/bootstrap.js"></script>
  <script src="/assets/materialize-admin/vendor/libs/node-waves/node-waves.js"></script>
  <script src="/assets/materialize-admin/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="/assets/materialize-admin/vendor/js/menu.js"></script>
  <script src="/assets/materialize-admin/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/admin.js"></script>
</body>
</html>
