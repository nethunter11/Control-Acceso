<?php
// app/auth.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function auth_start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    $session = app_config()['session'];
    if (!empty($session['name'])) {
      session_name($session['name']);
    }
    if (!empty($session['save_path'])) {
      session_save_path($session['save_path']);
    }
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime',(string)$session['absolute_timeout']);
    session_set_cookie_params([
      'path'=>$session['cookie_path'] ?: '/Control-Acceso/',
      'httponly'=>true, 'samesite'=>'Lax',
      'secure'=>isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
  }
}

function auth_login(array $user): void {
  auth_start_session();
  $lookup = db()->prepare('SELECT id,username,rol,activo,password_hash,session_version FROM usuarios WHERE id=?');
  $lookup->execute([(int)($user['id'] ?? 0)]);
  $current = $lookup->fetch();
  if (!$current || (int)$current['activo'] !== 1 || (isset($user['password_hash']) && !hash_equals($current['password_hash'],$user['password_hash']))) throw new RuntimeException('La cuenta no está disponible.');
  $user = $current;
  session_regenerate_id(true);
  $_SESSION = [];
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $_SESSION['auth_started'] = time();
  $_SESSION['last_activity'] = time();
  $_SESSION['auth_version'] = (int)$user['session_version'];
  $_SESSION['auth_signature'] = hash('sha256',$user['password_hash']);
  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'rol' => $user['rol'],
    'session_version' => (int)$user['session_version'],
  ];
}

function auth_user(): ?array {
  auth_start_session();
  $user = $_SESSION['user'] ?? null;
  if (!is_array($user)) return null;
  if (auth_expires_at() <= time()) { $_SESSION = []; return null; }
  try {
    $lookup = db()->prepare('SELECT id,username,rol,activo,password_hash,session_version FROM usuarios WHERE id=?');
    $lookup->execute([(int)($user['id'] ?? 0)]);
    $current = $lookup->fetch();
  } catch (Throwable $error) {
    error_log('Verificación de sesión: '.$error->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'No se pudo verificar la sesión. Inténtalo nuevamente.'],JSON_UNESCAPED_UNICODE);
    exit;
  }
  if (!$current || (int)$current['activo'] !== 1
      || !in_array($current['rol'],['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'],true)
      || (int)($user['session_version'] ?? 0) !== (int)$current['session_version']
      || (int)($_SESSION['auth_version'] ?? 0) !== (int)$current['session_version']
      || !hash_equals(hash('sha256',$current['password_hash']),$_SESSION['auth_signature'] ?? '')
      || $current['rol'] !== ($user['rol'] ?? '')) {
    $_SESSION = [];
    return null;
  }
  if (!headers_sent()) header('Cache-Control: no-store');
  return $_SESSION['user'];
}

function auth_logout(): void {
  auth_start_session();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

function require_login(): void {
  if (!auth_user()) {
    header('Location: /Control-Acceso/auth/login.php');
    exit;
  }
  $_SESSION['last_activity'] = time();
}

function require_role(string $rol): void {
  $u = auth_user();
  if (!$u || $u['rol'] !== $rol) {
    http_response_code(403);
    echo "403 - No autorizado";
    exit;
  }
}

function csrf_token(): string {
  auth_start_session();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_validate(mixed $token): bool {
  auth_start_session();
  return is_string($token) && $token !== ''
    && isset($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $token);
}

function auth_role(): string {
  $u = auth_user();
  return strtoupper($u['rol'] ?? '');
}

function require_roles(array $allowed) {
  require_login();
  $role = auth_role();
  $allowed = array_map('strtoupper', $allowed);

  if (!in_array($role, $allowed, true)) {
    header('Location: /Control-Acceso/home.php');
    exit;
  }
}

function require_api_roles(array $allowed, array $methods = ['GET']): void {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  $user = auth_user();
  $allowed = array_map('strtoupper', $allowed);

  if ($user !== null && in_array(strtoupper($user['rol']), $allowed, true)) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method,$methods,true)) {
      header('Allow: '.implode(', ',$methods));
      http_response_code(405);
      echo json_encode(['ok'=>false,'error'=>'Método no permitido.'],JSON_UNESCAPED_UNICODE);
      exit;
    }
    if (!in_array($method,['GET','HEAD','OPTIONS'],true)) {
      if (!csrf_validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'La validación de seguridad venció. Recarga la página.'],JSON_UNESCAPED_UNICODE);
        exit;
      }
      $_SESSION['last_activity'] = time();
    }
    return;
  }

  http_response_code($user === null ? 401 : 403);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'error' => $user === null
      ? 'Debes iniciar sesión.'
      : 'No tienes permisos para realizar esta operación.',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

function auth_expires_at(): int {
  $config = app_config()['session'];
  return min((int)($_SESSION['last_activity'] ?? 0)+(int)$config['idle_timeout'],(int)($_SESSION['auth_started'] ?? 0)+(int)$config['absolute_timeout']);
}