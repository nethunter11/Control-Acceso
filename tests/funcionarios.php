<?php
require_once __DIR__.'/support/accesos_fixture.php';
function staff_call(array $qa,array $data,int $status=200): array {
  $response=qa_http($qa['sessions']['ADMIN'],'api/admin_funcionarios.php',$data);
  qa_check($response['status']===$status,'HTTP inesperado en funcionarios: '.$response['status'].' ('.$data['accion'].')');
  return json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
}
function staff_csv(array $rows,string $separator=','): string {
  $stream=fopen('php://temp','w+');
  fputcsv($stream,['rut','nombres','unidad','grado','estado'],$separator,'"','');
  foreach($rows as $row) fputcsv($stream,$row,$separator,'"','');
  rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
}
qa_run(function (array &$qa): void {
  $reserved=[qa_staff($qa),qa_staff($qa),qa_staff($qa)]; sort($reserved);
  [$new,$extra,$rut]=$reserved;
  try {
    foreach($reserved as $id) $qa['pdo']->prepare('DELETE FROM funcionarios WHERE rut=?')->execute([$id]);
    $data=['accion'=>'guardar','rut'=>$rut,'nombres'=>$qa['marker'],'unidad'=>'Unidad Águila','grado'=>'Cabo','estado'=>'ACTIVO'];
    staff_call($qa,$data);
    qa_check(staff_call($qa,$data,409)['ok']===false,'Se sobrescribió un RUT existente.');
    staff_call($qa,array_replace($data,['rut'=>'12345678-0']),422);
    staff_call($qa,array_replace($data,['nombres'=>[]]),422);
    $get=fn()=>qa_json($qa,'api/admin_funcionarios.php?q='.$rut)['items'][0];
    $formatted=substr($rut,0,2).'.'.substr($rut,2,3).'.'.substr($rut,5);
    qa_check(count(qa_json($qa,'api/admin_funcionarios.php?q='.rawurlencode($formatted))['items'])===1,'No se puede buscar un RUT con puntos.');
    $current=$get(); $oldRevision=$current['revision'];
    $updated=array_replace($data,['revision'=>$oldRevision,'estado'=>'INACTIVO','unidad'=>'Nueva Unidad']);
    staff_call($qa,$updated);
    staff_call($qa,$updated,409);
    qa_check($get()['unidad']==='Nueva Unidad','Se perdió una edición.');
    qa_event($qa,$rut,'ENTRADA','2001-01-01 12:00:00');
    $entry=qa_http($qa['sessions']['GUARDIA'],'api/registrar_evento.php',['rut'=>$rut,'tipo'=>'ENTRADA','puesto'=>'Pórtico Principal']);
    qa_check(json_decode($entry['body'],true)['resultado']==='RECHAZADO','Un inactivo registró un ingreso.');
    $exit=qa_http($qa['sessions']['GUARDIA'],'api/registrar_evento.php',['rut'=>$rut,'tipo'=>'SALIDA','puesto'=>'Pórtico Principal']);
    qa_check(json_decode($exit['body'],true)['resultado']==='APROBADO','Un inactivo con ingreso abierto no puede salir.');
    echo "OK: alta, validación RUT, edición concurrente, desactivación y cierre de ingreso previo\n";

    $csv=staff_csv([[$new,$qa['marker'],'Unidad nueva','Soldado','ACTIVO'],[$rut,$qa['marker'],'Unidad importada','Capitán','ACTIVO']]);
    $rejected=staff_call($qa,['accion'=>'preview','csv'=>$csv,'modo'=>'crear']);
    qa_check($rejected['preview']['errores']===1 && $rejected['id']===null,'El modo solo nuevos permite reemplazar existentes.');
    qa_check($qa['pdo']->query("SELECT COUNT(*) FROM funcionarios WHERE rut=".$qa['pdo']->quote($new))->fetchColumn()==0,'La vista previa guardó datos.');
    $preview=staff_call($qa,['accion'=>'preview','csv'=>$csv,'modo'=>'actualizar']);
    qa_check($preview['preview']['crear']===1 && $preview['preview']['actualizar']===1 && $preview['preview']['errores']===0,'No se distinguen altas de actualizaciones.');
    $current=$get();
    staff_call($qa,array_replace($data,['revision'=>$current['revision'],'unidad'=>'Cambio posterior']));
    staff_call($qa,['accion'=>'importar','id'=>$preview['id']],409);
    qa_check($qa['pdo']->query("SELECT COUNT(*) FROM funcionarios WHERE rut=".$qa['pdo']->quote($new))->fetchColumn()==0,'Un conflicto dejó una importación parcial.');
    qa_check($get()['unidad']==='Cambio posterior','Un CSV sobrescribió una modificación posterior.');
    $preview=staff_call($qa,['accion'=>'preview','csv'=>$csv,'modo'=>'actualizar']);
    $result=staff_call($qa,['accion'=>'importar','id'=>$preview['id']]);
    qa_check($result['creados']===1 && $result['actualizados']===1,'No se importó el conjunto validado.');
    qa_check($get()['unidad']==='Unidad importada','No se aplicaron los cambios revisados.');
    staff_call($qa,['accion'=>'importar','id'=>$preview['id']],409);
    $badCsv=staff_csv([[$extra,$qa['marker'],'','','ACTIVO'],[$extra,$qa['marker'],'','','ACTIVO']]);
    $bad=staff_call($qa,['accion'=>'preview','csv'=>$badCsv,'modo'=>'crear']);
    qa_check($bad['preview']['errores']===1 && $bad['id']===null,'El CSV acepta RUT duplicados.');
    $semi="\xEF\xBB\xBF".staff_csv([[$extra,$qa['marker'],'Unidad Sur','Cabo','ACTIVO']],';');
    $semiPreview=staff_call($qa,['accion'=>'preview','csv'=>$semi,'modo'=>'crear']);
    qa_check($semiPreview['preview']['crear']===1 && $semiPreview['preview']['errores']===0,'No se admite BOM UTF-8 o punto y coma.');
    staff_call($qa,['accion'=>'preview','csv'=>"rut,nombre\n12345678-5,Uno",'modo'=>'crear'],422);
    staff_call($qa,['accion'=>'preview','csv'=>str_repeat('x',1048577),'modo'=>'crear'],422);
    foreach(['GUARDIA','AUDITOR','CONTROL_VISITA'] as $role) {
      qa_json($qa,'api/admin_funcionarios.php',403,$role);
      qa_check(qa_http($qa['sessions'][$role],'api/admin_funcionarios.php',$data)['status']===403,'El rol sin permisos pudo editar personal.');
    }
    qa_check(qa_http($qa['sessions']['ADMIN'],'api/admin_funcionarios.php',$data,null,false)['status']===403,'Funcionarios omite CSRF.');
    $page=qa_http($qa['sessions']['ADMIN'],'funcionarios.php');
    qa_check($page['status']===200 && str_contains($page['body'],'id="staffPreviewPanel"') && str_contains($page['body'],'funcionarios.js'),'Falta la pantalla de gestión.');
    echo "OK: vista previa sin escritura, CSV transaccional, conflictos, duplicados, BOM, permisos y CSRF\n";
  } finally {
    foreach($reserved as $id) $qa['pdo']->prepare('UPDATE funcionarios SET nombres=? WHERE rut=?')->execute([$qa['marker'],$id]);
  }
});