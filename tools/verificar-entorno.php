<?php

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__ . '/../app/db.php';

try {
  $connection = db();
  $database = (string)$connection->query('SELECT DATABASE()')->fetchColumn();
  if ($database !== app_config()['database']['name']) {
    throw new RuntimeException('La conexión no corresponde a la base de pruebas.');
  }

  $stableDatabaseBlocked = false;
  try {
    $connection->query('SELECT 1 FROM control_acceso.usuarios LIMIT 1');
  } catch (PDOException $error) {
    if (in_array((int)($error->errorInfo[1] ?? 0), [1044, 1142], true)) {
      $stableDatabaseBlocked = true;
    } else {
      throw $error;
    }
  }
  if (!$stableDatabaseBlocked) {
    throw new RuntimeException('La cuenta de pruebas tiene acceso a la base estable.');
  }
  echo "Entorno de pruebas listo. Base de datos: {$database}\n";
  echo "Aislamiento correcto: la cuenta de pruebas no puede consultar la base estable.\n";
} catch (Throwable $error) {
  fwrite(STDERR, "No se pudo iniciar el entorno de pruebas.\n");
  fwrite(STDERR, "Comprueba que MySQL esté iniciado y que exista app/config.local.php.\n");
  exit(1);
}
