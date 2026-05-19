<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/auth.php';
require_roles(['ADMIN']);

require_once __DIR__ . '/../app/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

try {
  $pdo = db();

  $id = (int)($data['id'] ?? 0);
  $password = (string)($data['password'] ?? '');

  if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (strlen($password) < 8) {
    echo json_encode(['ok'=>false,'error'=>'Password mínimo 8 caracteres'], JSON_UNESCAPED_UNICODE); exit;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $st = $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
  $st->execute([$hash, $id]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}