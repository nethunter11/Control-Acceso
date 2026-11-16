<?php
require_once __DIR__.'/support/accesos_fixture.php';
require_once __DIR__.'/../app/patentes_funcionarios.php';

qa_run(function(array &$qa):void{
  foreach(['PTCL21','ptcl21','ptcl-21',' PTCL 21 '] as $input)qa_check(patente_funcionario_validar($input)==='PTCL21','No se normalizó la patente.');
  foreach(['AB1234','ABCDE1','ABCD1','ABCD123','AÑCD12','<img>',[],42] as $input){
    try{patente_funcionario_validar($input);throw new RuntimeException('Se admitió una patente inválida.');}catch(InvalidArgumentException $expected){}
  }
  qa_check(patente_funcionario_validar('')===null && patente_funcionario_validar(null)===null,'No se permite una ficha peatonal sin patente.');
  $one=qa_staff($qa);$two=qa_staff($qa);
  $result=asignar_patentes_funcionarios($qa['pdo']);qa_check($result['asignadas']===2,'No se asignaron las dos patentes nuevas.');
  qa_check(asignar_patentes_funcionarios($qa['pdo'])['asignadas']===0,'Repetir la asignación cambia las patentes existentes.');
  $get=fn($rut)=>qa_json($qa,'api/admin_funcionarios.php?q='.$rut)['items'][0];
  $first=$get($one);$second=$get($two);$plate=$first['patentes'];
  qa_check($plate!==$second['patentes'] && preg_match('/^[A-Z]{4}[0-9]{2}$/D',$plate),'La asignación no es única o no tiene el formato solicitado.');
  try{$qa['pdo']->prepare('UPDATE funcionarios SET patentes=? WHERE rut=?')->execute([$plate,$two]);throw new RuntimeException('La base permite una patente duplicada.');}
  catch(PDOException $expected){qa_check((int)$expected->errorInfo[1]===1062,'Falló la restricción de unicidad.');}
  $save=function(array $payload,int $status=200)use($qa):array{
    $r=qa_http($qa['sessions']['ADMIN'],'api/admin_funcionarios.php',$payload);qa_check($r['status']===$status,'Estado inesperado en administración de patentes.');return json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
  };
  $data=['accion'=>'guardar','rut'=>$one,'nombres'=>$qa['marker'],'unidad'=>'Unidad','grado'=>'Cabo','estado'=>'ACTIVO'];
  $save($data+['revision'=>$first['revision'],'patentes'=>$second['patentes']],409);
  $save($data+['revision'=>$first['revision'],'patentes'=>'AB1234'],422);
  $save($data+['revision'=>$first['revision']]);qa_check($get($one)['patentes']===$plate,'Un cliente anterior borró la patente.');
  $csv="rut,nombres,unidad,grado,estado\n".$one.','.$qa['marker'].",Unidad,Cabo,ACTIVO\n";
  $preview=$save(['accion'=>'preview','csv'=>$csv,'modo'=>'actualizar']);$save(['accion'=>'importar','id'=>$preview['id']]);
  qa_check($get($one)['patentes']===$plate,'Un CSV anterior borró la patente.');
  $withPlate="rut,nombres,unidad,grado,estado,patentes\n".$one.','.$qa['marker'].",Unidad,Cabo,ACTIVO,".strtolower($plate)."\n";
  $preview=$save(['accion'=>'preview','csv'=>$withPlate,'modo'=>'actualizar']);
  qa_check($preview['preview']['errores']===0 && $preview['preview']['rows'][0]['data']['patentes']===$plate,'El CSV no valida su columna de patentes.');
  $save(['accion'=>'importar','id'=>$preview['id']]);
  $duplicate="rut,nombres,unidad,grado,estado,patentes\n".$one.','.$qa['marker'].",Unidad,Cabo,ACTIVO,".$second['patentes']."\n";
  qa_check($save(['accion'=>'preview','csv'=>$duplicate,'modo'=>'actualizar'])['preview']['errores']===1,'El CSV permite apropiarse de una patente existente.');
  $movement=function(string $type,string $mode,array $extra=[])use($qa,$one):array{
    $r=qa_http($qa['sessions']['GUARDIA'],'api/registrar_evento.php',['rut'=>$one,'tipo'=>$type,'modo'=>$mode,'puesto'=>'Pórtico Principal']+$extra);
    qa_check($r['status']===200,'Falló el registro vehicular.');return json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
  };
  $lookup=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>$one]);
  qa_check(json_decode($lookup['body'],true)['funcionario']['patentes']===$plate,'La consulta no entrega la patente asignada.');
  $entry=$movement('ENTRADA','VEHICULO',['patente'=>'ZZZZ99']);
  qa_check($entry['resultado']==='APROBADO' && $entry['patente']===$plate,'El registro aceptó la patente del cliente en lugar de la del padrón.');
  $inside=qa_json($qa,'api/personal_dentro.php?q='.$one)['items'][0];qa_check($inside['patente']===$plate,'Personal dentro no conserva la patente del ingreso.');
  $again=$movement('ENTRADA','VEHICULO');qa_check($again['guardado']===false && str_contains($again['motivo'],'duplicado'),'Las patentes rompieron el rechazo de entradas duplicadas.');
  $current=$get($one);
  do{$newPlate=patente_funcionario_generar();$owner=$qa['pdo']->prepare('SELECT COUNT(*) FROM funcionarios WHERE patentes=?');$owner->execute([$newPlate]);}while((int)$owner->fetchColumn()>0);
  $save($data+['revision'=>$current['revision'],'patentes'=>$newPlate]);
  $save($data+['revision'=>$current['revision'],'patentes'=>''],409);
  $exit=$movement('SALIDA','VEHICULO');qa_check($exit['resultado']==='APROBADO' && $exit['patente']===$newPlate,'La salida no conserva la patente vigente de su propio movimiento.');
  $event=$qa['pdo']->query('SELECT patente FROM registros_acceso WHERE id='.(int)$entry['id'])->fetchColumn();qa_check($event===$plate,'Editar la ficha cambió una patente histórica.');
  $history=qa_json($qa,'api/registros_resumen.php?rut='.$one)['items'][0];
  qa_check($history['patente_entrada']===$plate && $history['patente_salida']===$newPlate,'El historial confunde las patentes de entrada y salida.');
  $raw=qa_json($qa,'api/ultimos.php?rut='.$one)['items'];qa_check($raw[0]['patente']===$newPlate,'El historial individual pierde la patente.');
  $pedestrian=$movement('ENTRADA','PEATONAL',['patente'=>$plate]);qa_check($pedestrian['resultado']==='APROBADO' && $pedestrian['patente']===null,'Una entrada peatonal guardó una patente.');
  $movement('SALIDA','PEATONAL');
  $current=$get($one);$save($data+['revision'=>$current['revision'],'patentes'=>'']);
  $missing=$movement('ENTRADA','VEHICULO');qa_check($missing['guardado']===false && $missing['resultado']==='RECHAZADO' && str_contains($missing['motivo'],'patente'),'Se aprobó un vehículo sin patente registrada.');
  qa_check($movement('ENTRADA','PEATONAL')['resultado']==='APROBADO','Una ficha sin patente no puede ingresar peatonalmente.');
  $response=qa_http($qa['sessions']['ADMIN'],'api/admin_export_csv.php?table=registros_acceso&fecha='.date('Y-m-d'));
  qa_check($response['status']===200 && str_contains($response['body'],',patente') && str_contains($response['body'],$plate) && str_contains($response['body'],$newPlate),'El CSV no conserva las patentes históricas.');
  $staffCsv=qa_http($qa['sessions']['ADMIN'],'api/admin_export_csv.php?table=funcionarios');qa_check(str_contains($staffCsv['body'],',patentes'),'El CSV del padrón omite patentes.');
  foreach(['AUDITOR','CONTROL_VISITA'] as $role)qa_check(qa_http($qa['sessions'][$role],'api/registrar_evento.php',['rut'=>$one,'tipo'=>'ENTRADA','modo'=>'VEHICULO','puesto'=>'Pórtico Principal'])['status']===403,'Un rol sin permiso registró vehículos.');
  echo "OK: formato, unicidad, asignación repetible, administración y CSV compatibles, fuente del servidor, entradas/salidas, historial inmutable, peatonal sin patente, duplicados y permisos.\n";
});
