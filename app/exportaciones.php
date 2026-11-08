<?php
require_once __DIR__ . '/fechas.php';

function preparar_exportacion_csv(array $query): array {
  $table = $query['table'] ?? '';
  if (!is_string($table) || !in_array($table, ['usuarios','funcionarios','registros_acceso','visitas'], true)) {
    throw new InvalidArgumentException('Selecciona un listado válido.');
  }

  $dated = in_array($table, ['registros_acceso','visitas'], true);
  $hasDay = array_key_exists('fecha', $query);
  $hasRange = array_key_exists('desde', $query) || array_key_exists('hasta', $query);
  $start = null;
  $end = null;
  $periodName = '';
  if (!$dated && ($hasDay || $hasRange)) {
    throw new InvalidArgumentException('Este listado se exporta completo y no admite filtro por fecha.');
  }
  if ($dated) {
    if ($hasDay && $hasRange) {
      throw new InvalidArgumentException('Selecciona un día o un rango de fechas, no ambos.');
    }
    if ($hasDay) {
      [$start, $end] = limites_dia_chile($query['fecha']);
      $periodName = '_' . $query['fecha'];
    } elseif ($hasRange) {
      [$start, $end] = limites_rango_chile($query['desde'] ?? null, $query['hasta'] ?? null);
      $periodName = '_' . $query['desde'] . '_a_' . $query['hasta'];
    } else {
      throw new InvalidArgumentException('Selecciona un día o un rango de fechas para exportar los registros.');
    }
  }

  $criterion = $query['criterio'] ?? 'actividad';
  if (!is_string($criterion) || !in_array($criterion, ['actividad','entrada','salida'], true)) {
    throw new InvalidArgumentException('Selecciona un criterio de fecha válido para las visitas.');
  }
  if (array_key_exists('criterio', $query) && $table !== 'visitas') {
    throw new InvalidArgumentException('El criterio de fecha solo está disponible para las visitas.');
  }

  $params = [];
  switch ($table) {
    case 'usuarios':
      $columns = ['id','username','rol','activo'];
      $sql = 'SELECT id, username, rol, activo FROM usuarios ORDER BY id';
      break;
    case 'funcionarios':
      $columns = ['rut','nombres','unidad','grado','estado','fecha_actualizacion'];
      $sql = 'SELECT rut, nombres, unidad, grado, estado, fecha_actualizacion FROM funcionarios ORDER BY nombres, rut';
      break;
    case 'registros_acceso':
      $columns = ['id','rut','tipo','modo','fecha_hora','puesto','operador_id','operador','resultado','motivo'];
      $sql = 'SELECT r.id, r.rut, r.tipo, r.modo, r.fecha_hora, r.puesto, r.operador_id, u.username AS operador, r.resultado, r.motivo FROM registros_acceso r LEFT JOIN usuarios u ON u.id=r.operador_id';
      if ($start !== null) {
        $sql .= ' WHERE r.fecha_hora >= ? AND r.fecha_hora < ?';
        $params = [$start,$end];
      }
      $sql .= ' ORDER BY r.fecha_hora, r.id';
      break;
    case 'visitas':
      $columns = ['id','nombre','rut','telefono','modo','patente','puesto','operador_id','operador_entrada','operador_salida_id','operador_salida','motivo','fecha_hora_entrada','fecha_hora_salida','estado'];
      $sql = 'SELECT v.id, v.nombre, v.rut, v.telefono, v.modo, v.patente, v.puesto, v.operador_id, u.username AS operador_entrada, v.operador_salida_id, us.username AS operador_salida, v.motivo, v.fecha_hora_entrada, v.fecha_hora_salida, v.estado FROM visitas v LEFT JOIN usuarios u ON u.id=v.operador_id LEFT JOIN usuarios us ON us.id=v.operador_salida_id';
      if ($start !== null) {
        if ($criterion === 'actividad') {
          $sql .= ' WHERE ((v.fecha_hora_entrada >= ? AND v.fecha_hora_entrada < ?) OR (v.fecha_hora_salida >= ? AND v.fecha_hora_salida < ?))';
          $params = [$start,$end,$start,$end];
        } else {
          $dateColumn = $criterion === 'entrada' ? 'v.fecha_hora_entrada' : 'v.fecha_hora_salida';
          $sql .= " WHERE {$dateColumn} >= ? AND {$dateColumn} < ?";
          $params = [$start,$end];
        }
      }
      $sql .= ' ORDER BY v.fecha_hora_entrada, v.id';
      break;
  }

  $filename = $table . $periodName;
  if ($table === 'visitas' && $start !== null) {
    $filename .= '_' . $criterion;
  }
  return ['sql'=>$sql,'params'=>$params,'columns'=>$columns,'filename'=>$filename . '.csv'];
}

function celda_csv_texto(mixed $value): string {
  $text = $value === null ? '' : (string)$value;
  // Mantener como texto en Excel los valores que podrían interpretarse como fórmula.
  if (preg_match('/^[\x00-\x20\x{FEFF}]*[=+@\-＝＋＠－]/u', $text)
      || preg_match('/^[\t\r\n]/', $text)) {
    return "\t" . $text;
  }
  return $text;
}
