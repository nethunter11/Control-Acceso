<?php
// Copiar a config.local.php y completar los datos del entorno de pruebas.
// config.local.php es privado y no se guarda en Git.
return [
  'environment' => 'testing',
  'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'control_acceso_pruebas',
    'user' => 'ca_pruebas',
    'password' => 'COMPLETAR_CON_LA_CLAVE_LOCAL',
  ],
  'session' => [
    'name' => 'CONTROLACCESOPRUEBAS',
    'save_path' => 'C:/xampp/htdocs/Control-Acceso-pruebas/sesiones',
    'cookie_path' => '/Control-Acceso/',
  ],
];
