<?php
// Crear antes un respaldo SQL de pruebas y comprobar su restauración.
// Ejecutar: C:\xampp\php\php.exe tests\concurrencia.php
// No necesita el servidor web: ejecuta las API en procesos PHP independientes.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/concurrencia.php';
require_once __DIR__ . '/../app/puestos.php';

function concurrent_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function concurrent_batch(string $endpoint, array $payload, int $operatorId, int $workers = 4): array {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ca_parallel_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) {
    throw new RuntimeException('No se pudo crear la carpeta temporal de la prueba.');
  }
  $startFile = $directory . DIRECTORY_SEPARATOR . 'start';
  $processes = [];
  $files = [$startFile];
  try {
    for ($i = 0; $i < $workers; $i++) {
      $readyFile = $directory . DIRECTORY_SEPARATOR . 'ready_' . $i;
      $files[] = $readyFile;
      $command = [PHP_BINARY, __DIR__ . '/support/concurrencia_worker.php', $endpoint,
        base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), $readyFile, $startFile, (string)$operatorId];
      $process = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null, ['bypass_shell' => true]);
      if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar una solicitud paralela.');
      }
      fclose($pipes[0]);
      $processes[] = ['process' => $process, 'pipes' => [$pipes[1], $pipes[2]], 'ready' => $readyFile];
    }
    $deadline = microtime(true) + 10;
    do {
      clearstatcache();
      $ready = count(array_filter($processes, fn($item) => is_file($item['ready'])));
      if ($ready === $workers) {
        break;
      }
      usleep(10000);
    } while (microtime(true) < $deadline);
    concurrent_assert($ready === $workers, 'Las solicitudes no alcanzaron la barrera de inicio.');
    file_put_contents($startFile, 'start');

    $deadline = microtime(true) + 15;
    do {
      $running = 0;
      foreach ($processes as $item) {
        if (proc_get_status($item['process'])['running']) {
          $running++;
        }
      }
      if ($running === 0) {
        break;
      }
      usleep(10000);
    } while (microtime(true) < $deadline);
    concurrent_assert($running === 0, 'Una solicitud paralela no terminó a tiempo.');

    $responses = [];
    foreach ($processes as $item) {
      $output = stream_get_contents($item['pipes'][0]);
      $errors = stream_get_contents($item['pipes'][1]);
      concurrent_assert($errors === '', 'Una solicitud produjo un error PHP: ' . $errors);
      $responses[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $responses;
  } finally {
    foreach ($processes as $item) {
      if (proc_get_status($item['process'])['running']) {
        proc_terminate($item['process']);
      }
      foreach ($item['pipes'] as $pipe) {
        fclose($pipe);
      }
      proc_close($item['process']);
    }
    foreach ($files as $file) {
      if (is_file($file)) {
        unlink($file);
      }
    }
    rmdir($directory);
  }
}

function concurrent_results(array $responses, int $approved, string $label): void {
  $actualApproved = 0;
  foreach ($responses as $response) {
    concurrent_assert(($response['status'] ?? 0) === 200 && ($response['body']['ok'] ?? false) === true, 'Respuesta inesperada: ' . $label);
    $result = $response['body']['resultado'] ?? '';
    concurrent_assert(in_array($result, ['APROBADO', 'RECHAZADO'], true), 'Resultado inesperado: ' . $label);
    if ($result === 'APROBADO') {
      $actualApproved++;
    } else {
      concurrent_assert(($response['body']['guardado'] ?? null) === false, 'Un duplicado indicó que se guardó: ' . $label);
    }
  }
  concurrent_assert($actualApproved === $approved, 'Cantidad de aprobaciones incorrecta: ' . $label);
  echo "OK: {$label}; {$actualApproved} aprobados de " . count($responses) . " solicitudes simultáneas\n";
}

function concurrent_unused_rut(PDO $pdo, array $reserved): string {
  for ($attempt = 0; $attempt < 100; $attempt++) {
    $number = random_int(80000000, 98999999);
    foreach (array_merge(range(0, 9), ['K']) as $dv) {
      $rut = $number . '-' . $dv;
      if (!rut_es_valido($rut) || in_array($rut, $reserved, true)) {
        continue;
      }
      $lookup = $pdo->prepare('SELECT (SELECT COUNT(*) FROM funcionarios WHERE rut=?) + (SELECT COUNT(*) FROM registros_acceso WHERE rut=?) + (SELECT COUNT(*) FROM visitas WHERE rut=?)');
      $lookup->execute([$rut, $rut, $rut]);
      if ((int)$lookup->fetchColumn() === 0) {
        return $rut;
      }
    }
  }
  throw new RuntimeException('No se pudo reservar un RUT exclusivo para la prueba.');
}

$pdo = db();
$marker = 'QA_CONC_' . bin2hex(random_bytes(6));
$suffix = bin2hex(random_bytes(5));
$triggers = [];
$staffRuts = [];
$reserved = [];
$failed = false;
$before = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, visitas, usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
try {
  $operatorId = (int)$pdo->query("SELECT id FROM usuarios WHERE rol='ADMIN' AND activo=1 LIMIT 1")->fetchColumn();
  concurrent_assert($operatorId > 0, 'Se necesita una cuenta ADMIN activa en la copia de pruebas.');
  for ($i=0; $i<4; $i++) {
    $reserved[] = concurrent_unused_rut($pdo, $reserved);
  }
  [$staffRut, $otherStaffRut, $visitorRut, $rollbackRut] = $reserved;
  $insertStaff = $pdo->prepare("INSERT INTO funcionarios (rut,nombres,estado) VALUES (?,?,'ACTIVO')");
  foreach ([$staffRut, $otherStaffRut] as $rut) {
    $insertStaff->execute([$rut, $marker]);
    $staffRuts[] = $rut;
  }

  // Retardo limitado a los RUT exclusivos de esta prueba. Así, la comprobación detecta
  // la carrera incluso en un equipo rápido y con el historial inicialmente vacío.
  foreach ([['registros_acceso','INSERT'], ['visitas','INSERT'], ['visitas','UPDATE']] as $index => [$table,$event]) {
    $trigger = 'qa_conc_' . $suffix . '_' . $index;
    $testRutList = implode(',', array_map(fn($testRut) => $pdo->quote($testRut), $reserved));
    $pdo->exec("CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN IF NEW.rut IN ({$testRutList}) THEN DO SLEEP(0.25); END IF; END");
    $triggers[] = $trigger;
  }

  $movement = ['rut'=>$staffRut, 'tipo'=>'ENTRADA', 'puesto'=>PUESTOS_ACCESO[0]];
  concurrent_results(concurrent_batch('registrar_evento.php', $movement, $operatorId), 1, 'primera entrada de funcionario');
  concurrent_results(concurrent_batch('registrar_evento.php', $movement, $operatorId), 0, 'entrada repetida');
  $movement['tipo'] = 'SALIDA';
  concurrent_results(concurrent_batch('registrar_evento.php', $movement, $operatorId), 1, 'salida de funcionario');
  $movement['tipo'] = 'ENTRADA';
  concurrent_results(concurrent_batch('registrar_evento.php', $movement, $operatorId), 1, 'reingreso después de salir');
  $sequence = $pdo->prepare("SELECT tipo FROM registros_acceso WHERE rut=? AND resultado='APROBADO' ORDER BY id");
  $sequence->execute([$staffRut]);
  concurrent_assert($sequence->fetchAll(PDO::FETCH_COLUMN) === ['ENTRADA','SALIDA','ENTRADA'], 'El historial no mantuvo la alternancia de movimientos.');

  $visit = ['rut'=>$visitorRut, 'nombre'=>$marker];
  $visitorResponses = concurrent_batch('visitas_entrada.php', $visit, $operatorId);
  concurrent_results($visitorResponses, 1, 'primera entrada de visita');
  $visitIds = array_values(array_filter(array_map(fn($response) => $response['body']['resultado'] === 'APROBADO' ? (int)$response['body']['id'] : null, $visitorResponses)));
  $visitId = $visitIds[0];
  concurrent_results(concurrent_batch('visitas_salida.php', ['id'=>$visitId], $operatorId), 1, 'salida de visita');
  concurrent_results(concurrent_batch('visitas_salida.php', ['id'=>$visitId], $operatorId), 0, 'salida de visita ya cerrada');
  concurrent_results(concurrent_batch('visitas_entrada.php', $visit, $operatorId), 1, 'nuevo ingreso de visita después de salir');
  $openVisits = $pdo->prepare("SELECT COUNT(*) FROM visitas WHERE rut=? AND estado='DENTRO'");
  $openVisits->execute([$visitorRut]);
  concurrent_assert((int)$openVisits->fetchColumn() === 1, 'Se crearon varias visitas abiertas para el mismo RUT.');

  ejecutar_por_rut($pdo, 'funcionarios', $staffRut, function () use ($otherStaffRut, $marker, $operatorId): array {
    $responses = concurrent_batch('registrar_evento.php', ['rut'=>$otherStaffRut,'tipo'=>'ENTRADA','puesto'=>PUESTOS_ACCESO[0]], $operatorId, 1);
    concurrent_results($responses, 1, 'otro RUT puede registrar mientras el primero está bloqueado');
    return ['ok'=>true];
  });

  ejecutar_por_rut($pdo, 'visitas', $visitorRut, function () use ($visit, $operatorId): array {
    $responses = concurrent_batch('visitas_entrada.php', $visit, $operatorId, 1);
    concurrent_assert($responses[0]['status'] === 409 && ($responses[0]['body']['ok'] ?? true) === false, 'La espera máxima no devolvió un error controlado.');
    echo "OK: una espera agotada devuelve HTTP 409 sin aprobar ni duplicar\n";
    return ['ok'=>true];
  });

  try {
    ejecutar_por_rut($pdo, 'visitas', $rollbackRut, function () use ($pdo, $rollbackRut, $marker): array {
      $insert = $pdo->prepare("INSERT INTO visitas (rut,nombre,puesto,fecha_hora_entrada,estado) VALUES (?,?,?,NOW(),'DENTRO')");
      $insert->execute([$rollbackRut, $marker, $marker]);
      throw new RuntimeException('fallo_controlado_concurrencia');
    });
    throw new RuntimeException('No se produjo el fallo de prueba esperado.');
  } catch (RuntimeException $error) {
    if ($error->getMessage() !== 'fallo_controlado_concurrencia') {
      throw $error;
    }
  }
  $openVisits->execute([$rollbackRut]);
  concurrent_assert((int)$openVisits->fetchColumn() === 0, 'Un fallo dejó datos parciales.');
  $secondConnection = db();
  ejecutar_por_rut($secondConnection, 'visitas', $rollbackRut, fn() => ['ok'=>true]);
  echo "OK: un fallo revierte los datos y libera el turno del RUT\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . "\n");
  $failed = true;
} finally {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  foreach ($triggers as $trigger) {
    $pdo->exec('DROP TRIGGER ' . $trigger);
  }
  foreach ($reserved as $rut) {
    $pdo->prepare('DELETE FROM registros_acceso WHERE rut=?')->execute([$rut]);
    $pdo->prepare('DELETE FROM visitas WHERE rut=?')->execute([$rut]);
  }
  foreach ($staffRuts as $rut) {
    $pdo->prepare('DELETE FROM funcionarios WHERE rut=? AND nombres=?')->execute([$rut, $marker]);
  }
  $after = $pdo->query('CHECKSUM TABLE funcionarios, registros_acceso, visitas, usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
  if ($before !== $after) {
    fwrite(STDERR, "Los datos de pruebas no coinciden con su estado inicial.\n");
    $failed = true;
  } else {
    echo "OK: retirados los datos y retardos temporales; las cuatro tablas conservan su checksum\n";
  }
}
exit($failed ? 1 : 0);
