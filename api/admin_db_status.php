<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/auth.php';
require_roles(['ADMIN']);

require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();
  $pdo->query("SELECT 1");
  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'DB caída'], JSON_UNESCAPED_UNICODE);
}