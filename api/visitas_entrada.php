<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/concurrencia.php';
require_once __DIR__ . '/../app/puestos.php';
require_once __DIR__.'/../app/entrada.php';

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

  $nombre = campo_texto($data['nombre'] ?? '', 'nombre',120,true);
  $rut = rut_normalizar(campo_texto($data['rut'] ?? '', 'RUT',20,true));
  $telefono = campo_texto($data['telefono'] ?? '', 'teléfono',20);
  $modo = strtoupper(campo_texto($data['modo'] ?? 'PEATONAL','modo',10,true));
  $patente = strtoupper(campo_texto($data['patente'] ?? '', 'patente',12));
  $puesto = PUESTO_VISITAS;
  $motivo = campo_texto($data['motivo'] ?? '', 'motivo',160);

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

  $response = ejecutar_por_rut($pdo, 'visitas', $rut, function () use ($pdo, $rut, $nombre, $telefono, $modo, $patente, $puesto, $u, $motivo): array {
    $st = $pdo->prepare("SELECT id FROM visitas WHERE rut = ? AND estado = 'DENTRO' LIMIT 1");
    $st->execute([$rut]);
    if ($st->fetchColumn()) {
      return ['ok'=>true,'resultado'=>'RECHAZADO','motivo'=>'El visitante ya tiene un ingreso activo (sin salida).','guardado'=>false];
    }

    $st2 = $pdo->prepare("
      INSERT INTO visitas (nombre, rut, telefono, modo, patente, puesto, operador_id, motivo, fecha_hora_entrada, estado)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'DENTRO')
    ");
    $st2->execute([$nombre, $rut, $telefono ?: null, $modo, $patente ?: null, $puesto, $u['id'], $motivo ?: null]);
    $visitId=$pdo->lastInsertId();
    $saved=$pdo->prepare('SELECT fecha_hora_entrada FROM visitas WHERE id=?');
    $saved->execute([$visitId]);
    return [
      'ok'=>true,
      'resultado'=>'APROBADO',
      'id'=>$visitId,
      'fecha_hora_entrada'=>$saved->fetchColumn(),
      'telefono'=>$telefono ?: null,
      'patente'=>$patente ?: null,
      'rut'=>$rut,
      'nombre'=>$nombre,
      'modo'=>$modo,
      'puesto'=>$puesto,
    ];
  });
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (BloqueoRutOcupado $e) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
