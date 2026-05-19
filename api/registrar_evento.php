<?php
// api/registrar_evento.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['ADMIN','GUARDIA']);

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
  $data = input_data();
  $rut = rut_normalizar($data['rut'] ?? '');
  $tipo = strtoupper(trim($data['tipo'] ?? ''));
  $puesto = trim($data['puesto'] ?? 'Portón 1');
  $modo = strtoupper(trim($data['modo'] ?? 'PEATONAL'));
  if (!in_array($modo, ['PEATONAL','VEHICULO'], true)) $modo = 'PEATONAL';
  if (!in_array($tipo, ['ENTRADA','SALIDA'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Tipo inválido. Use ENTRADA o SALIDA.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = db();

  // 1) Validación RUT
  if (!rut_es_valido($rut)) {
    // registra rechazado
    $motivo = 'RUT inválido (DV o formato)';
    $st = $pdo->prepare("INSERT INTO registros_acceso (rut, tipo, modo, fecha_hora, puesto, resultado, motivo)
                         VALUES (?, ?, NOW(), ?, 'RECHAZADO', ?)");
    $st->execute([$rut, $tipo, $puesto, $motivo]);

    echo json_encode([
      'ok' => true,
      'resultado' => 'RECHAZADO',
      'motivo' => $motivo,
      'rut' => $rut
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 2) Consulta funcionario
  $st = $pdo->prepare("SELECT rut, nombres, unidad, grado, estado FROM funcionarios WHERE rut = ?");
  $st->execute([$rut]);
  $f = $st->fetch();

  $resultado = 'RECHAZADO';
  $motivo = null;

  if (!$f) {
    $motivo = 'RUT no registrado en la institución';
  } else if ($f['estado'] !== 'ACTIVO') {
    $motivo = 'Funcionario INACTIVO';
  } else {
    $resultado = 'APROBADO';
  }
    // 2.5) Reglas anti-duplicado (solo si iba a ser APROBADO)
  if ($resultado === 'APROBADO') {

    // Último evento APROBADO para este RUT (ignora los RECHAZADOS)
    $stLast = $pdo->prepare("
      SELECT tipo, fecha_hora
      FROM registros_acceso
      WHERE rut = ? AND resultado = 'APROBADO'
      ORDER BY fecha_hora DESC, id DESC
      LIMIT 1
    ");
    $stLast->execute([$rut]);
    $last = $stLast->fetch();

    if ($last) {
      // Regla principal: no permitir dos iguales seguidos
      if ($last['tipo'] === $tipo) {
        $motivo = "Movimiento duplicado: la última acción aprobada fue {$last['tipo']}";

        // NO guardar en BD, solo responder para mostrar el mensaje en pantalla
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'ok'        => true,
          'resultado' => 'RECHAZADO',
          'motivo'    => $motivo,
          'guardado'  => false,

          // Campos que tu UI usa para armar el recuadro
          'rut'       => $rut,
          'tipo'      => $tipo,
          'modo'      => $modo,
          'puesto'    => $puesto,

          // (Opcional pero recomendado) Datos del funcionario para que no salga undefined en nombre/unidad/grado
          'funcionario' => [
            'nombre' => $func['nombre'] ?? null,
            'unidad' => $func['unidad'] ?? null,
            'grado'  => $func['grado']  ?? null,
            'activo' => $func['activo'] ?? null
          ],
        ]);
        exit;

      }
    } else {
      // OPCIONAL (recomendado): no permitir SALIDA si nunca hubo ENTRADA aprobada
      if ($tipo === 'SALIDA') {
        $resultado = 'RECHAZADO';
        $motivo = 'No se puede registrar SALIDA sin una ENTRADA aprobada previa';
      }
    }
  }


  // 3) Registrar evento
  $st2 = $pdo->prepare("INSERT INTO registros_acceso (rut, tipo, modo, fecha_hora, puesto, resultado, motivo)
                        VALUES (?, ?, ?, NOW(), ?, ?, ?)");
  $st2->execute([$rut, $tipo, $modo, $puesto, $resultado, $motivo]);

  echo json_encode([
    'ok' => true,
    'rut' => $rut,
    'tipo' => $tipo,
    'modo' => $modo,
    'puesto' => $puesto,
    'resultado' => $resultado,
    'motivo' => $motivo,
    'funcionario' => $f ? [
      'rut' => $f['rut'],
      'nombres' => $f['nombres'],
      'unidad' => $f['unidad'],
      'grado' => $f['grado'],
      'estado' => $f['estado'],
    ] : null
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
