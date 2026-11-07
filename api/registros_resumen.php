<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_api_roles(['ADMIN','GUARDIA','AUDITOR']);

try {
  $rut = trim($_GET['rut'] ?? '');
  $fecha = $_GET['fecha'] ?? '';
  $resultado = trim($_GET['resultado'] ?? '');
  $limit = max(1, min(200, intval($_GET['limit'] ?? 50)));
  $where = [];
  $params = [];
  if ($rut !== '') {
    $where[] = 'e.rut LIKE ?';
    $params[] = '%' . $rut . '%';
  }
  if ($fecha !== '') {
    [$start, $end] = limites_dia_chile($fecha);
    $where[] = '((e.fecha_hora >= ? AND e.fecha_hora < ?) OR (s.fecha_hora >= ? AND s.fecha_hora < ?))';
    $params = array_merge($params, [$start, $end, $start, $end]);
  }
  if (in_array($resultado, ['APROBADO','RECHAZADO'], true)) {
    $where[] = 'e.resultado = ?';
    $params[] = $resultado;
  }

  // Solo dos movimientos aprobados consecutivos ENTRADA -> SALIDA forman una pareja.
  // Los rechazos y las salidas antiguas sin entrada permanecen como filas individuales.
  $sql = "
    WITH aprobados AS (
      SELECT id, tipo,
        LAG(tipo) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS anterior_tipo,
        LEAD(tipo) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS siguiente_tipo,
        LEAD(id) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS siguiente_id
      FROM registros_acceso WHERE resultado = 'APROBADO'
    ), filas AS (
      SELECT id, CASE WHEN tipo = 'ENTRADA' AND siguiente_tipo = 'SALIDA' THEN siguiente_id END AS salida_id,
        CASE WHEN siguiente_tipo = 'ENTRADA' AND tipo = 'ENTRADA' THEN 'Entrada seguida de otra entrada aprobada'
             WHEN tipo = 'SALIDA' THEN 'Salida sin entrada consecutiva registrada' END AS incidencia
      FROM aprobados
      WHERE tipo = 'ENTRADA' OR (tipo = 'SALIDA' AND COALESCE(anterior_tipo, '') <> 'ENTRADA')
      UNION ALL
      SELECT id, NULL, NULL FROM registros_acceso WHERE resultado = 'RECHAZADO'
    )
    SELECT e.id, e.tipo AS tipo_movimiento, e.fecha_hora AS fecha_movimiento,
      e.rut,
      CASE WHEN e.tipo = 'ENTRADA' THEN e.fecha_hora END AS entrada,
      CASE WHEN e.tipo = 'SALIDA' THEN e.fecha_hora ELSE s.fecha_hora END AS salida,
      e.modo, e.puesto, s.puesto AS puesto_salida, e.resultado, e.motivo, filas.incidencia,
      CASE WHEN e.tipo = 'ENTRADA' THEN e.operador_id END AS operador_entrada_id,
      CASE WHEN e.tipo = 'SALIDA' THEN e.operador_id ELSE s.operador_id END AS operador_salida_id,
      CASE WHEN e.tipo = 'ENTRADA' THEN ue.username END AS operador_entrada,
      CASE WHEN e.tipo = 'SALIDA' THEN ue.username ELSE us.username END AS operador_salida
    FROM filas
    INNER JOIN registros_acceso e ON e.id = filas.id
    LEFT JOIN registros_acceso s ON s.id = filas.salida_id
    LEFT JOIN usuarios ue ON ue.id = e.operador_id
    LEFT JOIN usuarios us ON us.id = s.operador_id
  ";
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY e.fecha_hora DESC, e.id DESC LIMIT ' . $limit;
  $st = db()->prepare($sql);
  $st->execute($params);
  echo json_encode(['ok'=>true, 'items'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  http_response_code(500);
  error_log('Historial de accesos: ' . $error->getMessage());
  echo json_encode(['ok'=>false, 'error'=>'No se pudo consultar el historial.'], JSON_UNESCAPED_UNICODE);
}