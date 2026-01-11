<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();

  $rut = trim($_GET['rut'] ?? '');
  $fecha = trim($_GET['fecha'] ?? ''); // YYYY-MM-DD
  $resultado = trim($_GET['resultado'] ?? ''); // opcional
  $limit = intval($_GET['limit'] ?? 50);
  if ($limit < 1) $limit = 50;
  if ($limit > 200) $limit = 200;

  // Construimos filtros (se aplican sobre la ENTRADA)
  $where = [];
  $params = [];

  if ($rut !== '') {
    $where[] = "e.rut LIKE ?";
    $params[] = "%" . $rut . "%";
  }

  if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
  $where[] = "e.entrada >= ? AND e.entrada <= ?";
  $params[] = $fecha . " 00:00:00";
  $params[] = $fecha . " 23:59:59";
  }


  // Si quieres filtrar APROBADO/RECHAZADO en esta vista (normalmente será APROBADO)
  if ($resultado !== '' && in_array($resultado, ['APROBADO','RECHAZADO'], true)) {
    $where[] = "e.resultado = ?";
    $params[] = $resultado;
  }

  // Empareja ENTRADA #1 con SALIDA #1 por RUT (usando ROW_NUMBER)
  // IMPORTANTE: esto asume tu regla de “no permitir doble ENTRADA/SALIDA aprobada” (alternancia).
  $sql = "
    WITH entradas AS (
      SELECT
        id,
        rut,
        modo,
        puesto,
        resultado,
        motivo,
        fecha_hora AS entrada,
        ROW_NUMBER() OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS rn
      FROM registros_acceso
      WHERE tipo = 'ENTRADA'
    ),
    salidas AS (
      SELECT
        id,
        rut,
        fecha_hora AS salida,
        ROW_NUMBER() OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS rn
      FROM registros_acceso
      WHERE tipo = 'SALIDA'
    )
    SELECT
      e.rut,
      DATE_FORMAT(e.entrada, '%H:%i:%s') AS entrada,
      DATE_FORMAT(s.salida,  '%H:%i:%s') AS salida,
      e.modo,
      e.puesto,
      e.resultado,
      e.motivo,
      e.entrada AS _orden
    FROM entradas e
    LEFT JOIN salidas s
      ON s.rut = e.rut AND s.rn = e.rn
  ";

  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }

  $sql .= " ORDER BY _orden DESC LIMIT " . $limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
