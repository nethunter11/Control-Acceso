<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();

  $desde = trim($_GET['desde'] ?? '');
  $hasta = trim($_GET['hasta'] ?? '');

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    echo json_encode(['ok'=>false,'error'=>'Rango inválido'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $dt1 = $desde . " 00:00:00";
  $dt2 = $hasta . " 23:59:59";

  $st = $pdo->prepare("
    SELECT
      DATE(fecha_hora) AS fecha,
      COUNT(*) AS total,
      SUM(resultado='APROBADO') AS aprobados,
      SUM(resultado='RECHAZADO') AS rechazados
    FROM registros_acceso
    WHERE tipo='ENTRADA'
      AND fecha_hora >= ? AND fecha_hora <= ?
    GROUP BY DATE(fecha_hora)
    ORDER BY fecha
  ");
  $st->execute([$dt1, $dt2]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}