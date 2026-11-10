<?php
// Ejecutar con el servidor de pruebas detenido y un respaldo SQL restaurado y verificado.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/migrar_rut_funcionarios.php';
if(app_config()['environment']!=='testing' || app_config()['database']['name']!=='control_acceso_pruebas')throw new RuntimeException('Esta herramienta solo convierte la base de pruebas.');
if(!in_array('--aplicar',$argv,true)){
  echo "Respalda la base, verifica su restauración y detén el servidor de pruebas.\n";
  echo "Después ejecuta: php tools/migrar-rut-funcionarios.php --aplicar\n";exit;
}
$result=migrar_rut_funcionarios(db());
echo 'Conversión completada: '.$result['funcionarios'].' funcionarios y '.$result['movimientos']." movimientos. Visitas conserva su RUT completo.\n";
