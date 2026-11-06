<?php
// api/registrar_evento.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/concurrencia.php';
require_once __DIR__ . '/../app/puestos.php';

require_roles(['ADMIN','GUARDIA']);

$operadorId = (int)(auth_user()['id'] ?? 0);
if ($operadorId <= 0) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Tu sesión no identifica al operador. Vuelve a iniciar sesión.'], JSON_UNESCAPED_UNICODE);
  exit;
}

function input_data(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  return $_POST;
}

function respuesta_rut_sin_registro(string $rut, string $tipo, string $modo, string $puesto, string $motivo): array {
  return [
    'ok' => true,
    'resultado' => 'RECHAZADO',
    'guardado' => false,
    'motivo' => $motivo,
    'rut' => $rut,
    'tipo' => $tipo,
    'modo' => $modo,
    'puesto' => $puesto,
    'funcionario' => null,
  ];
}

try {
  $data = input_data();
  $rutIngresado = $data['rut'] ?? '';
  $rut = is_string($rutIngresado) && preg_match('//u', $rutIngresado) === 1
    ? rut_normalizar($rutIngresado)
    : '';
  $tipo = strtoupper(trim($data['tipo'] ?? ''));
  $puestoIngresado = array_key_exists('puesto', $data) ? $data['puesto'] : PUESTOS_ACCESO[0];
  $puesto = is_string($puestoIngresado) ? trim($puestoIngresado) : null;
  if (!in_array($puesto, PUESTOS_ACCESO, true)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Selecciona un puesto válido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $modo = strtoupper(trim($data['modo'] ?? 'PEATONAL'));
  if (!in_array($modo, ['PEATONAL','VEHICULO'], true)) $modo = 'PEATONAL';
  if (!in_array($tipo, ['ENTRADA','SALIDA'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Tipo inválido. Use ENTRADA o SALIDA.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 1) Validación RUT
  if (!rut_es_valido($rut)) {
    // El historial de movimientos exige un funcionario existente.
    echo json_encode(respuesta_rut_sin_registro($rut, $tipo, $modo, $puesto, 'RUT inválido (DV o formato)'), JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Comprobar el último movimiento y guardar el siguiente bajo el mismo bloqueo.
  $pdo = db();
  $response = ejecutar_por_rut($pdo, 'funcionarios', $rut, function () use ($pdo, $rut, $tipo, $modo, $puesto, $operadorId): array {
    $st = $pdo->prepare("SELECT rut, nombres, unidad, grado, estado FROM funcionarios WHERE rut = ?");
    $st->execute([$rut]);
    $f = $st->fetch();
    if (!$f) {
      return respuesta_rut_sin_registro($rut, $tipo, $modo, $puesto, 'RUT no registrado en la institución');
    }

    $resultado = 'RECHAZADO';
    $motivo = null;
    if ($f['estado'] !== 'ACTIVO') {
      $motivo = 'Funcionario INACTIVO';
    } else {
      $resultado = 'APROBADO';
    }

    if ($resultado === 'APROBADO') {
      $stLast = $pdo->prepare("
        SELECT tipo, fecha_hora
        FROM registros_acceso
        WHERE rut = ? AND resultado = 'APROBADO'
        ORDER BY fecha_hora DESC, id DESC
        LIMIT 1
      ");
      $stLast->execute([$rut]);
      $last = $stLast->fetch();

      if ($last && $last['tipo'] === $tipo) {
        return [
          'ok' => true,
          'resultado' => 'RECHAZADO',
          'motivo' => "Movimiento duplicado: la última acción aprobada fue {$last['tipo']}",
          'guardado' => false,
          'rut' => $rut,
          'tipo' => $tipo,
          'modo' => $modo,
          'puesto' => $puesto,
          'funcionario' => $f,
        ];
      }
      if (!$last && $tipo === 'SALIDA') {
        $resultado = 'RECHAZADO';
        $motivo = 'No se puede registrar SALIDA sin una ENTRADA aprobada previa';
      }
    }

    $st2 = $pdo->prepare("INSERT INTO registros_acceso (rut, tipo, modo, fecha_hora, puesto, operador_id, resultado, motivo)
                          VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)");
    $st2->execute([$rut, $tipo, $modo, $puesto, $operadorId, $resultado, $motivo]);
    return [
      'ok' => true,
      'rut' => $rut,
      'tipo' => $tipo,
      'modo' => $modo,
      'puesto' => $puesto,
      'resultado' => $resultado,
      'motivo' => $motivo,
      'guardado' => true,
      'operador_id' => $operadorId,
      'funcionario' => $f,
    ];
  });
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (BloqueoRutOcupado $e) {
  http_response_code(409);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
