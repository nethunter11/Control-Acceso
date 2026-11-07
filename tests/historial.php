<?php
// Ejecutar con el servidor de pruebas y un respaldo SQL verificado.
require_once __DIR__.'/support/accesos_fixture.php';

qa_run(function (array &$qa): void {
  $rut = qa_staff($qa);
  $rejectedExit = qa_event($qa,$rut,'SALIDA','2001-02-03 08:00:00','RECHAZADO','GUARDIA');
  $rejectedEntry = qa_event($qa,$rut,'ENTRADA','2001-02-03 09:00:00','RECHAZADO');
  $entry = qa_event($qa,$rut,'ENTRADA','2001-02-03 10:00:00');
  $rejectedInside = qa_event($qa,$rut,'SALIDA','2001-02-03 11:00:00','RECHAZADO','GUARDIA');
  qa_event($qa,$rut,'SALIDA','2001-02-03 18:00:00','APROBADO','GUARDIA');
  foreach (['ADMIN','GUARDIA','AUDITOR'] as $role) {
    $items = qa_json($qa,'api/registros_resumen.php?rut='.$rut,200,$role)['items'];
    qa_check(count($items) === 4,'Se perdió un rechazo o se duplicó una pareja.');
    $byId = array_column($items,null,'id');
    qa_check($byId[$entry]['salida'] === '2001-02-03 18:00:00','La salida se asignó a otra entrada.');
    qa_check($byId[$entry]['operador_entrada'] === $qa['users']['ADMIN']['username'] && $byId[$entry]['operador_salida'] === $qa['users']['GUARDIA']['username'],'Los operadores no corresponden a la pareja.');
    qa_check($byId[$rejectedEntry]['salida'] === null && $byId[$rejectedEntry]['operador_salida_id'] === null,'El rechazo de entrada absorbió una salida.');
    foreach ([$rejectedExit,$rejectedInside] as $id) {
      qa_check($byId[$id]['entrada'] === null && $byId[$id]['operador_entrada_id'] === null && $byId[$id]['tipo_movimiento'] === 'SALIDA','Un rechazo de salida aparece como entrada.');
    }
  }
  qa_check(count(qa_json($qa,'api/registros_resumen.php?rut='.$rut.'&resultado=RECHAZADO')['items']) === 3,'El filtro ocultó rechazos.');
  echo "OK: rechazos separados, operadores correctos y roles de consulta conservados\n";

  $odd = qa_staff($qa);
  $orphan = qa_event($qa,$odd,'SALIDA','2001-02-03 12:00:00','APROBADO',null);
  $first = qa_event($qa,$odd,'ENTRADA','2001-02-03 12:00:00');
  $second = qa_event($qa,$odd,'ENTRADA','2001-02-03 12:00:00');
  qa_event($qa,$odd,'SALIDA','2001-02-03 12:00:00','APROBADO','GUARDIA');
  $extraExit = qa_event($qa,$odd,'SALIDA','2001-02-03 12:00:00');
  $pending = qa_event($qa,$odd,'ENTRADA','2001-02-03 12:00:00','APROBADO',null);
  $items = qa_json($qa,'api/registros_resumen.php?rut='.$odd)['items'];
  $byId = array_column($items,null,'id');
  qa_check(count($items) === 5,'Secuencia histórica irregular perdió movimientos.');
  qa_check($byId[$first]['salida'] === null && $byId[$first]['incidencia'] !== null,'Una entrada antigua duplicada absorbió una salida.');
  qa_check($byId[$second]['salida'] !== null,'El desempate por ID no emparejó movimientos consecutivos.');
  qa_check($byId[$orphan]['entrada'] === null && $byId[$extraExit]['entrada'] === null,'Se inventó una entrada para una salida huérfana.');
  qa_check($byId[$pending]['salida'] === null && $byId[$pending]['operador_entrada'] === null,'Se inventó un operador o una salida para un registro antiguo.');
  qa_check((int)$items[0]['id'] === $pending,'El orden en el mismo segundo no es determinista.');
  echo "OK: secuencias antiguas irregulares, movimientos del mismo segundo y operadores desconocidos\n";

  $night = qa_staff($qa);
  qa_event($qa,$night,'ENTRADA','2001-02-03 23:59:59');
  qa_event($qa,$night,'SALIDA','2001-02-04 00:00:00','APROBADO','GUARDIA');
  foreach (['2001-02-03','2001-02-04'] as $date) {
    $items = qa_json($qa,'api/registros_resumen.php?rut='.$night.'&fecha='.$date)['items'];
    qa_check(count($items) === 1 && $items[0]['entrada'] === '2001-02-03 23:59:59' && $items[0]['salida'] === '2001-02-04 00:00:00','El filtro de día perdió o cortó una pareja nocturna.');
  }
  qa_check(qa_json($qa,'api/registros_resumen.php?rut='.$night.'&fecha=2001-02-05')['items'] === [],'El filtro de día incluye movimientos ajenos.');
  qa_json($qa,'api/registros_resumen.php',403,'CONTROL_VISITA');
  qa_json($qa,'api/registros_resumen.php',401,'SIN_SESION');
  $page = qa_http($qa['sessions']['ADMIN'],'registros.php');
  qa_check($page['status'] === 200 && str_contains($page['body'],'<th>Movimiento</th>'),'La tabla no distingue el tipo de movimiento.');
  echo "OK: parejas nocturnas visibles en ambos días, fechas completas y acceso protegido\n";
});