<?php
// Requiere respaldo SQL de pruebas con restauración comprobada y servidor iniciado.
// Ejecutar: C:\xampp\php\php.exe tests\operadores.php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/puestos.php';
require_once __DIR__.'/support/security.php';

function operator_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function operator_request(string $fixture, string $path, ?array $payload = null): array {
  $http = curl_init('http://localhost:8081/Control-Acceso/' . $path);
  curl_setopt_array($http, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_COOKIE => app_config()['session']['name'] . '=' . $fixture,
  ]);
  if ($payload !== null) {
    curl_setopt_array($http, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json','X-CSRF-Token: '.test_session_token($fixture)],
      CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    ]);
  }
  try {
    $body = curl_exec($http);
    if ($body === false) {
      throw new RuntimeException('No se pudo consultar el servidor de pruebas: ' . curl_error($http));
    }
    return ['status'=>curl_getinfo($http, CURLINFO_HTTP_CODE), 'body'=>$body];
  } finally {
    curl_close($http);
  }
}

function operator_json(string $fixture, string $path, ?array $payload = null): array {
  $response = operator_request($fixture, $path, $payload);
  operator_assert($response['status'] === 200, 'Respuesta HTTP inesperada en ' . $path);
  $json = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
  operator_assert(($json['ok'] ?? false) === true, 'La operación no fue aceptada en ' . $path);
  return $json;
}

function operator_unused_rut(PDO $pdo, array $reserved): string {
  for ($attempt=0; $attempt<100; $attempt++) {
    $number = random_int(80000000, 98999999);
    foreach (array_merge(range(0, 9), ['K']) as $dv) {
      $rut = $number . '-' . $dv;
      if (!rut_es_valido($rut) || in_array($rut, $reserved, true)) {
        continue;
      }
      $lookup = $pdo->prepare('SELECT (SELECT COUNT(*) FROM funcionarios WHERE rut=?) + (SELECT COUNT(*) FROM registros_acceso WHERE rut=?)');
      $lookup->execute([$rut, $rut]);
      if ((int)$lookup->fetchColumn() === 0) {
        return $rut;
      }
    }
  }
  throw new RuntimeException('No se pudo reservar un RUT exclusivo para la prueba.');
}

$pdo = db();
$config = app_config();
$marker = 'QA_OP_' . bin2hex(random_bytes(6));
$fixtures = [];
$staff = [];
$reserved = [];
$failed = false;
$before = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);

try {
  $operators = [];
  $lookup = $pdo->prepare('SELECT id, username, rol FROM usuarios WHERE rol=? AND activo=1 ORDER BY id LIMIT 1');
  foreach (['ADMIN','GUARDIA','AUDITOR'] as $role) {
    $lookup->execute([$role]);
    $operator = $lookup->fetch();
    operator_assert($operator !== false, 'Se necesita una cuenta activa con rol ' . $role . ' en pruebas.');
    $operators[$role] = $operator;
    session_id(bin2hex(random_bytes(16)));
    auth_login($operator);
    $fixtures[$role] = session_id();
    session_write_close();
  }
  session_id(bin2hex(random_bytes(16)));
  auth_start_session();
  $_SESSION = ['user'=>['id'=>0,'username'=>'sesion_incompleta','rol'=>'ADMIN']];
  $fixtures['INCOMPLETA'] = session_id();
  session_write_close();

  for ($i=0; $i<4; $i++) {
    $reserved[] = operator_unused_rut($pdo, $reserved);
  }
  [$activeRut,$inactiveRut,$withoutEntryRut,$legacyRut] = $reserved;
  $insertStaff = $pdo->prepare('INSERT INTO funcionarios (rut,nombres,estado) VALUES (?,?,?)');
  foreach ($reserved as $rut) {
    $insertStaff->execute([$rut,$marker,$rut === $inactiveRut ? 'INACTIVO' : 'ACTIVO']);
    $staff[] = $rut;
  }
  $legacy = $pdo->prepare("INSERT INTO registros_acceso (rut,tipo,modo,fecha_hora,puesto,operador_id,resultado) VALUES (?,'ENTRADA','PEATONAL',NOW(),?,NULL,'APROBADO')");
  $legacy->execute([$legacyRut,$marker]);

  $entry = operator_json($fixtures['ADMIN'], 'api/registrar_evento.php', ['rut'=>$activeRut,'tipo'=>'ENTRADA','puesto'=>PUESTOS_ACCESO[0],'operador_id'=>$operators['GUARDIA']['id']]);
  operator_assert($entry['resultado'] === 'APROBADO' && ($entry['guardado'] ?? false) && (int)$entry['operador_id'] === (int)$operators['ADMIN']['id'], 'La entrada no identificó al operador de la sesión.');
  $duplicate = operator_json($fixtures['GUARDIA'], 'api/registrar_evento.php', ['rut'=>$activeRut,'tipo'=>'ENTRADA','puesto'=>PUESTOS_ACCESO[0]]);
  operator_assert($duplicate['resultado'] === 'RECHAZADO' && $duplicate['guardado'] === false, 'Una solicitud duplicada creó un nuevo registro.');
  $exit = operator_json($fixtures['GUARDIA'], 'api/registrar_evento.php', ['rut'=>$activeRut,'tipo'=>'SALIDA','puesto'=>PUESTOS_ACCESO[0],'operador_id'=>$operators['ADMIN']['id']]);
  operator_assert($exit['resultado'] === 'APROBADO' && (int)$exit['operador_id'] === (int)$operators['GUARDIA']['id'], 'La salida no identificó al segundo operador.');
  echo "OK: entrada y salida conservan operadores distintos e ignoran un ID falsificado en la petición\n";

  $stored = $pdo->prepare('SELECT tipo, operador_id FROM registros_acceso WHERE rut=? ORDER BY id');
  $stored->execute([$activeRut]);
  $movements = $stored->fetchAll();
  operator_assert(count($movements) === 2 && (int)$movements[0]['operador_id'] === (int)$operators['ADMIN']['id'] && (int)$movements[1]['operador_id'] === (int)$operators['GUARDIA']['id'], 'La base no conservó los operadores correctos o el duplicado alteró el historial.');

  foreach ([[$inactiveRut,'ENTRADA','ADMIN'],[$withoutEntryRut,'SALIDA','GUARDIA']] as [$rut,$type,$role]) {
    $rejected = operator_json($fixtures[$role], 'api/registrar_evento.php', ['rut'=>$rut,'tipo'=>$type,'puesto'=>PUESTOS_ACCESO[0]]);
    operator_assert($rejected['resultado'] === 'RECHAZADO' && $rejected['guardado'] === true && (int)$rejected['operador_id'] === (int)$operators[$role]['id'], 'Un rechazo guardado perdió al operador.');
    $stored->execute([$rut]);
    $saved = $stored->fetchAll();
    operator_assert(count($saved) === 1 && (int)$saved[0]['operador_id'] === (int)$operators[$role]['id'], 'El rechazo no guardó correctamente al operador en MariaDB.');
  }
  echo "OK: también se identifica al operador de los rechazos que sí se guardan\n";

  $invalidSession = operator_request($fixtures['INCOMPLETA'], 'api/registrar_evento.php', ['rut'=>$activeRut,'tipo'=>'ENTRADA','puesto'=>PUESTOS_ACCESO[0]]);
  $invalidBody = json_decode($invalidSession['body'], true, 512, JSON_THROW_ON_ERROR);
  operator_assert($invalidSession['status'] === 401 && ($invalidBody['ok'] ?? true) === false, 'Una sesión sin identificador de operador pudo registrar un movimiento.');

  foreach (['ADMIN','GUARDIA','AUDITOR'] as $reader) {
    $latest = operator_json($fixtures[$reader], 'api/ultimos.php?rut=' . urlencode($activeRut));
    operator_assert(count($latest['items']) === 2, 'La consulta de historial perdió o duplicó movimientos.');
    foreach ($latest['items'] as $movement) {
      $expected = $operators[$movement['tipo'] === 'ENTRADA' ? 'ADMIN' : 'GUARDIA'];
      operator_assert((int)$movement['operador_id'] === (int)$expected['id'] && $movement['operador'] === $expected['username'], 'El historial muestra un operador incorrecto.');
    }
    $summary = operator_json($fixtures[$reader], 'api/registros_resumen.php?rut=' . urlencode($activeRut));
    operator_assert(count($summary['items']) === 1, 'Cambió el emparejamiento del resumen.');
    $row = $summary['items'][0];
    operator_assert((int)$row['operador_entrada_id'] === (int)$operators['ADMIN']['id'] && $row['operador_entrada'] === $operators['ADMIN']['username'] && (int)$row['operador_salida_id'] === (int)$operators['GUARDIA']['id'] && $row['operador_salida'] === $operators['GUARDIA']['username'], 'El resumen no distingue a ambos operadores.');
  }
  echo "OK: ADMIN, GUARDIA y AUDITOR ven quién registró cada movimiento y cada lado del resumen\n";

  $legacyRows = operator_json($fixtures['ADMIN'], 'api/ultimos.php?rut=' . urlencode($legacyRut));
  operator_assert(count($legacyRows['items']) === 1 && $legacyRows['items'][0]['operador_id'] === null && $legacyRows['items'][0]['operador'] === null, 'Un movimiento antiguo fue ocultado o atribuido sin evidencia.');
  $legacySummary = operator_json($fixtures['ADMIN'], 'api/registros_resumen.php?rut=' . urlencode($legacyRut));
  operator_assert(count($legacySummary['items']) === 1 && $legacySummary['items'][0]['operador_entrada'] === null && $legacySummary['items'][0]['salida'] === null, 'Cambió el comportamiento de un registro antiguo sin salida.');
  echo "OK: los registros antiguos sin operador permanecen disponibles y sin atribución inventada\n";

  $indexPage = operator_request($fixtures['ADMIN'], 'index.php');
  $recordsPage = operator_request($fixtures['ADMIN'], 'registros.php');
  operator_assert($indexPage['status'] === 200 && str_contains($indexPage['body'], 'id="rut"'), 'La pantalla de validación dejó de estar disponible.');
  operator_assert($recordsPage['status'] === 200 && str_contains($recordsPage['body'], 'id="tabla"') && str_contains($recordsPage['body'], '<th>Operador entrada</th>') && str_contains($recordsPage['body'], '<th>Operador salida</th>') && str_contains($recordsPage['body'], 'Sin registro'), 'La pantalla de registros no distingue los operadores.');
  echo "OK: la pantalla de registros incluye las nuevas columnas y validación sigue disponible\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . "\n");
  $failed = true;
} finally {
  foreach ($reserved as $rut) {
    $pdo->prepare('DELETE FROM registros_acceso WHERE rut=?')->execute([$rut]);
  }
  foreach ($staff as $rut) {
    $pdo->prepare('DELETE FROM funcionarios WHERE rut=? AND nombres=?')->execute([$rut,$marker]);
  }
  foreach ($fixtures as $fixtureId) {
    $file = $config['session']['save_path'] . DIRECTORY_SEPARATOR . 'sess_' . $fixtureId;
    if (is_file($file)) {
      unlink($file);
    }
  }
  $after = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
  if ($before !== $after) {
    fwrite(STDERR, "Los datos de pruebas no coinciden con su estado inicial.\n");
    $failed = true;
  } else {
    echo "OK: datos y sesiones temporales retirados; las cuatro tablas conservan su checksum\n";
  }
}
exit($failed ? 1 : 0);
