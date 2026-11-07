<?php
// Requiere respaldo SQL de pruebas con restauración comprobada y servidor iniciado.
// Ejecutar: C:\xampp\php\php.exe tests\puestos.php
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

function gate_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function gate_request(string $fixture, string $path, ?array $payload = null, bool $json = true): array {
  $http = curl_init('http://localhost:8081/Control-Acceso/' . $path);
  curl_setopt_array($http, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
    CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>8,
    CURLOPT_COOKIE=>app_config()['session']['name'] . '=' . $fixture]);
  if ($payload !== null) {
    curl_setopt_array($http, [CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>['Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded'),'X-CSRF-Token: '.test_session_token($fixture)],
      CURLOPT_POSTFIELDS=>$json ? json_encode($payload, JSON_THROW_ON_ERROR) : http_build_query($payload)]);
  }
  try {
    $body = curl_exec($http);
    if ($body === false) {
      throw new RuntimeException('No se pudo consultar el servidor de pruebas: ' . curl_error($http));
    }
    return ['status'=>curl_getinfo($http, CURLINFO_HTTP_CODE), 'body'=>$body, 'json'=>json_decode($body, true)];
  } finally {
    curl_close($http);
  }
}

function gate_unused_rut(PDO $pdo, array $reserved): string {
  for ($attempt=0; $attempt<100; $attempt++) {
    $number = random_int(80000000, 98999999);
    foreach (array_merge(range(0,9), ['K']) as $dv) {
      $rut = $number . '-' . $dv;
      if (!rut_es_valido($rut) || in_array($rut, $reserved, true)) {
        continue;
      }
      $lookup = $pdo->prepare('SELECT (SELECT COUNT(*) FROM funcionarios WHERE rut=?) + (SELECT COUNT(*) FROM registros_acceso WHERE rut=?) + (SELECT COUNT(*) FROM visitas WHERE rut=?)');
      $lookup->execute([$rut,$rut,$rut]);
      if ((int)$lookup->fetchColumn() === 0) {
        return $rut;
      }
    }
  }
  throw new RuntimeException('No se pudo reservar un RUT exclusivo para la prueba.');
}

$pdo = db();
$config = app_config();
$marker = 'QA_PUESTOS_' . bin2hex(random_bytes(6));
$reserved = [];
$staff = [];
$fixture = null;
$failed = false;
$before = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
try {
  $operator = $pdo->query("SELECT id,username,rol FROM usuarios WHERE rol='ADMIN' AND activo=1 ORDER BY id LIMIT 1")->fetch();
  gate_assert($operator !== false, 'Se necesita una cuenta ADMIN activa en pruebas.');
  session_id(bin2hex(random_bytes(16)));
  auth_login($operator);
  $fixture = session_id();
  session_write_close();

  $indexPage = gate_request($fixture, 'index.php');
  gate_assert($indexPage['status'] === 200, 'No se pudo abrir Validación de acceso.');
  $indexDom = new DOMDocument();
  $indexDom->loadHTML('<?xml encoding="UTF-8">' . $indexPage['body'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
  $indexXpath = new DOMXPath($indexDom);
  $options = $indexXpath->query('//select[@id="puesto"]/option');
  $values = [];
  foreach ($options as $option) {
    $values[] = $option->getAttribute('value');
    gate_assert(trim($option->textContent) === $option->getAttribute('value'), 'Una etiqueta de puesto no coincide con su valor.');
  }
  gate_assert($values === ['Pórtico Principal','Pórtico Sur','Pórtico Norte'], 'El desplegable no contiene exactamente las tres opciones solicitadas.');
  gate_assert($indexXpath->query('//input[@id="puesto"]')->length === 0, 'Puesto todavía permite texto libre.');
  $visitsPage = gate_request($fixture, 'visitas.php');
  gate_assert($visitsPage['status'] === 200 && !str_contains($visitsPage['body'], 'v_puesto'), 'Visitas todavía contiene el campo editable de puesto o una referencia a él.');
  echo "OK: Validación muestra exactamente los tres pórticos y Visitas no tiene el campo editable\n";

  for ($i=0; $i<4; $i++) {
    $reserved[] = gate_unused_rut($pdo, $reserved);
  }
  $insertStaff = $pdo->prepare("INSERT INTO funcionarios (rut,nombres,estado) VALUES (?,?,'ACTIVO')");
  foreach (array_slice($reserved,0,3) as $rut) {
    $insertStaff->execute([$rut,$marker]);
    $staff[] = $rut;
  }
  $latestGate = $pdo->prepare('SELECT puesto FROM registros_acceso WHERE rut=? ORDER BY id DESC LIMIT 1');
  foreach (PUESTOS_ACCESO as $index => $gate) {
    foreach ([true,false] as $json) {
      $response = gate_request($fixture, 'api/registrar_evento.php', ['rut'=>$staff[$index],'tipo'=>$json ? 'ENTRADA' : 'SALIDA','puesto'=>$gate], $json);
      gate_assert($response['status'] === 200 && ($response['json']['resultado'] ?? '') === 'APROBADO' && $response['json']['puesto'] === $gate, 'No se pudo registrar el pórtico seleccionado.');
      $latestGate->execute([$staff[$index]]);
      gate_assert($latestGate->fetchColumn() === $gate, 'El pórtico guardado en MariaDB no coincide.');
    }
    echo "OK: {$gate}, entrada y salida, JSON y formulario\n";
  }
  $default = gate_request($fixture, 'api/registrar_evento.php', ['rut'=>$staff[0],'tipo'=>'ENTRADA']);
  gate_assert($default['status'] === 200 && $default['json']['puesto'] === 'Pórtico Principal' && $default['json']['resultado'] === 'APROBADO', 'El pórtico inicial no es Principal.');
  $trimmed = gate_request($fixture, 'api/registrar_evento.php', ['rut'=>$staff[0],'tipo'=>'SALIDA','puesto'=>' Pórtico Sur ']);
  gate_assert($trimmed['status'] === 200 && $trimmed['json']['puesto'] === 'Pórtico Sur', 'El pórtico no se guarda en su forma canónica.');

  $beforeInvalid = $pdo->query('CHECKSUM TABLE registros_acceso')->fetch(PDO::FETCH_ASSOC);
  foreach (['Cualquier palabra','Portón 1','','Pórtico Este',17,['valor'=>'Pórtico Sur'],null] as $invalidGate) {
    foreach ([true,false] as $json) {
      if ($invalidGate === null && !$json) {
        continue; // Un campo null de formulario se omite y equivale a usar el valor inicial.
      }
      $response = gate_request($fixture, 'api/registrar_evento.php', ['rut'=>$staff[0],'tipo'=>'ENTRADA','puesto'=>$invalidGate], $json);
      gate_assert($response['status'] === 422 && ($response['json']['ok'] ?? true) === false, 'La API aceptó un puesto fuera de las tres opciones.');
    }
  }
  gate_assert($beforeInvalid === $pdo->query('CHECKSUM TABLE registros_acceso')->fetch(PDO::FETCH_ASSOC), 'Un puesto inválido modificó el historial.');
  echo "OK: los puestos arbitrarios o mal formados se rechazan sin guardar movimientos\n";

  $visitorRut = $reserved[3];
  $visitorLookup = $pdo->prepare('SELECT puesto FROM visitas WHERE id=? AND rut=?');
  $visitCases = [[true,['puesto'=>'Pórtico Sur']], [false,['puesto'=>'Otro portón']], [true,['puesto'=>['valor'=>'inventado']]], [false,[]]];
  foreach ($visitCases as [$json,$extra]) {
    $visit = gate_request($fixture, 'api/visitas_entrada.php', array_merge(['rut'=>$visitorRut,'nombre'=>$marker], $extra), $json);
    gate_assert($visit['status'] === 200 && ($visit['json']['resultado'] ?? '') === 'APROBADO' && $visit['json']['puesto'] === PUESTO_VISITAS, 'El navegador pudo alterar el pórtico de visitas.');
    $visitorLookup->execute([$visit['json']['id'],$visitorRut]);
    gate_assert($visitorLookup->fetchColumn() === PUESTO_VISITAS, 'La visita no conservó el pórtico fijo en MariaDB.');
    $exit = gate_request($fixture, 'api/visitas_salida.php', ['id'=>$visit['json']['id']]);
    gate_assert($exit['status'] === 200 && ($exit['json']['resultado'] ?? '') === 'APROBADO', 'La visita de prueba no pudo salir.');
  }
  echo "OK: visitas usa siempre Pórtico de Visitas, incluso si se intenta enviar otro puesto\n";
} catch (Throwable $error) {
  fwrite(STDERR,$error->getMessage() . "\n");
  $failed = true;
} finally {
  foreach ($reserved as $rut) {
    $pdo->prepare('DELETE FROM registros_acceso WHERE rut=?')->execute([$rut]);
    $pdo->prepare('DELETE FROM visitas WHERE rut=?')->execute([$rut]);
  }
  foreach ($staff as $rut) {
    $pdo->prepare('DELETE FROM funcionarios WHERE rut=? AND nombres=?')->execute([$rut,$marker]);
  }
  if ($fixture !== null) {
    $sessionFile = $config['session']['save_path'] . DIRECTORY_SEPARATOR . 'sess_' . $fixture;
    if (is_file($sessionFile)) {
      unlink($sessionFile);
    }
  }
  $after = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, usuarios, visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
  if ($before !== $after) {
    fwrite(STDERR,"Los datos de pruebas no coinciden con su estado inicial.\n");
    $failed = true;
  } else {
    echo "OK: datos temporales retirados; las cuatro tablas conservan su checksum\n";
  }
}
exit($failed ? 1 : 0);
