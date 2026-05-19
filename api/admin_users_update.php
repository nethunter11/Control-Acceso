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
    echo json_encode(['ok'=>false,'error'=>'ID inválido'], JSON_UNESCAPED_UNICODE); exit;
  }

  $fields = [];
  $params = [];

  if (isset($data['rol'])) {
    $rol = strtoupper(trim($data['rol']));
    $allowed = ['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'];
    if (!in_array($rol, $allowed, true)) {
      echo json_encode(['ok'=>false,'error'=>'Rol inválido'], JSON_UNESCAPED_UNICODE); exit;
    }
    $fields[] = "rol=?";
    $params[] = $rol;
  }

  if (isset($data['activo'])) {
    $activo = ((int)$data['activo'] === 1) ? 1 : 0;
    $fields[] = "activo=?";
    $params[] = $activo;
  }

  if (!$fields) {
    echo json_encode(['ok'=>false,'error'=>'Nada para actualizar'], JSON_UNESCAPED_UNICODE); exit;
  }

  $params[] = $id;

  $sql = "UPDATE usuarios SET " . implode(", ", $fields) . " WHERE id=?";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}