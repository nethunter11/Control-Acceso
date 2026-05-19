<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/auth.php';
require_roles(['ADMIN']);

require_once __DIR__ . '/../app/db.php';

try {
  $t0 = microtime(true);

  $pdo = db();
  $pdo->query("SELECT 1");

  $ms = (int) round((microtime(true) - $t0) * 1000);

  echo json_encode([
    'ok' => true,
    'latency_ms' => $ms,
    'checked_at' => date('Y-m-d H:i:s')
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode([
    'ok' => false,
    'error' => 'No se pudo conectar a la base de datos',
    'checked_at' => date('Y-m-d H:i:s')
  ], JSON_UNESCAPED_UNICODE);
}