<?php
// Solo pruebas. Requiere respaldo SQL restaurado y verificado antes de aplicar.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/patentes_funcionarios.php';
if(app_config()['environment']!=='testing' || app_config()['database']['name']!=='control_acceso_pruebas')throw new RuntimeException('Asignación permitida únicamente en la base de pruebas.');
if(($argv[1]??'')!=='--aplicar')throw new RuntimeException('Uso: php tools/asignar-patentes-pruebas.php --aplicar. Requiere respaldo SQL verificado.');
$result=asignar_patentes_funcionarios(db());
echo 'Patentes asignadas: '.$result['asignadas'].'; funcionarios: '.$result['total'].". Las patentes existentes se conservan.\n";
