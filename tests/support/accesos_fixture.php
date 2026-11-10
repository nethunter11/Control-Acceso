<?php
// Datos y sesiones exclusivos de pruebas. Requiere respaldo SQL restaurado y verificado.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../../app/db.php';
require_once __DIR__.'/../../app/auth.php';
require_once __DIR__.'/../../app/rut.php';
require_once __DIR__.'/security.php';

function qa_check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
function qa_http(?string $session, string $path, ?array $payload = null, ?string $method = null, bool $csrf = true): array {
  $http = curl_init('http://127.0.0.1:8081/Control-Acceso/'.$path);
  $receivedHeaders = [];
  curl_setopt_array($http, [
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10,
    CURLOPT_COOKIE=>app_config()['session']['name'].'='.($session ?? ''),
    CURLOPT_HEADERFUNCTION=>static function ($handle,$line) use (&$receivedHeaders) { $receivedHeaders[]=$line; return strlen($line); },
  ]);
  $headers = [];
  if ($csrf && $session !== null) $headers[] = 'X-CSRF-Token: '.test_session_token($session);
  if ($payload !== null) { $headers[] = 'Content-Type: application/json'; curl_setopt($http,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR)); }
  if ($method !== null) curl_setopt($http,CURLOPT_CUSTOMREQUEST,$method);
  curl_setopt($http,CURLOPT_HTTPHEADER,$headers);
  try {
    $body = curl_exec($http);
    if ($body === false) throw new RuntimeException('Servidor de pruebas no disponible: '.curl_error($http));
    return ['status'=>curl_getinfo($http, CURLINFO_HTTP_CODE), 'body'=>$body, 'headers'=>$receivedHeaders];
  } finally { curl_close($http); }
}
function qa_json(array $qa, string $path, int $status = 200, string $role = 'ADMIN'): array {
  $response = qa_http($qa['sessions'][$role] ?? null, $path);
  qa_check($response['status'] === $status, 'HTTP inesperado en '.$path.': '.$response['status']);
  $json = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
  if ($status === 200) qa_check(($json['ok'] ?? false) === true, 'Operación rechazada en '.$path);
  return $json;
}
function qa_staff(array &$qa, string $state = 'ACTIVO'): string {
  for ($i=0; $i<200; $i++) {
    $number = random_int(80000000, 98999999);
    foreach (array_merge(range(0,9), ['K']) as $dv) {
      $rut = $number.'-'.$dv;
      if (!rut_es_valido($rut)) continue;
      $rut=(string)$number;
      $query = $qa['pdo']->prepare('SELECT COUNT(*) FROM funcionarios WHERE rut=?');
      $query->execute([$rut]);
      if ($query->fetchColumn() != 0) continue;
      $qa['pdo']->prepare('INSERT INTO funcionarios (rut,nombres,estado) VALUES (?,?,?)')->execute([$rut,$qa['marker'],$state]);
      $qa['staff'][] = $rut;
      return $rut;
    }
  }
  throw new RuntimeException('No se pudo reservar un RUT de prueba.');
}
function qa_rut_completo(string $number): string {
  foreach(array_merge(range(0,9),['K']) as $dv)if(rut_es_valido($number.'-'.$dv))return $number.'-'.$dv;
  throw new RuntimeException('No se pudo calcular el DV del visitante de prueba.');
}
function qa_event(array $qa, string $rut, string $type, string $when, string $result = 'APROBADO', ?string $role = 'ADMIN'): int {
  $query = $qa['pdo']->prepare("INSERT INTO registros_acceso (rut,tipo,fecha_hora,resultado,operador_id,puesto,motivo) VALUES (?,?,?,?,?,?,?)");
  $query->execute([$rut,$type,$when,$result,$role ? $qa['users'][$role]['id'] : null,'Pórtico Norte',$result === 'RECHAZADO' ? 'Rechazo de prueba' : null]);
  return (int)$qa['pdo']->lastInsertId();
}
function qa_run(callable $test): void {
  $qa = ['pdo'=>db(), 'sessions'=>[], 'visits'=>[], 'staff'=>[], 'users'=>[], 'marker'=>'QA_ACCESOS_'.bin2hex(random_bytes(6))];
  $before = $qa['pdo']->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
  $failed = false;
  try {
    $lookup = $qa['pdo']->prepare('SELECT id,username,rol FROM usuarios WHERE rol=? AND activo=1 ORDER BY id LIMIT 1');
    foreach (['ADMIN','GUARDIA','AUDITOR','CONTROL_VISITA'] as $role) {
      $lookup->execute([$role]);
      $user = $lookup->fetch();
      qa_check($user !== false, 'Falta usuario de pruebas con rol '.$role);
      $qa['users'][$role] = $user;
      session_id(bin2hex(random_bytes(16)));
      auth_login($user);
      $qa['sessions'][$role] = session_id();
      session_write_close();
    }
    $test($qa);
  } catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    $failed = true;
  } finally {
    foreach ($qa['visits'] as $id) $qa['pdo']->prepare('DELETE FROM visitas WHERE id=?')->execute([$id]);
    foreach ($qa['staff'] as $rut) {
      $qa['pdo']->prepare('DELETE FROM registros_acceso WHERE rut=?')->execute([$rut]);
      $qa['pdo']->prepare('DELETE FROM funcionarios WHERE rut=? AND nombres=?')->execute([$rut,$qa['marker']]);
    }
    foreach ($qa['sessions'] as $id) {
      $file = app_config()['session']['save_path'].DIRECTORY_SEPARATOR.'sess_'.$id;
      if (is_file($file)) unlink($file);
    }
    $after = $qa['pdo']->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($before !== $after) { fwrite(STDERR,"ERROR: los datos no conservan su checksum.\n"); $failed = true; }
    else echo "OK: datos y sesiones temporales retirados; checksum intacto en las cuatro tablas\n";
  }
  exit($failed ? 1 : 0);
}