<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/auth.php';
require_api_roles(['ADMIN','AUDITOR']);
require_once __DIR__ . '/../app/db.php';

try {
  $pdo = db();

  [$dt1, $dt2] = limites_dia_chile($_GET['fecha'] ?? '');

  $st = $pdo->prepare("
    SELECT
      HOUR(fecha_hora) AS hora,
      SUM(tipo='ENTRADA') AS entradas,
      SUM(tipo='SALIDA') AS salidas
    FROM registros_acceso
    WHERE tipo IN ('ENTRADA','SALIDA')
      AND fecha_hora >= ? AND fecha_hora < ?
      AND resultado='APROBADO'
    GROUP BY HOUR(fecha_hora)
    ORDER BY hora
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
