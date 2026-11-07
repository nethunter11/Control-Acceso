<?php
require_once __DIR__.'/support/accesos_fixture.php';
require_once __DIR__.'/../app/login_intentos.php';
qa_run(function(array &$qa): void {
  $name=$qa['marker'].'_LOGIN';$password='Temporal-2894';
  $qa['pdo']->prepare("INSERT INTO usuarios(username,password_hash,rol,activo) VALUES (?,?,'GUARDIA',1)")->execute([$name,password_hash($password,PASSWORD_DEFAULT)]);
  $id=(int)$qa['pdo']->lastInsertId();$http=curl_init();$sessions=[];
  curl_setopt_array($http,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10]);
  $request=function(string $path,?array $payload=null) use($http,&$sessions): array {
    $headers=[];
    curl_setopt($http,CURLOPT_URL,'http://127.0.0.1:8081/Control-Acceso/'.$path);
    curl_setopt($http,CURLOPT_HEADERFUNCTION,static function($handle,$line)use(&$headers){$headers[]=$line;return strlen($line);});
    if($payload===null) {curl_setopt($http,CURLOPT_POSTFIELDS,null);curl_setopt($http,CURLOPT_HTTPGET,true);}
    else {curl_setopt($http,CURLOPT_POST,true);curl_setopt($http,CURLOPT_POSTFIELDS,http_build_query($payload));}
    $body=curl_exec($http);qa_check($body!==false,'Fallo de conexión en acceso.');
    foreach(curl_getinfo($http,CURLINFO_COOKIELIST) as $cookie) {$parts=explode("\t",$cookie);if(($parts[5]??'')===app_config()['session']['name'])$sessions[]=$parts[6];}
    return ['status'=>curl_getinfo($http,CURLINFO_HTTP_CODE),'body'=>$body,'headers'=>$headers];
  };
  try {
    $login=$request('auth/login.php');
    qa_check(preg_match('/name="csrf" value="([a-f0-9]{64})"/',$login['body'],$match)===1,'No se genera CSRF en acceso.');
    $anonymous=end($sessions);
    $response=$request('auth/login_post.php',['csrf'=>$match[1],'username'=>$name,'password'=>$password]);
    qa_check($response['status']===302 && str_contains(implode('',$response['headers']),'/home.php'),'No funciona el inicio de sesión real.');
    $authenticated=end($sessions);
    qa_check($anonymous!==$authenticated,'El acceso no rota el ID de sesión.');
    qa_check($request('api/personal_dentro.php')['status']===200,'La sesión real no permite operar.');
    $page=$request('index.php');
    qa_check(preg_match('/<script id="appSession" type="application\/json">(.*?)<\/script>/s',$page['body'],$metadata)===1,'No se configura la protección del navegador.');
    $state=json_decode($metadata[1],true,512,JSON_THROW_ON_ERROR);
    qa_check($state['csrf']!==$match[1],'No se rota CSRF al iniciar sesión.');
    qa_check($request('auth/logout.php',['csrf'=>$state['csrf']])['status']===302,'No se puede cerrar sesión.');
    qa_check($request('api/personal_dentro.php')['status']===401,'La sesión permanece después de salir.');
    echo "OK: acceso real con formulario, rotación de sesión y CSRF, pantalla operativa y cierre protegido\n";
  } finally {
    curl_close($http);
    foreach(array_unique($sessions) as $session) {if(preg_match('/^[a-zA-Z0-9,-]+$/D',$session)){$file=app_config()['session']['save_path'].'/sess_'.$session;if(is_file($file))unlink($file);}}
    login_limite($name,false,true);
    $qa['pdo']->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
  }
});