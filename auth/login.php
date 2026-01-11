<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../app/auth.php';
auth_start_session();

// Si ya está logueado, lo mandas al inicio
if (auth_user()) {
  header('Location: /Control-Acceso/index.php');
  exit;
}

// CSRF simple
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign in | Control Acceso</title>

  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/login.css" rel="stylesheet">
</head>

<body class="login-page">
  <div class="login-card">
    <div class="row g-0">
      <!-- IZQUIERDA -->
      <div class="col-md-5 login-left">
        <img src="/Control-Acceso/img/logo.png" alt="Logo institucional">
        <h1>CONTROL ACCESO</h1>
        <p>Ingreso autorizado para operadores y administradores.</p>
      </div>

      <!-- DERECHA -->
      <div class="col-md-7 login-right">
        <div class="login-title h4 text-white">Sign in</div>
        <div class="login-sub text-white-50">Ingresa tus credenciales</div>

        <?php if ($err): ?>
          <div class="alert alert-danger">
            <?= htmlspecialchars($err) ?>
          </div>
        <?php endif; ?>

        <form method="post" action="/Control-Acceso/auth/login_post.php" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="mb-3">
            <label class="form-label text-white-50">Usuario</label>
            <input name="username" class="form-control login-input" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-white-50">Contraseña</label>
            <input name="password" type="password" class="form-control login-input" required>
          </div>

          <button class="btn btn-primary w-100 btn-login" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
          </button>
        </form>
      </div>
    </div>
  </div>

  <script src="/Control-Acceso/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
