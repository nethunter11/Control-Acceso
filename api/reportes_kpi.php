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
      COUNT(*) AS total,
      SUM(resultado='APROBADO') AS aprobados,
      SUM(resultado='RECHAZADO') AS rechazados
    FROM registros_acceso
    WHERE tipo='ENTRADA'
      AND fecha_hora >= ? AND fecha_hora <= ?
  ");
  $st->execute([$dt1, $dt2]);
  $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'aprobados'=>0,'rechazados'=>0];

  $total = (int)$r['total'];
  $ap = (int)$r['aprobados'];
  $re = (int)$r['rechazados'];
  $pct = ($total > 0) ? round(($ap / $total) * 100, 1) : 0;

  echo json_encode(['ok'=>true,'kpi'=>[
    'total'=>$total,
    'aprobados'=>$ap,
    'rechazados'=>$re,
    'pct_aprob'=>$pct
  ]], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}