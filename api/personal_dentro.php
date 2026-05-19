<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['ADMIN','GUARDIA']);

try {
  $pdo = db();

  $fecha = date('Y-m-d'); // siempre hoy

  $dt1 = $fecha . " 00:00:00";
  $dt2 = $fecha . " 23:59:59";

  // 1) Tomar el último evento APROBADO de cada RUT en el día
  // 2) Quedarse solo con los que terminaron con ENTRADA (están "dentro")
  $sql = "
    SELECT t.rut,
           f.nombres,
           f.unidad,
           f.grado,
           t.modo,
           t.puesto,
           t.fecha_hora AS ultima_hora
    FROM (
      SELECT ra.rut, ra.tipo, ra.modo, ra.puesto, ra.fecha_hora
      FROM registros_acceso ra
      INNER JOIN (
        SELECT rut, MAX(fecha_hora) AS max_hora
        FROM registros_acceso
        WHERE resultado='APROBADO'
          AND fecha_hora >= ? AND fecha_hora <= ?
        GROUP BY rut
      ) last ON last.rut = ra.rut AND last.max_hora = ra.fecha_hora
      WHERE ra.resultado='APROBADO'
        AND ra.fecha_hora >= ? AND ra.fecha_hora <= ?
    ) t
    INNER JOIN funcionarios f ON f.rut = t.rut
    WHERE t.tipo = 'ENTRADA'
      AND f.estado = 'ACTIVO'
    ORDER BY t.fecha_hora DESC
  ";

  $st = $pdo->prepare($sql);
  $st->execute([$dt1,$dt2,$dt1,$dt2]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'fecha'=>$fecha,'items'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}