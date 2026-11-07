<?php
// Ejecutar con MySQL y el servidor de pruebas iniciados:
// C:\xampp\php\php.exe tests\rut_rechazos.php
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

function rut_test_response(string $cookie, array $payload, bool $jsonBody): array {
  $http = curl_init('http://localhost:8081/Control-Acceso/api/registrar_evento.php');
  curl_setopt_array($http, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'X-CSRF-Token: '.test_session_token(explode('=',$cookie,2)[1]),
      'Content-Type: ' . ($jsonBody ? 'application/json' : 'application/x-www-form-urlencoded'),
    ],
    CURLOPT_COOKIE => $cookie,
    CURLOPT_POSTFIELDS => $jsonBody
      ? json_encode($payload, JSON_THROW_ON_ERROR)
      : http_build_query($payload),
  ]);
  try {
    $body = curl_exec($http);
    if ($body === false) {
      throw new RuntimeException('No se pudo consultar el servidor de pruebas: ' . curl_error($http));
    }
    if (curl_getinfo($http, CURLINFO_HTTP_CODE) !== 200) {
      throw new RuntimeException('El rechazo de RUT no respondió con HTTP 200.');
    }
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
  } finally {
    curl_close($http);
  }
}

function rut_test_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$config = app_config();
$fixtures = [];
$failed = false;

try {
  $connection = db();
  $before = $connection->query('CHECKSUM TABLE registros_acceso')->fetch(PDO::FETCH_ASSOC);
  $unknownRut = null;
  $lookup = $connection->prepare('SELECT 1 FROM funcionarios WHERE rut = ?');
  for ($number = 90000000; $number < 90000100; $number++) {
    $candidate = $number . '-K';
    if (!rut_es_valido($candidate)) {
      continue;
    }
    $lookup->execute([$candidate]);
    if (!$lookup->fetchColumn()) {
      $unknownRut = $candidate;
      break;
    }
  }
  rut_test_assert($unknownRut !== null, 'No se encontró un RUT válido y ausente para la prueba.');
  [$unknownNumber] = explode('-', $unknownRut);
  $formattedUnknown = number_format((int)$unknownNumber, 0, ',', '.') . '-k';

  $cases = [
    'dígito verificador incorrecto' => ['payload' => ['rut' => '12345678-0'], 'rut' => '12345678-0', 'motivo' => 'RUT inválido'],
    'formato inválido' => ['payload' => ['rut' => 'abc'], 'rut' => 'AB-C', 'motivo' => 'RUT inválido'],
    'RUT vacío' => ['payload' => ['rut' => ''], 'rut' => '', 'motivo' => 'RUT inválido'],
    'campo ausente' => ['payload' => [], 'rut' => '', 'motivo' => 'RUT inválido'],
    'RUT demasiado largo' => ['payload' => ['rut' => str_repeat('9', 80)], 'rut' => str_repeat('9', 79) . '-9', 'motivo' => 'RUT inválido'],
    'dato no textual' => ['payload' => ['rut' => ['dato', 'incorrecto']], 'rut' => '', 'motivo' => 'RUT inválido'],
    'RUT válido no registrado' => ['payload' => ['rut' => $unknownRut], 'rut' => $unknownRut, 'motivo' => 'RUT no registrado'],
    'RUT con puntos y DV minúscula' => ['payload' => ['rut' => $formattedUnknown], 'rut' => $unknownRut, 'motivo' => 'RUT no registrado'],
  ];

  // Sesiones temporales de operadores existentes; no se crean usuarios.
  $operatorLookup = $connection->prepare('SELECT id, username, rol FROM usuarios WHERE rol = ? AND activo = 1 ORDER BY id LIMIT 1');
  foreach (['ADMIN', 'GUARDIA'] as $role) {
    $operatorLookup->execute([$role]);
    $operator = $operatorLookup->fetch();
    rut_test_assert($operator !== false, 'Se necesita un operador activo con rol ' . $role . ' en pruebas.');
    session_id(bin2hex(random_bytes(16)));
    auth_login($operator);
    $fixtures[$role] = session_id();
    session_write_close();
  }

  $checks = 0;
  foreach ($cases as $label => $case) {
    foreach ($fixtures as $fixtureId) {
      $cookie = $config['session']['name'] . '=' . $fixtureId;
      foreach (['ENTRADA', 'SALIDA'] as $type) {
        foreach ([true, false] as $jsonBody) {
          $payload = array_merge($case['payload'], ['tipo' => $type, 'modo' => 'VEHICULO', 'puesto' => PUESTOS_ACCESO[0]]);
          $response = rut_test_response($cookie, $payload, $jsonBody);
          rut_test_assert(($response['ok'] ?? null) === true && ($response['resultado'] ?? '') === 'RECHAZADO', 'Respuesta incorrecta: ' . $label);
          rut_test_assert(($response['guardado'] ?? null) === false, 'El rechazo debe informar que no se guardó: ' . $label);
          rut_test_assert(($response['rut'] ?? null) === $case['rut'] && str_contains($response['motivo'] ?? '', $case['motivo']), 'RUT o motivo incorrecto: ' . $label);
          rut_test_assert(($response['tipo'] ?? '') === $type && ($response['modo'] ?? '') === 'VEHICULO' && ($response['puesto'] ?? '') === PUESTOS_ACCESO[0], 'Faltan los campos utilizados por la pantalla: ' . $label);
          rut_test_assert(array_key_exists('funcionario', $response) && $response['funcionario'] === null, 'Un RUT rechazado sin registro no debe devolver un funcionario: ' . $label);
          $checks++;
        }
      }
    }
    echo "OK: {$label}, ADMIN y GUARDIA, entrada/salida, JSON/formulario\n";
  }

  // Comprueba una ruta de funcionario existente sin insertar movimientos nuevos.
  $known = $connection->query("SELECT r.rut, r.tipo FROM registros_acceso r INNER JOIN funcionarios f ON f.rut = r.rut WHERE r.resultado = 'APROBADO' AND f.estado = 'ACTIVO' ORDER BY r.fecha_hora DESC, r.id DESC LIMIT 1")->fetch();
  if ($known) {
    foreach ($fixtures as $fixtureId) {
      foreach ([true, false] as $jsonBody) {
        $response = rut_test_response($config['session']['name'] . '=' . $fixtureId, ['rut' => $known['rut'], 'tipo' => $known['tipo']], $jsonBody);
        rut_test_assert(($response['resultado'] ?? '') === 'RECHAZADO' && ($response['guardado'] ?? null) === false && ($response['funcionario']['rut'] ?? '') === $known['rut'], 'Cambió la comprobación de duplicados de un funcionario existente.');
        $checks++;
      }
    }
    echo "OK: se conserva el rechazo de movimientos duplicados de funcionarios existentes\n";
  }

  $after = $connection->query('CHECKSUM TABLE registros_acceso')->fetch(PDO::FETCH_ASSOC);
  rut_test_assert($before === $after, 'La comprobación modificó el historial de movimientos.');
  echo "{$checks} comprobaciones correctas. El historial de movimientos conserva su checksum.\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . "\n");
  $failed = true;
} finally {
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
  foreach ($fixtures as $fixtureId) {
    $fixtureFile = $config['session']['save_path'] . DIRECTORY_SEPARATOR . 'sess_' . $fixtureId;
    if (is_file($fixtureFile)) {
      unlink($fixtureFile);
    }
  }
}

exit($failed ? 1 : 0);
