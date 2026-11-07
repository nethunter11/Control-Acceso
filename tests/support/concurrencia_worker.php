<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../../app/auth.php';
$endpoint = $argv[1] ?? '';
if (!in_array($endpoint, ['registrar_evento.php', 'visitas_entrada.php', 'visitas_salida.php'], true)) {
  exit(1);
}
$payload = json_decode(base64_decode($argv[2] ?? '', true), true, 512, JSON_THROW_ON_ERROR);
$readyFile = $argv[3];
$startFile = $argv[4];
$operatorId = (int)$argv[5];
$config = app_config();

// Cada proceso usa una sesión distinta: el bloqueo de sesiones PHP no debe
// esconder una carrera de base de datos entre dos puestos de trabajo.
session_id(bin2hex(random_bytes(16)));
auth_login(['id' => $operatorId, 'username' => 'prueba_concurrencia', 'rol' => 'ADMIN']);
$fixtureId = session_id();
$requestToken = csrf_token();
session_write_close();
ob_start();
register_shutdown_function(function () use ($config, $fixtureId): void {
  $body = ob_get_clean();
  $response = ['status' => http_response_code() ?: 200, 'body' => json_decode($body, true)];
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
  $sessionFile = $config['session']['save_path'] . DIRECTORY_SEPARATOR . 'sess_' . $fixtureId;
  if (is_file($sessionFile)) {
    unlink($sessionFile);
  }
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
});

file_put_contents($readyFile, 'ready');
$deadline = microtime(true) + 12;
while (!is_file($startFile)) {
  if (microtime(true) > $deadline) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No comenzó la prueba paralela.']);
    exit(1);
  }
  usleep(1000);
  clearstatcache(true, $startFile);
}
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = $payload;
$_SERVER['HTTP_X_CSRF_TOKEN'] = $requestToken;
require __DIR__ . '/../../api/' . $endpoint;
