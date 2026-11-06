<?php
// Ejecutar desde la carpeta de pruebas: C:\xampp\php\php.exe tests\sistema.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
putenv('CONTROL_ACCESO_ENV=testing');
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/db.php';
require_once __DIR__.'/../app/monitor_sistema.php';
function system_assert(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
function system_request(string $fixture,string $path,string $method='GET'): array {
  $curl=curl_init('http://localhost:8081/Control-Acceso/'.$path);
  curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>3,
    CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIE=>app_config()['session']['name'].'='.$fixture]);
  try { $body=curl_exec($curl);if($body===false)throw new RuntimeException('No se pudo consultar el servidor de pruebas.');return ['status'=>curl_getinfo($curl,CURLINFO_HTTP_CODE),'body'=>$body]; }
  finally { curl_close($curl); }
}
$directory=rtrim(sys_get_temp_dir(),'/\\').DIRECTORY_SEPARATOR.'ca-monitor-test-'.bin2hex(random_bytes(8));
$fixtures=[];$pdo=db();$config=app_config();$failed=false;
$before=$pdo->query('CHECKSUM TABLE funcionarios,registros_acceso,usuarios,visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
try {
  $raw=['cpu_percent'=>0,'ram_total_bytes'=>1024,'ram_free_bytes'=>256,'disk_total_bytes'=>2048,'disk_free_bytes'=>0,'temperature_c'=>null,'uptime_seconds'=>123];
  $metrics=monitor_normalize($raw);
  system_assert($metrics['cpu_percent']===0.0&&$metrics['ram_percent']===75.0&&$metrics['disk_percent']===100.0,'Se perdieron ceros reales o la proporción de recursos.');
  $invalid=monitor_normalize(['cpu_percent'=>NAN,'ram_total_bytes'=>100,'ram_free_bytes'=>101,'temperature_c'=>'43','disk_total_bytes'=>0,'disk_free_bytes'=>0]);
  system_assert($invalid['cpu_percent']===null&&$invalid['ram_percent']===null&&$invalid['disk_percent']===null&&$invalid['temperature_c']===null,'Un dato ausente o inválido se convirtió en una lectura.');
  system_assert(monitor_normalize([])['ram_percent']===null,'La ausencia de RAM se representa como cero.');
  system_assert(monitor_normalize(['temperature_c'=>45.5])['temperature_source']==='Zona térmica ACPI (máxima)','La fuente térmica se identifica incorrectamente como CPU.');
  $now=time();$calls=0;
  $collect=function()use(&$calls,$raw){$calls++;return ['metrics'=>$raw,'database'=>['ok'=>true,'latency_ms'=>0,'version'=>'QA']];};
  $first=monitor_snapshot($directory,$collect,$now);
  $second=monitor_snapshot($directory,$collect,$now+1);
  system_assert($calls===1&&$second['cached']===true&&count($second['history'])===1,'La caché duplica lecturas o consultas.');
  $third=monitor_snapshot($directory,$collect,$now+10);
  system_assert($calls===2&&count($third['history'])===2&&$third['history'][1]['db_latency_ms']===0,'No se renueva la lectura al vencer la caché.');
  $lock=fopen($directory.DIRECTORY_SEPARATOR.'collector.lock','c');
  try {
    flock($lock,LOCK_EX);
    $busy=monitor_snapshot($directory,static function(){throw new RuntimeException('No debe ejecutar un segundo colector.');},$now+40);
    system_assert($busy['cached']===true&&$busy['sample']['timestamp']===$now+10,'No conserva la última lectura mientras hay un colector activo.');
  } finally {flock($lock,LOCK_UN);fclose($lock);}
  $prior=file_get_contents($directory.DIRECTORY_SEPARATOR.'state.json');
  try {monitor_snapshot($directory,static function(){throw new RuntimeException('QA error esperado');},$now+50);throw new LogicException('No propagó el error de lectura.');}
  catch(RuntimeException $error){system_assert($error->getMessage()==='QA error esperado','Error inesperado del colector.');}
  system_assert(file_get_contents($directory.DIRECTORY_SEPARATOR.'state.json')===$prior,'Un error destruyó la última lectura.');
  monitor_snapshot($directory,$collect,$now+60);
  system_assert($calls===3,'El bloqueo no se liberó después del error.');
  file_put_contents($directory.DIRECTORY_SEPARATOR.'state.json','{');
  $recovered=monitor_snapshot($directory,$collect,$now+70);
  system_assert(count($recovered['history'])===1,'No recuperó un archivo de caché inválido.');
  $history=[];for($index=0;$index<4000;$index++)$history[]=['timestamp'=>$now-4000+$index,'cpu_percent'=>1];
  $bounded=monitor_history($history,['timestamp'=>$now,'cpu_percent'=>0]);
  system_assert(count($bounded)===MONITOR_MAX_SAMPLES&&$bounded[array_key_last($bounded)]['cpu_percent']===0,'El historial no respeta su límite.');
  $trimmed=monitor_history([['timestamp'=>$now-MONITOR_HISTORY_SECONDS-1],['timestamp'=>$now+100]],['timestamp'=>$now]);
  system_assert(count($trimmed)===1,'El historial conserva lecturas vencidas o futuras.');
  system_assert(!str_starts_with(strtolower(str_replace('\\','/',monitor_directory())),'c:/xampp/htdocs/'),'El historial está dentro de la carpeta pública.');
  echo "OK: datos reales o nulos, unidades, caché, bloqueo, recuperación, retención y almacenamiento privado\n";

  foreach(['ADMIN','GUARDIA','AUDITOR','CONTROL_VISITA']as$role){
    $st=$pdo->prepare('SELECT id,username,rol FROM usuarios WHERE rol=? AND activo=1 ORDER BY id LIMIT 1');$st->execute([$role]);$user=$st->fetch();
    system_assert($user!==false,'Falta una cuenta activa de pruebas: '.$role);
    session_id(bin2hex(random_bytes(16)));auth_login($user);$fixtures[$role]=session_id();session_write_close();
  }
  session_id(bin2hex(random_bytes(16)));auth_start_session();$_SESSION=[];$fixtures['ANONIMO']=session_id();session_write_close();
  foreach(['ANONIMO'=>401,'GUARDIA'=>403,'AUDITOR'=>403,'CONTROL_VISITA'=>403]as$role=>$status){
    $response=system_request($fixtures[$role],'api/admin_system_status.php');
    system_assert($response['status']===$status&&(json_decode($response['body'],true)['ok']??true)===false,'El diagnóstico expone métricas a otro rol.');
    foreach(['gestion','sistema','exportaciones']as$page)system_assert(system_request($fixtures[$role],'configuracion/'.$page.'.php')['status']===302,'Una pantalla de configuración permite entrar a otro rol.');
  }
  $response=system_request($fixtures['ADMIN'],'api/admin_system_status.php');
  $data=json_decode($response['body'],true);
  system_assert($response['status']===200&&($data['ok']??false)===true,'El diagnóstico de administrador no responde.');
  system_assert($data['sample']['database']['ok']===true,'La comprobación real de MariaDB falló.');
  foreach(['cpu_percent','ram_percent','disk_percent']as$key)system_assert(is_numeric($data['sample']['metrics'][$key]??null),'No se obtuvo la lectura real de '.$key);
  $cached=json_decode(system_request($fixtures['ADMIN'],'api/admin_system_status.php')['body'],true);
  system_assert($cached['cached']===true&&$cached['sample']['timestamp']===$data['sample']['timestamp'],'La API no comparte lecturas en caché.');
  $post=system_request($fixtures['ADMIN'],'api/admin_system_status.php','POST');
  system_assert($post['status']===405,'El diagnóstico permite un método no admitido.');
  foreach(['gestion'=>['usersTable','nu_username'],'sistema'=>['systemChart','systemPause','cpuValue','ramValue','temperatureValue'],'exportaciones'=>['csvForm','csvTable','csvDate']]as$page=>$ids){
    $response=system_request($fixtures['ADMIN'],'configuracion/'.$page.'.php');
    system_assert($response['status']===200,'No se abre la pantalla '.$page);
    $dom=new DOMDocument();$dom->loadHTML('<?xml encoding="UTF-8">'.$response['body'],LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new DOMXPath($dom);
    foreach($ids as$id)system_assert($xpath->query('//*[@id="'.$id.'"]')->length===1,'Falta un control en '.$page.': '.$id);
    system_assert($xpath->query('//*[@id="configSubmenu"]/a')->length===3&&$xpath->query('//*[@aria-current="page"]')->length===1,'El submenú no identifica la pantalla seleccionada.');
    system_assert($xpath->query('//script[contains(@src,"assets/js/ui.js")]')->length===1,'El menú se carga más de una vez.');
    system_assert($xpath->query('//*[@id="settingsAccordion"]')->length===0,'Quedó el acordeón anterior.');
    foreach($xpath->query('//script[@src] | //link[@rel="stylesheet"]')as$asset){
      $url=$asset->getAttribute($asset->hasAttribute('src')?'src':'href');
      $path=preg_replace('~^/Control-Acceso/~','',$url);
      system_assert(system_request($fixtures['ANONIMO'],$path)['status']===200,'Falta un recurso de '.$page);
    }
  }
  echo "OK: métricas reales por HTTP, caché compartida, permisos de cuatro roles y tres páginas independientes\n";
} catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");$failed=true;}
finally {
  foreach($fixtures as$fixture){$file=$config['session']['save_path'].DIRECTORY_SEPARATOR.'sess_'.$fixture;if(is_file($file))unlink($file);}
  if(is_dir($directory)){foreach(glob($directory.DIRECTORY_SEPARATOR.'*')as$file)if(is_file($file))unlink($file);rmdir($directory);}
  $after=$pdo->query('CHECKSUM TABLE funcionarios,registros_acceso,usuarios,visitas')->fetchAll(PDO::FETCH_KEY_PAIR);
  if($before!==$after){fwrite(STDERR,"Las tablas cambiaron durante la verificación.\n");$failed=true;}
  else echo "OK: datos de MariaDB sin cambios; archivos y sesiones de prueba retirados\n";
}
ob_end_flush();
exit($failed?1:0);
