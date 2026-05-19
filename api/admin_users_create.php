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

  $username = trim($data['username'] ?? '');
  $password = (string)($data['password'] ?? '');
  $rol = strtoupper(trim($data['rol'] ?? 'GUARDIA'));
  $activo = (int)($data['activo'] ?? 1);

  if ($username === '') {
    echo json_encode(['ok'=>false,'error'=>'Username obligatorio'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (strlen($password) < 8) {
    echo json_encode(['ok'=>false,'error'=>'Password mínimo 8 caracteres'], JSON_UNESCAPED_UNICODE); exit;
  }

  $allowed = ['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'];
  if (!in_array($rol, $allowed, true)) {
    echo json_encode(['ok'=>false,'error'=>'Rol inválido'], JSON_UNESCAPED_UNICODE); exit;
  }

  $activo = ($activo === 1) ? 1 : 0;

  // usuario único
  $st = $pdo->prepare("SELECT id FROM usuarios WHERE username=? LIMIT 1");
  $st->execute([$username]);
  if ($st->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'Username ya existe'], JSON_UNESCAPED_UNICODE); exit;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $st2 = $pdo->prepare("INSERT INTO usuarios (username, password_hash, rol, activo) VALUES (?,?,?,?)");
  $st2->execute([$username, $hash, $rol, $activo]);

  echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}