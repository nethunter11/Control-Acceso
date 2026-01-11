<?php
require_once __DIR__ . '/app/auth.php';
require_login();
$activePage = 'visitas';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Visitas | Control Acceso</title>

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>

<body>
  <div class="app">
    <!-- SIDEBAR -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- MAIN -->
    <main class="main">
      <!-- TOPBAR -->
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <!-- CONTENT -->
      <section class="content">
        <div class="card card-dark">
          <div class="card-header card-header-dark">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="h6 mb-0">Visitas</div>
                <div class="text-muted small">Módulo en construcción</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="alert alert-info mb-0">
              Aquí irá el registro y control de visitas.
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
