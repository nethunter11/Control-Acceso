<?php
require_once __DIR__.'/support/accesos_fixture.php';
qa_run(function (array &$qa): void {
  $rut=qa_staff($qa);
  $query=$qa['pdo']->prepare("INSERT INTO visitas (nombre,rut,puesto,fecha_hora_entrada,estado) VALUES (?,?,'Pórtico de Visitas','2001-02-03 23:00:00','DENTRO')");
  $query->execute([$qa['marker'],$rut]); $legacy=(int)$qa['pdo']->lastInsertId(); $qa['visits'][]=$legacy;
  $inside=qa_json($qa,'api/visitas_listar.php?vista=dentro&fecha=2026-11-07&rut='.$rut);
  qa_check(count($inside['items'])===1 && (int)$inside['items'][0]['id']===$legacy,'No se muestra el ingreso pendiente antiguo.');
  qa_check($inside['items'][0]['operador_entrada']===null && $inside['items'][0]['operador_salida']===null,'Se atribuyó un operador a un registro antiguo.');
  $response=qa_http($qa['sessions']['CONTROL_VISITA'],'api/visitas_salida.php',['id'=>$legacy,'operador_salida_id'=>$qa['users']['ADMIN']['id']]);
  $exit=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
  qa_check($response['status']===200 && $exit['resultado']==='APROBADO' && (int)$exit['operador_salida_id']===(int)$qa['users']['CONTROL_VISITA']['id'],'La salida no usa la identidad de la sesión.');
  $again=qa_http($qa['sessions']['ADMIN'],'api/visitas_salida.php',['id'=>$legacy]);
  qa_check(json_decode($again['body'],true)['guardado']===false,'Se sobrescribió una salida previa.');
  $closed=qa_json($qa,'api/visitas_listar.php?rut='.$rut)['items'][0];
  qa_check($closed['operador_salida']===$qa['users']['CONTROL_VISITA']['username'],'Se cambió o perdió el operador de salida.');
  qa_check(qa_json($qa,'api/visitas_listar.php?vista=dentro&rut='.$rut)['total']===0,'Una visita cerrada sigue dentro.');
  for($i=0;$i<3;$i++) { $query->execute([$qa['marker'],$rut]); $qa['visits'][]=(int)$qa['pdo']->lastInsertId(); }
  $one=qa_json($qa,'api/visitas_listar.php?vista=dentro&rut='.$rut.'&limit=2&page=1');
  $two=qa_json($qa,'api/visitas_listar.php?vista=dentro&rut='.$rut.'&limit=2&page=2');
  qa_check($one['total']===3 && count($one['items'])===2 && count($two['items'])===1,'La paginación oculta el total.');
  qa_check(count(array_unique(array_merge(array_column($one['items'],'id'),array_column($two['items'],'id'))))===3,'Las páginas duplican movimientos del mismo segundo.');
  $csv=qa_http($qa['sessions']['ADMIN'],'api/admin_export_csv.php?table=visitas&fecha=2001-02-03');
  qa_check(str_contains($csv['body'],'operador_salida_id,operador_salida') && str_contains($csv['body'],$qa['users']['CONTROL_VISITA']['username']),'El CSV no incluye el operador de salida.');
  foreach(['GUARDIA','AUDITOR'] as $role) qa_json($qa,'api/visitas_listar.php',403,$role);
  echo "OK: visitas antiguas dentro, salida auditada, duplicados, paginación y CSV con ambos operadores\n";
});