<?php
require_once __DIR__.'/../app/auth.php';
require_api_roles(['ADMIN']);
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405); header('Allow: GET');
  echo json_encode(['ok'=>false,'error'=>'Método no permitido.'],JSON_UNESCAPED_UNICODE); exit;
}
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/monitor_sistema.php';
try {
  $state = monitor_snapshot(monitor_directory(),static function (): array {
    $metrics = monitor_read_windows();
    $database = ['ok'=>false,'latency_ms'=>null,'version'=>null];
    try {
      $start=microtime(true);
      $pdo=db(); $pdo->query('SELECT 1');
      $database=['ok'=>true,'latency_ms'=>round((microtime(true)-$start)*1000,1),'version'=>(string)$pdo->query('SELECT VERSION()')->fetchColumn()];
    } catch (Throwable $error) { /* La vista informa desconexión sin exponer credenciales. */ }
    return ['metrics'=>$metrics,'database'=>$database];
  });
  $age=max(0,time()-$state['sample']['timestamp']);
  echo json_encode([
    'ok'=>true,'sample'=>$state['sample'],'history'=>$state['history'],'cached'=>$state['cached'],
    'age_seconds'=>$age,'stale'=>$age>30,'interval_seconds'=>MONITOR_INTERVAL_SECONDS,
    'retention_seconds'=>MONITOR_HISTORY_SECONDS,'runtime'=>['os'=>PHP_OS_FAMILY,'php'=>PHP_VERSION],
  ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
  error_log('Diagnóstico del sistema: '.$error->getMessage());
  http_response_code(503);
  echo json_encode(['ok'=>false,'error'=>'No se pudo actualizar el diagnóstico. Inténtalo nuevamente.'],JSON_UNESCAPED_UNICODE);
}
