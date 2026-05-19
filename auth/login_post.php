<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

auth_start_session();

if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
  header('Location: /Control-Acceso/auth/login.php?err=CSRF inválido');
  exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
  header('Location: /Control-Acceso/auth/login.php?err=Completa usuario y contraseña');
  exit;
}

try {
  $pdo = db();

  $st = $pdo->prepare("SELECT id, username, password_hash, rol, activo FROM usuarios WHERE username = ? LIMIT 1");
  $st->execute([$username]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if (!$u || (int)$u['activo'] !== 1) {
    header('Location: /Control-Acceso/auth/login.php?err=Usuario o contraseña incorrectos');
    exit;
  }

  if (!password_verify($password, $u['password_hash'])) {
    header('Location: /Control-Acceso/auth/login.php?err=Usuario o contraseña incorrectos');
    exit;
  }

  auth_login($u);

  header('Location: /Control-Acceso/home.php');
  exit;

} catch (Throwable $e) {
  header('Location: /Control-Acceso/auth/login.php?err=Error interno');
  exit;
}
