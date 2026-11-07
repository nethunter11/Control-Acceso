<?php
require_once __DIR__ . '/fechas.php';

function app_config(): array {
  static $config = null;
  if ($config !== null) {
    return $config;
  }

  $defaults = [
    'environment' => 'stable',
    'database' => [
      'host' => '127.0.0.1',
      'port' => 3306,
      'name' => 'control_acceso',
      'user' => 'root',
      'password' => '',
      'charset' => 'utf8mb4',
    ],
    'session' => [
      'name' => null,
      'save_path' => null,
      'cookie_path' => null,
      'idle_timeout' => 3600,
      'absolute_timeout' => 43200,
    ],
  ];

  $localFile = __DIR__ . '/config.local.php';
  $local = is_file($localFile) ? require $localFile : [];
  if (!is_array($local)) {
    throw new RuntimeException('La configuración local debe devolver un arreglo.');
  }
  $resolved = array_replace_recursive($defaults, $local);

  if (getenv('CONTROL_ACCESO_ENV') === 'testing') {
    if ($resolved['environment'] !== 'testing'
        || $resolved['database']['name'] === 'control_acceso'
        || $resolved['database']['user'] === 'root'
        || empty($resolved['session']['name'])
        || $resolved['session']['name'] === 'PHPSESSID'
        || empty($resolved['session']['save_path'])
        || !is_dir($resolved['session']['save_path'])) {
      throw new RuntimeException('Falta la configuración aislada del entorno de pruebas.');
    }
  }

  $config = $resolved;
  return $config;
}
