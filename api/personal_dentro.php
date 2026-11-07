<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_api_roles(['ADMIN','GUARDIA']);

try {
  // La presencia depende del último movimiento aprobado de todo el historial.
  // ID resuelve empates de fecha; un rechazo nunca cambia el estado físico.
  $sql = "
    WITH ultimos AS (
      SELECT rut, tipo, modo, puesto, fecha_hora, id,
        ROW_NUMBER() OVER (PARTITION BY rut ORDER BY fecha_hora DESC, id DESC) AS rn
      FROM registros_acceso
      WHERE resultado = 'APROBADO'
    )
    SELECT t.rut, f.nombres, f.unidad, f.grado, f.estado,
      t.modo, t.puesto, t.fecha_hora AS ultima_hora
    FROM ultimos t
    INNER JOIN funcionarios f ON f.rut = t.rut
    WHERE t.rn = 1 AND t.tipo = 'ENTRADA'
    ORDER BY t.fecha_hora DESC, t.id DESC
  ";
  $rows = db()->query($sql)->fetchAll();
  echo json_encode(['ok'=>true, 'fecha'=>date('Y-m-d'), 'checked_at'=>date('Y-m-d H:i:s'), 'items'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  http_response_code(500);
  error_log('Personal dentro: ' . $error->getMessage());
  echo json_encode(['ok'=>false, 'error'=>'No se pudo consultar el personal dentro.'], JSON_UNESCAPED_UNICODE);
}