<?php
require_once __DIR__ . '/../app/auth.php';
require_api_roles(['ADMIN']);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/exportaciones.php';

$out = null;
try {
  $export = preparar_exportacion_csv($_GET);
  $pdo = db();
  $st = $pdo->prepare($export['sql']);
  $st->execute($export['params']);
  $out = fopen('php://temp/maxmemory:2097152', 'w+b');
  if ($out === false) {
    throw new RuntimeException('No se pudo preparar el archivo.');
  }
  fwrite($out, "\xEF\xBB\xBF");
  if (fputcsv($out, $export['columns'], ',', '"', '', "\r\n") === false) {
    throw new RuntimeException('No se pudieron escribir los encabezados.');
  }
  $count = 0;
  while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $values = array_map(fn($column) => celda_csv_texto($row[$column]), $export['columns']);
    if (fputcsv($out, $values, ',', '"', '', "\r\n") === false) {
      throw new RuntimeException('No se pudo completar el archivo.');
    }
    $count++;
  }
  rewind($out);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
  header('X-Content-Type-Options: nosniff');
  header('X-Export-Row-Count: ' . $count);
  fpassthru($out);
} catch (InvalidArgumentException $error) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log('Error al generar CSV: ' . $error->getMessage());
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>'No se pudo generar el CSV. Inténtalo nuevamente.'], JSON_UNESCAPED_UNICODE);
} finally {
  if (is_resource($out)) {
    fclose($out);
  }
}
