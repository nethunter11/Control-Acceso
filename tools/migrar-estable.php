<?php
// Ejecutar solo durante una integración aprobada, con respaldo SQL restaurado y verificado.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1] ?? '')!=='--aplicar')throw new RuntimeException('Uso: php tools/migrar-estable.php --aplicar. Requiere respaldo SQL verificado.');
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/migraciones.php';
if(app_config()['environment']!=='stable' || app_config()['database']['name']!=='control_acceso')throw new RuntimeException('Ejecuta este comando desde la carpeta de la versión oficial.');
foreach(aplicar_migraciones(db(),__DIR__.'/../migrations') as $version)echo 'Aplicada: '.$version.PHP_EOL;
echo "Base oficial actualizada. Se conservaron los registros existentes.\n";