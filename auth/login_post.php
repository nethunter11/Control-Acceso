<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/login_intentos.php';
auth_start_session();
function login_error(string $message): never {
  header('Location: /Control-Acceso/auth/login.php?err='.rawurlencode($message));
  exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!csrf_validate($_POST['csrf'] ?? null)) login_error('Recarga la página e intenta nuevamente.');
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($username) || !is_string($password) || !preg_match('//u',$username) || mb_strlen($username,'UTF-8') > 60 || strlen($password) > 72) login_error('Usuario o contraseña incorrectos.');
$username = trim($username);
if ($username === '' || $password === '') login_error('Completa usuario y contraseña.');
try {
  if (login_limite($username)) login_error('Demasiados intentos. Espera 10 minutos antes de volver a intentar.');
  $st = db()->prepare('SELECT id,username,password_hash,rol,activo FROM usuarios WHERE username=? LIMIT 1');
  $st->execute([$username]);
  $user = $st->fetch();
  $validPassword = password_verify($password,$user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
  if (!$user || (int)$user['activo'] !== 1 || !$validPassword || !in_array($user['rol'],['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'],true)) {
    login_limite($username,true);
    login_error('Usuario o contraseña incorrectos.');
  }
  login_limite($username,false,true);
  auth_login($user);
  header('Location: /Control-Acceso/home.php');
} catch (Throwable $error) {
  error_log('Inicio de sesión: '.$error->getMessage());
  login_error('No se pudo iniciar sesión. Inténtalo nuevamente.');
}