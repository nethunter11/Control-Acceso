<?php
// Ejecutar después de verificar un respaldo SQL. Solo modifica la base de pruebas.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../app/db.php';
$pdo = db();
if (app_config()['environment'] !== 'testing' || app_config()['database']['name'] === 'control_acceso') {
  throw new RuntimeException('Migraciones permitidas únicamente en pruebas.');
}
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL)');
foreach (glob(__DIR__.'/../migrations/*.sql') as $file) {
  $version = basename($file);
  $lookup = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');
  $lookup->execute([$version]);
  if ($lookup->fetchColumn()) continue;
  $pdo->exec(file_get_contents($file));
  $pdo->prepare('INSERT INTO schema_migrations (version,applied_at) VALUES (?,NOW())')->execute([$version]);
  echo 'Aplicada: '.$version.PHP_EOL;
}
echo "Base de pruebas actualizada.\n";