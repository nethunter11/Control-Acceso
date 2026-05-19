<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/rut.php';

require_roles(['ADMIN','CONTROL_VISITA']);

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

  $nombre = trim($data['nombre'] ?? '');
  $rut = rut_normalizar(trim($data['rut'] ?? ''));
  $telefono = trim($data['telefono'] ?? '');
  $modo = strtoupper(trim($data['modo'] ?? 'PEATONAL'));
  $patente = strtoupper(trim($data['patente'] ?? ''));
  $puesto = trim($data['puesto'] ?? 'Portón 1');
  $motivo = trim($data['motivo'] ?? '');

  if ($nombre === '') {
    echo json_encode(['ok'=>false,'error'=>'Nombre es obligatorio'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (!rut_es_valido($rut)) {
    echo json_encode(['ok'=>false,'error'=>'RUT inválido'], JSON_UNESCAPED_UNICODE); exit;
  }
  if (!in_array($modo, ['PEATONAL','VEHICULO'], true)) $modo = 'PEATONAL';

  // si es peatonal, patente debe ir vacía
  if ($modo === 'PEATONAL') $patente = '';
  // si es vehiculo, patente recomendable
  if ($modo === 'VEHICULO' && $patente === '') {
    echo json_encode(['ok'=>false,'error'=>'Patente obligatoria si viene en vehículo'], JSON_UNESCAPED_UNICODE); exit;
  }

  // Regla: no permitir que el mismo RUT tenga otra visita "DENTRO"
  $st = $pdo->prepare("SELECT id FROM visitas WHERE rut = ? AND estado = 'DENTRO' LIMIT 1");
  $st->execute([$rut]);
  $existeDentro = $st->fetchColumn();
  if ($existeDentro) {
    echo json_encode(['ok'=>true,'resultado'=>'RECHAZADO','motivo'=>'El visitante ya tiene un ingreso activo (sin salida).','guardado'=>false], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $st2 = $pdo->prepare("
    INSERT INTO visitas (nombre, rut, telefono, modo, patente, puesto, operador_id, motivo, fecha_hora_entrada, estado)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'DENTRO')
  ");
  $st2->execute([$nombre, $rut, $telefono ?: null, $modo, $patente ?: null, $puesto, $u['id'], $motivo ?: null]);

  echo json_encode([
    'ok'=>true,
    'resultado'=>'APROBADO',
    'id'=>$pdo->lastInsertId(),
    'rut'=>$rut,
    'nombre'=>$nombre,
    'modo'=>$modo,
    'puesto'=>$puesto
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}