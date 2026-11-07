<?php
require_once __DIR__.'/support/accesos_fixture.php';

qa_run(function (array &$qa): void {
  $overnight = qa_staff($qa);
  qa_event($qa,$overnight,'ENTRADA','2001-02-03 23:59:59');
  $inactive = qa_staff($qa,'INACTIVO');
  qa_event($qa,$inactive,'ENTRADA','2001-02-03 20:00:00');
  qa_event($qa,$inactive,'SALIDA','2001-02-04 01:00:00','RECHAZADO');
  $outside = qa_staff($qa);
  qa_event($qa,$outside,'ENTRADA','2001-02-03 12:00:00');
  qa_event($qa,$outside,'SALIDA','2001-02-03 12:00:00');
  qa_event($qa,$outside,'ENTRADA','2001-02-04 00:00:00','RECHAZADO');
  $returned = qa_staff($qa);
  qa_event($qa,$returned,'SALIDA','2001-02-03 12:00:00');
  qa_event($qa,$returned,'ENTRADA','2001-02-03 12:00:00');
  $onlyRejects = qa_staff($qa);
  qa_event($qa,$onlyRejects,'ENTRADA','2001-02-03 12:00:00','RECHAZADO');
  foreach (['ADMIN','GUARDIA'] as $role) {
    $items = qa_json($qa,'api/personal_dentro.php?fecha=1999-01-01',200,$role)['items'];
    $byRut = array_column($items,null,'rut');
    qa_check(count($items) === count($byRut),'Una persona aparece más de una vez.');
    qa_check(isset($byRut[$overnight]) && $byRut[$overnight]['ultima_hora'] === '2001-02-03 23:59:59','El ingreso de un día anterior desapareció.');
    qa_check(isset($byRut[$inactive]) && $byRut[$inactive]['estado'] === 'INACTIVO','Se ocultó una persona aún dentro por su estado administrativo.');
    qa_check(!isset($byRut[$outside]),'La salida del mismo segundo o el rechazo posterior alteran la presencia.');
    qa_check(isset($byRut[$returned]),'La última entrada del mismo segundo no determina presencia.');
    qa_check(!isset($byRut[$onlyRejects]),'Un rechazo se contó como ingreso.');
  }
  echo "OK: presencia global, medianoche, inactivos, rechazos y desempate por ID\n";
  foreach (['AUDITOR','CONTROL_VISITA'] as $role) qa_json($qa,'api/personal_dentro.php',403,$role);
  qa_json($qa,'api/personal_dentro.php',401,'SIN_SESION');
  $page = qa_http($qa['sessions']['GUARDIA'],'index.php');
  qa_check($page['status'] === 200 && str_contains($page['body'],'personal-dentro.js') && str_contains($page['body'],'Última actualización'),'La pantalla no muestra el estado de la consulta.');
  qa_check(!str_contains($page['body'],"getElementById('d_fecha')"),'Persiste una referencia al filtro inexistente.');
  echo "OK: permisos y pantalla de presencia con actualización automática\n";
});