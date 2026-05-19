<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();

  $fecha = trim($_GET['fecha'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['ok'=>false,'error'=>'Fecha inválida'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $dt1 = $fecha . " 00:00:00";
  $dt2 = $fecha . " 23:59:59";

  $st = $pdo->prepare("
    SELECT HOUR(fecha_hora) AS hora, COUNT(*) AS total
    FROM registros_acceso
    WHERE tipo='ENTRADA'
      AND fecha_hora >= ? AND fecha_hora <= ?
      AND resultado='APROBADO'
    GROUP BY HOUR(fecha_hora)
    ORDER BY hora
  ");
  $st->execute([$dt1, $dt2]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}