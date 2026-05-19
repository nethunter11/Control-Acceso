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
  if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Evitar borrar tu propio usuario (recomendado)
  $me = auth_user();
  $myId = (int)($me['id'] ?? 0);
  if ($id === $myId) {
    echo json_encode(['ok'=>false,'error'=>'No puedes eliminar tu propio usuario.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Borrar
  $st = $pdo->prepare("DELETE FROM usuarios WHERE id=?");
  $st->execute([$id]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}