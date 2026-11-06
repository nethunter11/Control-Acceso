<?php
// Requiere respaldo SQL de pruebas con restauración comprobada y servidor iniciado.
// Ejecutar: C:\xampp\php\php.exe tests\exportaciones.php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';

function export_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function export_request(string $fixture, string $path): array {
  $headers = [];
  $http = curl_init('http://localhost:8081/Control-Acceso/' . $path);
  curl_setopt_array($http, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>10,
    CURLOPT_COOKIE=>app_config()['session']['name'] . '=' . $fixture,
    CURLOPT_HEADERFUNCTION=>function ($http, string $line) use (&$headers): int {
      $parts = explode(':',$line,2);
      if (count($parts) === 2) {
        $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
      }
      return strlen($line);
    }]);
  try {
    $body = curl_exec($http);
    if ($body === false) {
      throw new RuntimeException('No se pudo consultar el servidor de pruebas: ' . curl_error($http));
    }
    return ['status'=>curl_getinfo($http,CURLINFO_HTTP_CODE),'headers'=>$headers,'body'=>$body];
  } finally {
    curl_close($http);
  }
}

function export_csv(string $fixture, array $params): array {
  $response = export_request($fixture,'api/admin_export_csv.php?' . http_build_query($params));
  export_assert($response['status'] === 200, 'La exportación no respondió con HTTP 200.');
  export_assert(str_starts_with($response['headers']['content-type'] ?? '', 'text/csv'), 'La respuesta no es un CSV.');
  export_assert(str_starts_with($response['body'],"\xEF\xBB\xBF"), 'Falta la marca UTF-8 para Excel.');
  $stream = fopen('php://memory','w+b');
  fwrite($stream,substr($response['body'],3));
  rewind($stream);
  try {
    $columns = fgetcsv($stream,0,',','"','');
    export_assert(is_array($columns) && count($columns)>0, 'Faltan los encabezados CSV.');
    $rows = [];
    while (($values = fgetcsv($stream,0,',','"','')) !== false) {
      export_assert(count($columns) === count($values), 'El CSV perdió columnas al tratar comillas o saltos de línea.');
      $rows[] = array_combine($columns,$values);
    }
  } finally {
    fclose($stream);
  }
  export_assert((int)($response['headers']['x-export-row-count'] ?? -1) === count($rows), 'El contador no coincide con las filas descargadas.');
  return ['columns'=>$columns,'rows'=>$rows,'headers'=>$response['headers']];
}

function export_unused_rut(PDO $pdo): string {
  for ($attempt=0; $attempt<100; $attempt++) {
    $number = random_int(80000000,98999999);
    foreach (array_merge(range(0,9),['K']) as $dv) {
      $rut = $number . '-' . $dv;
      if (!rut_es_valido($rut)) continue;
      $lookup = $pdo->prepare('SELECT (SELECT COUNT(*) FROM funcionarios WHERE rut=?) + (SELECT COUNT(*) FROM registros_acceso WHERE rut=?) + (SELECT COUNT(*) FROM visitas WHERE rut=?)');
      $lookup->execute([$rut,$rut,$rut]);
      if ((int)$lookup->fetchColumn() === 0) return $rut;
    }
  }
  throw new RuntimeException('No se pudo reservar un RUT de prueba.');
}

$pdo = db();
$config = app_config();
$fixtures = [];
$rut = null;
$createdStaff = false;
$accessIds = [];
$visitIds = [];
$marker = 'QA_CSV_' . bin2hex(random_bytes(6));
$failed = false;
$before = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
try {
  $operators = [];
  foreach (['ADMIN','GUARDIA','AUDITOR','CONTROL_VISITA'] as $role) {
    $lookup = $pdo->prepare('SELECT id,username,rol FROM usuarios WHERE rol=? AND activo=1 ORDER BY id LIMIT 1');
    $lookup->execute([$role]);
    $operator = $lookup->fetch();
    export_assert($operator !== false,'Se necesita una cuenta activa con rol ' . $role . ' en pruebas.');
    $operators[$role] = $operator;
    session_id(bin2hex(random_bytes(16)));
    auth_login($operator);
    $fixtures[$role] = session_id();
    session_write_close();
  }
  session_id(bin2hex(random_bytes(16)));
  auth_start_session();
  $_SESSION = [];
  $fixtures['ANONIMO'] = session_id();
  session_write_close();

  $day = new DateTimeImmutable('2001-02-03');
  $previous = $day->modify('-1 day')->format('Y-m-d');
  $next = $day->modify('+1 day')->format('Y-m-d');
  $emptyDay = $day->modify('+10 days')->format('Y-m-d');
  $date = $day->format('Y-m-d');
  $checkDates = $pdo->prepare('SELECT (SELECT COUNT(*) FROM registros_acceso WHERE fecha_hora>=? AND fecha_hora<?) + (SELECT COUNT(*) FROM visitas WHERE (fecha_hora_entrada>=? AND fecha_hora_entrada<?) OR (fecha_hora_salida>=? AND fecha_hora_salida<?))');
  $rangeStart = $previous . ' 00:00:00';
  $rangeEnd = $day->modify('+11 days')->format('Y-m-d') . ' 00:00:00';
  $checkDates->execute([$rangeStart,$rangeEnd,$rangeStart,$rangeEnd,$rangeStart,$rangeEnd]);
  export_assert((int)$checkDates->fetchColumn() === 0, 'El periodo reservado para probar fechas ya contiene datos.');

  $rut = export_unused_rut($pdo);
  $staffName = '=1+1, "Prueba ñ"';
  $pdo->prepare("INSERT INTO funcionarios (rut,nombres,estado) VALUES (?,?,'ACTIVO')")->execute([$rut,$staffName]);
  $createdStaff = true;
  $accessInsert = $pdo->prepare("INSERT INTO registros_acceso (rut,tipo,modo,fecha_hora,puesto,operador_id,resultado,motivo) VALUES (?,?,'PEATONAL',?,'Pórtico Norte',?,'APROBADO',?)");
  $accessTimes = [$previous.' 23:59:59',$date.' 00:00:00',$date.' 23:59:59',$next.' 00:00:00'];
  $multiline = "Línea uno, con coma\n\"Tildes: áéíóúñ\"";
  foreach ($accessTimes as $index=>$time) {
    $accessInsert->execute([$rut,$index%2===0?'ENTRADA':'SALIDA',$time,$operators['ADMIN']['id'],$index===1?$multiline:'=1+1']);
    $accessIds[] = (int)$pdo->lastInsertId();
  }
  $visitInsert = $pdo->prepare("INSERT INTO visitas (nombre,rut,telefono,modo,puesto,operador_id,motivo,fecha_hora_entrada,fecha_hora_salida,estado) VALUES (?,?,?,'PEATONAL','Pórtico de Visitas',?,?,?,?,?)");
  $visitTimes = [
    [$previous.' 23:00:00',$date.' 00:00:00'],
    [$date.' 00:00:00',$date.' 12:00:00'],
    [$date.' 23:59:59',$next.' 00:00:00'],
    [$previous.' 22:00:00',$next.' 03:00:00'],
    [$next.' 00:00:00',null],
    [$date.' 14:00:00',null],
    [$previous.' 21:00:00',$date.' 23:59:59'],
  ];
  foreach ($visitTimes as [$entry,$exit]) {
    $visitInsert->execute([$marker,$rut,'+56 9 1234 5678',$operators['ADMIN']['id'],$multiline,$entry,$exit,$exit===null?'DENTRO':'SALIO']);
    $visitIds[] = (int)$pdo->lastInsertId();
  }

  $access = export_csv($fixtures['ADMIN'],['table'=>'registros_acceso','fecha'=>$date]);
  export_assert(array_map(fn($row)=>(int)$row['id'],$access['rows']) === [$accessIds[1],$accessIds[2]], 'El filtro de accesos incluyó otro día o perdió un límite de medianoche.');
  export_assert($access['rows'][0]['motivo'] === $multiline && $access['rows'][0]['puesto'] === 'Pórtico Norte' && $access['rows'][0]['operador'] === $operators['ADMIN']['username'], 'El CSV perdió tildes, comillas, saltos o el operador.');
  export_assert($access['rows'][1]['motivo'] === "\t=1+1", 'Una fórmula no se exportó como texto.');
  export_assert(str_contains($access['headers']['content-disposition'] ?? '', 'registros_acceso_'.$date.'.csv'), 'El archivo no identifica el día elegido.');
  echo "OK: accesos por día, medianoche, operador, tildes, comillas y saltos de línea\n";

  foreach (['actividad'=>[0,1,2,5,6],'entrada'=>[1,2,5],'salida'=>[0,1,6]] as $criterion=>$indexes) {
    $visits = export_csv($fixtures['ADMIN'],['table'=>'visitas','fecha'=>$date,'criterio'=>$criterion]);
    $actual = array_map(fn($row)=>(int)$row['id'],$visits['rows']);
    $expected = array_map(fn($index)=>$visitIds[$index],$indexes);
    sort($actual); sort($expected);
    export_assert($actual === $expected,'El filtro de visitas no respeta el criterio ' . $criterion . '.');
    export_assert(count(array_unique($actual)) === count($actual),'Una visita se duplicó al ingresar y salir el mismo día.');
    foreach ($visits['rows'] as $row) {
      export_assert($row['telefono'] === "\t+56 9 1234 5678" && $row['operador_entrada'] === $operators['ADMIN']['username'],'Se alteró el teléfono o el operador de ingreso en el CSV.');
    }
    echo "OK: visitas por día con criterio {$criterion}, incluidos cruces de medianoche\n";
  }
  $defaultVisits = export_csv($fixtures['ADMIN'],['table'=>'visitas','fecha'=>$date]);
  export_assert(count($defaultVisits['rows'])===5,'El criterio inicial de visitas no incluye ingresos o salidas.');
  foreach (['registros_acceso','visitas'] as $table) {
    $empty = export_csv($fixtures['ADMIN'],['table'=>$table,'fecha'=>$emptyDay]);
    export_assert(count($empty['rows'])===0 && count($empty['columns'])>0,'Un día sin resultados no conserva sus encabezados.');
    export_csv($fixtures['ADMIN'],['table'=>$table]);
  }
  $users = export_csv($fixtures['ADMIN'],['table'=>'usuarios']);
  export_assert($users['columns']===['id','username','rol','activo'],'La exportación de usuarios incluye datos de contraseña.');
  $staff = export_csv($fixtures['ADMIN'],['table'=>'funcionarios']);
  $staffRow = array_values(array_filter($staff['rows'],fn($row)=>$row['rut']===$rut));
  export_assert(count($staffRow)===1 && $staffRow[0]['nombres']==="\t".$staffName,'No se protegió el texto de nombre que comienza como fórmula.');
  echo "OK: exportaciones completas, días vacíos con encabezados y usuarios sin contraseñas\n";

  $badParams = [
    ['table'=>'desconocida'],['table'=>['usuarios']],
    ['table'=>'registros_acceso','fecha'=>'2001-02-30'],['table'=>'visitas','fecha'=>''],
    ['table'=>'registros_acceso','fecha'=>['2001-02-03']],['table'=>'visitas','fecha'=>'0999-01-01'],
    ['table'=>'visitas','fecha'=>'9999-12-31'],['table'=>'usuarios','fecha'=>$date],
    ['table'=>'visitas','fecha'=>$date,'criterio'=>'inventado'],['table'=>'registros_acceso','fecha'=>$date,'criterio'=>'entrada'],
    ['table'=>'visitas','criterio'=>'salida'],
  ];
  foreach ($badParams as $params) {
    $invalid = export_request($fixtures['ADMIN'],'api/admin_export_csv.php?'.http_build_query($params));
    export_assert($invalid['status']===400 && !isset($invalid['headers']['content-disposition']) && (json_decode($invalid['body'],true)['ok'] ?? true)===false,'Un filtro inválido no produjo un error controlado.');
  }
  foreach (['ANONIMO'=>401,'GUARDIA'=>403,'AUDITOR'=>403,'CONTROL_VISITA'=>403] as $role=>$status) {
    $forbidden = export_request($fixtures[$role],'api/admin_export_csv.php?table=registros_acceso&fecha='.$date);
    export_assert($forbidden['status']===$status && !isset($forbidden['headers']['content-disposition']),'Un usuario sin permisos pudo descargar el CSV.');
  }
  echo "OK: fechas y criterios inválidos rechazados; exportaciones restringidas a ADMIN\n";

  $settings = export_request($fixtures['ADMIN'],'configuraciones.php');
  $dom = new DOMDocument();
  $dom->loadHTML('<?xml encoding="UTF-8">'.$settings['body'],LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  $xpath = new DOMXPath($dom);
  export_assert($settings['status']===200 && $xpath->query('//*[@id="settingsAccordion"]/*[contains(@class,"accordion-item")]')->length===3,'Configuración no tiene tres secciones desplegables.');
  foreach (['settingsUsers','settingsSystem','settingsExports','usersTable','nu_username','nu_password','nu_rol','nu_activo','csvTable','csvDate','csvPeriod','csvCriterion'] as $id) {
    export_assert($xpath->query('//*[@id="'.$id.'"]')->length===1,'Falta un control o hay identificadores duplicados: '.$id);
  }
  echo "OK: Configuración conserva los controles de usuarios y separa diagnóstico y exportaciones\n";
} catch (Throwable $error) {
  fwrite(STDERR,$error->getMessage()."\n");
  $failed = true;
} finally {
  if ($rut !== null) {
    foreach ($accessIds as $id) $pdo->prepare('DELETE FROM registros_acceso WHERE id=? AND rut=?')->execute([$id,$rut]);
    foreach ($visitIds as $id) $pdo->prepare('DELETE FROM visitas WHERE id=? AND rut=?')->execute([$id,$rut]);
    if ($createdStaff) $pdo->prepare('DELETE FROM funcionarios WHERE rut=? AND nombres=?')->execute([$rut,$staffName]);
  }
  foreach ($fixtures as $fixture) {
    $file = $config['session']['save_path'].DIRECTORY_SEPARATOR.'sess_'.$fixture;
    if (is_file($file)) unlink($file);
  }
  $after = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
  if ($before !== $after) {
    fwrite(STDERR,"Los datos de pruebas no coinciden con su estado inicial.\n");
    $failed = true;
  } else {
    echo "OK: datos y sesiones temporales retirados; las cuatro tablas conservan su checksum\n";
  }
}
exit($failed?1:0);
