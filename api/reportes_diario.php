<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/auth.php';
require_api_roles(['ADMIN','AUDITOR']);
require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();

  [$dt1, $dt2] = limites_rango_chile($_GET['desde'] ?? '', $_GET['hasta'] ?? '');

  $st = $pdo->prepare("
    SELECT
      DATE(fecha_hora) AS fecha,
      COUNT(*) AS total,
      SUM(resultado='APROBADO') AS aprobados,
      SUM(resultado='RECHAZADO') AS rechazados
    FROM registros_acceso
    WHERE tipo='ENTRADA'
      AND fecha_hora >= ? AND fecha_hora < ?
    GROUP BY DATE(fecha_hora)
    ORDER BY fecha
  ");
  $st->execute([$dt1, $dt2]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $error) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
