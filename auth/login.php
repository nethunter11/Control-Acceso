<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../app/auth.php';
auth_start_session();
if (auth_user()) {
  header('Location: /Control-Acceso/index.php');
  exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$err = is_string($_GET['err'] ?? null) ? $_GET['err'] : '';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión | Control de Acceso</title>
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/../assets/css/theme.css') ?>" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/login.css?v=<?= filemtime(__DIR__.'/../assets/css/login.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
  <div class="login-appearance">
    <button id="btnContrast" class="btn-icon contrast-toggle" type="button" aria-label="Activar contraste reforzado" aria-pressed="false"><i class="bi bi-circle-half" aria-hidden="true"></i><span>Contraste</span></button>
  </div>
  <main class="login-shell" id="mainContent">
    <section class="login-identity" aria-label="Control de acceso institucional">
      <div class="login-brand"><img src="/Control-Acceso/img/logo.png" alt="Escudo institucional FACH"><div><div class="login-brand-name">Fuerza Aérea de Chile</div><div class="login-brand-sub">Sistema institucional</div></div></div>
      <div class="login-intro">
        <div class="page-eyebrow">Panel operativo</div>
        <h1>Control de acceso<span>.</span></h1>
        <p>Registro y validación de funcionarios y visitas, en un solo lugar.</p>
        <div class="login-capabilities"><span><i class="bi bi-shield-check" aria-hidden="true"></i>Validación</span><span><i class="bi bi-people" aria-hidden="true"></i>Visitas</span><span><i class="bi bi-journal-text" aria-hidden="true"></i>Registros</span></div>
      </div>
      <div class="login-identity-footer"><span class="login-rule"></span>Acceso para personal autorizado</div>
    </section>
    <section class="login-form-panel" aria-labelledby="loginTitle">
      <div class="login-form-icon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></div>
      <h2 id="loginTitle">Iniciar sesión</h2>
      <p class="login-description">Ingresa tus credenciales para continuar.</p>
      <?php if ($err): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?><div class="mt-1">Verifica tus credenciales e inténtalo nuevamente.</div></div>
      <?php endif; ?>
      <form method="post" action="/Control-Acceso/auth/login_post.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-4"><label class="form-label" for="loginUsername">Usuario</label><input id="loginUsername" name="username" class="form-control form-control-dark" placeholder="Tu nombre de usuario" autocomplete="username" autocapitalize="none" spellcheck="false" required></div>
        <div class="mb-4"><label class="form-label" for="loginPassword">Contraseña</label><input id="loginPassword" name="password" type="password" class="form-control form-control-dark" placeholder="Tu contraseña" autocomplete="current-password" required></div>
        <button class="btn btn-primary w-100 btn-login" type="submit">Ingresar al sistema<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
      </form>
      <div class="login-help"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Si necesitas acceso o ayuda con tu cuenta, contacta al administrador.</span></div>
    </section>
  </main>
  <footer class="login-footer">Control de Acceso <span>·</span> Uso institucional</footer>
  <?php include __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
