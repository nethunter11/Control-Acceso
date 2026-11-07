<?php
// Ejecutar con MySQL y el servidor de pruebas iniciados:
// C:\xampp\php\php.exe tests\reportes_acceso.php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../app/auth.php';

$config = app_config();
$sessionDirectory = $config['session']['save_path'];
$sessionCookieName = $config['session']['name'];
$fixtures = [];
$failed = false;
$cases = [
  'SIN_SESION' => ['role' => null, 'status' => 401],
  'ADMIN' => ['role' => 'ADMIN', 'status' => 200],
  'AUDITOR' => ['role' => 'AUDITOR', 'status' => 200],
  'GUARDIA' => ['role' => 'GUARDIA', 'status' => 403],
  'CONTROL_VISITA' => ['role' => 'CONTROL_VISITA', 'status' => 403],
  'DESCONOCIDO' => ['role' => 'DESCONOCIDO', 'status' => 401],
];
$endpoints = [
  'reportes_diario.php?desde=2026-01-01&hasta=2026-01-01' => 'items',
  'reportes_kpi.php?desde=2026-01-01&hasta=2026-01-01' => 'kpi',
  'reportes_horas.php?fecha=2026-01-01' => 'items',
];

try {
  // Sesiones exclusivas de pruebas, sin crear ni modificar usuarios en MariaDB.
  // Se crean antes de imprimir resultados para no interferir con las cabeceras PHP.
  foreach ($cases as $label => $case) {
    session_id(bin2hex(random_bytes(16)));
    auth_start_session();
    if ($case['role'] !== null) {
      $lookup = db()->prepare('SELECT id FROM usuarios WHERE activo=1 AND rol=? ORDER BY id LIMIT 1');
      $lookup->execute([$case['role'] === 'DESCONOCIDO' ? 'GUARDIA' : $case['role']]);
      auth_login(['id'=>(int)$lookup->fetchColumn()]);
      if ($case['role'] === 'DESCONOCIDO') $_SESSION['user']['rol'] = 'DESCONOCIDO';
    }
    $fixtures[$label] = session_id();
    session_write_close();
  }

  $checks = 0;
  foreach ($cases as $label => $case) {
    foreach ($endpoints as $endpoint => $dataKey) {
      $http = curl_init('http://localhost:8081/Control-Acceso/api/' . $endpoint);
      curl_setopt_array($http, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_COOKIE => $sessionCookieName . '=' . $fixtures[$label],
      ]);
      try {
        $response = curl_exec($http);
        if ($response === false) {
          throw new RuntimeException('No se pudo consultar el servidor de pruebas: ' . curl_error($http));
        }
        $status = curl_getinfo($http, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($http, CURLINFO_CONTENT_TYPE) ?: '';
      } finally {
        curl_close($http);
      }

      $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
      if ($status !== $case['status'] || !str_starts_with($contentType, 'application/json')) {
        throw new RuntimeException("Respuesta inesperada para {$label} en {$endpoint}: HTTP {$status}");
      }
      if ($status === 200) {
        if (($json['ok'] ?? null) !== true || !isset($json[$dataKey]) || !is_array($json[$dataKey])) {
          throw new RuntimeException("Formato de reporte alterado para {$label} en {$endpoint}");
        }
      } elseif (($json['ok'] ?? null) !== false || empty($json['error']) || isset($json['items']) || isset($json['kpi'])) {
        throw new RuntimeException("La respuesta rechazada incluye datos o no informa el error: {$label}");
      }

      $checks++;
      echo "OK: {$label}, {$endpoint}, HTTP {$status}\n";
    }
  }
  echo "{$checks} comprobaciones correctas. No se modificaron datos de MariaDB.\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . "\n");
  $failed = true;
} finally {
  // Retirar únicamente las sesiones creadas por esta prueba.
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
  foreach ($fixtures as $fixtureId) {
    $fixtureFile = $sessionDirectory . DIRECTORY_SEPARATOR . 'sess_' . $fixtureId;
    if (is_file($fixtureFile)) {
      unlink($fixtureFile);
    }
  }
}

exit($failed ? 1 : 0);
