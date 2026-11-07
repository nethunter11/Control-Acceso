<?php
require_once __DIR__.'/support/accesos_fixture.php';
require_once __DIR__.'/../app/login_intentos.php';
qa_run(function (array &$qa): void {
  foreach (['api/admin_users_create.php','api/admin_users_update.php','api/admin_users_delete.php','api/admin_users_reset_password.php','api/registrar_evento.php','api/visitas_entrada.php','api/visitas_salida.php','api/validar_rut.php'] as $path) {
    qa_check(qa_http($qa['sessions']['ADMIN'],$path,[],null,false)['status'] === 403,'Acción sin CSRF permitida: '.$path);
    qa_check(qa_http($qa['sessions']['ADMIN'],$path)['status'] === 405,'Método GET permitido: '.$path);
  }
  qa_check(qa_http($qa['sessions']['GUARDIA'],'api/admin_users_create.php',[])['status'] === 403,'Se omitió el control de roles.');
  $session = $qa['sessions']['ADMIN'];
  session_id($session); auth_start_session(); $_SESSION['last_activity']=time()-100; $last=$_SESSION['last_activity']; session_write_close();
  $read=qa_http($session,'api/session.php');
  qa_check($read['status']===200,'No se pudo consultar la sesión.');
  session_id($session); auth_start_session(); qa_check($_SESSION['last_activity']===$last,'La actualización automática prolonga la sesión.'); session_write_close();
  qa_check(qa_http($session,'api/session.php',[])['status']===200,'La actividad protegida no renueva la sesión.');
  foreach (['IDLE'=>['last_activity'=>time()-3601,'auth_started'=>time()], 'ABSOLUTE'=>['last_activity'=>time(),'auth_started'=>time()-43201]] as $name=>$timestamps) {
    session_id(bin2hex(random_bytes(16))); auth_login($qa['users']['ADMIN']); $id=session_id(); $qa['sessions'][$name]=$id;
    foreach($timestamps as $key=>$value) $_SESSION[$key]=$value;
    session_write_close();
    qa_check(qa_http($id,'api/session.php')['status']===401,'La sesión vencida se conserva: '.$name);
  }
  qa_check(qa_http($session,'auth/logout.php')['status']===405,'El cierre de sesión admite GET.');
  qa_check(qa_http($session,'auth/logout.php',[],null,false)['status']===403,'El cierre de sesión omite CSRF.');
  $anonymous=qa_http(null,'auth/login.php');
  $cookies=implode('', $anonymous['headers']);
  qa_check(str_contains(strtolower($cookies),'httponly') && str_contains(strtolower($cookies),'samesite=lax'),'Cookies sin HttpOnly o SameSite.');
  $login=qa_http(null,'auth/login_post.php',[],null,false);
  qa_check($login['status']===302 && str_contains(implode('',$login['headers']),'Recarga'),'El formulario de acceso sin token no se rechaza por CSRF.');
  $name='qa-rate-'.bin2hex(random_bytes(6));
  try {
    qa_check(!login_limite($name),'El límite comienza bloqueado.');
    for($i=0;$i<8;$i++) login_limite($name,true);
    qa_check(login_limite($name),'No se limita la repetición de intentos.');
  } finally { login_limite($name,false,true); }
  echo "OK: CSRF, métodos, roles, 60 minutos de inactividad, 12 horas máximas y límite de intentos\n";
  echo "OK: las consultas automáticas no renuevan la sesión; solo la actividad protegida\n";
});