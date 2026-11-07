<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_api_roles(['ADMIN','CONTROL_VISITA'],['POST']);

require_login();
$u = auth_user();

function input_data(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  return $_POST;
}

try {
  $pdo = db();
  $data = input_data();

  $id = filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if ($id === false) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido'], JSON_UNESCAPED_UNICODE); exit;
  }

  // La condición y el cambio se ejecutan juntos: solo una solicitud modifica la fila.
  $st2 = $pdo->prepare("
    UPDATE visitas
    SET fecha_hora_salida = NOW(),
        estado = 'SALIO', operador_salida_id = ?
    WHERE id = ? AND estado = 'DENTRO'
  ");
  $st2->execute([(int)$u['id'],$id]);

  if ($st2->rowCount() !== 1) {
    $st = $pdo->prepare('SELECT estado FROM visitas WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
      echo json_encode(['ok'=>false,'error'=>'Visita no existe'], JSON_UNESCAPED_UNICODE);
      exit;
    }
    echo json_encode(['ok'=>true,'resultado'=>'RECHAZADO','motivo'=>'La visita ya tiene salida registrada.','guardado'=>false], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['ok'=>true,'resultado'=>'APROBADO','id'=>$id,'operador_salida_id'=>(int)$u['id']], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
