<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db(); 

  // 2) Ping rápido
  $pdo->query("SELECT 1")->fetchColumn();

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['ok' => false]);
}
