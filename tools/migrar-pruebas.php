<?php
// Ejecutar después de verificar un respaldo SQL. Solo modifica la base de pruebas.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/migraciones.php';
if(app_config()['environment']!=='testing' || app_config()['database']['name']==='control_acceso')throw new RuntimeException('Migraciones permitidas únicamente en pruebas.');
foreach(aplicar_migraciones(db(),__DIR__.'/../migrations') as $version)echo 'Aplicada: '.$version.PHP_EOL;
echo "Base de pruebas actualizada.\n";