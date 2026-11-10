<?php
require_once __DIR__.'/support/accesos_fixture.php';
require_once __DIR__.'/../app/rut_funcionario.php';
require_once __DIR__.'/../app/migrar_rut_funcionarios.php';
require_once __DIR__.'/../app/funcionarios.php';
qa_run(function(array &$qa):void {
  // 12345678 también sería un RUT completo válido de siete cifras: aquí son ocho cifras del cuerpo.
  foreach(['12345678'=>'12345678','12.345.678'=>'12345678'," 12345678\r\n"=>'12345678','12345678-5'=>'12345678','20.420.416-0'=>'20420416','10000013-K'=>'10000013'] as $input=>$expected){
    qa_check(rut_funcionario_normalizar((string)$input)===$expected,'Se interpretó incorrectamente un RUT de funcionario.');
  }
  foreach(['','0','00000000','01234567','123456789','12345678-0','abc','1234/5678',[],null,12345678] as $bad){
    qa_check(rut_funcionario_normalizar($bad)==='','Se admitió un identificador inválido.');
  }
  $rut=qa_staff($qa);
  $legacy=null;
  foreach(array_merge(range(0,9),['K']) as $dv)if(rut_es_valido($rut.'-'.$dv)){$legacy=$rut.'-'.$dv;break;}
  $formatted=number_format((int)$rut,0,',','.');
  $qa['pdo']->prepare('UPDATE funcionarios SET imagen=? WHERE rut=?')->execute(['Imagen_hombre.png',$rut]);
  $call=function(string $path,array $data)use($qa):array{
    $role=$path==='visitas_entrada.php'?'CONTROL_VISITA':'GUARDIA';
    $response=qa_http($qa['sessions'][$role],'api/'.$path,$data);
    qa_check($response['status']===200,'Falló la API '.$path);
    return json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
  };
  foreach([$rut,$formatted,$legacy] as $input){
    $card=$call('validar_rut.php',['rut'=>$input]);
    qa_check($card['existe'] && $card['rut']===$rut && $card['funcionario']['rut']===$rut && str_ends_with($card['funcionario']['imagen_url'],'Imagen_hombre.png'),'La consulta perdió la identidad o la fotografía.');
  }
  $movement=['tipo'=>'ENTRADA','puesto'=>'Pórtico Principal'];
  qa_check($call('registrar_evento.php',['rut'=>$rut]+$movement)['resultado']==='APROBADO','No se registró la entrada sin DV.');
  foreach([$formatted,$legacy,$rut] as $input){
    $result=$call('registrar_evento.php',['rut'=>$input]+$movement);
    qa_check($result['resultado']==='RECHAZADO' && $result['guardado']===false,'Cambiar el formato permitió una entrada duplicada.');
  }
  qa_check(qa_json($qa,'api/personal_dentro.php?q='.$rut)['items'][0]['rut']===$rut,'Personal dentro no reconoce el RUT sin DV.');
  qa_check(qa_json($qa,'api/admin_funcionarios.php?q='.rawurlencode($formatted))['items'][0]['rut']===$rut,'La búsqueda alteró el último dígito del número.');
  $header="rut,nombres,unidad,grado,estado\n";
  $csvRow=','.$qa['marker'].",Unidad,Cabo,ACTIVO\n";
  $preview=funcionarios_preview($qa['pdo'],$header.$legacy.$csvRow,'actualizar');
  qa_check($preview['errores']===0 && $preview['rows'][0]['data']['rut']===$rut,'El CSV anterior no se convierte al identificador actual.');
  $duplicate=funcionarios_preview($qa['pdo'],$header.$rut.$csvRow.$legacy.$csvRow,'actualizar');
  qa_check($duplicate['errores']===1,'El CSV permite dos filas del mismo funcionario con formatos distintos.');
  $csv=qa_http($qa['sessions']['ADMIN'],'api/admin_export_csv.php?table=registros_acceso&fecha='.date('Y-m-d'));
  qa_check($csv['status']===200 && str_contains($csv['body'],','.$rut.',') && !str_contains($csv['body'],','.$legacy.','),'La exportación de accesos conserva un DV.');
  qa_check($call('registrar_evento.php',['rut'=>$rut,'tipo'=>'SALIDA','puesto'=>'Pórtico Sur'])['resultado']==='APROBADO','No se registró la salida sin DV.');
  $visitData=['nombre'=>$qa['marker'],'rut'=>'20420416'];
  $rejected=$call('visitas_entrada.php',$visitData);
  qa_check(($rejected['ok'] ?? true)===false && !isset($rejected['id']),'Visitas aceptó el cuerpo sin su DV.');
  $visit=$call('visitas_entrada.php',array_replace($visitData,['rut'=>$legacy]));
  if(isset($visit['id']))$qa['visits'][]=(int)$visit['id'];
  qa_check($visit['resultado']==='APROBADO' && $visit['rut']===$legacy,'Visitas dejó de guardar el RUT completo.');
  $export=qa_http($qa['sessions']['ADMIN'],'api/admin_export_csv.php?table=visitas&fecha='.date('Y-m-d'));
  qa_check($export['status']===200 && str_contains($export['body'],','.$legacy.','),'La exportación de visitas eliminó el DV.');
  echo "OK: número íntegro de 8 cifras, fotografías, entrada/salida, duplicados entre formatos, búsquedas y CSV; visitas con DV.\n";

  // La conversión detecta colisiones y revierte sin desactivar la integridad referencial.
  $original=$qa['pdo']->query('SELECT * FROM funcionarios WHERE rut='.$qa['pdo']->quote($rut))->fetch();
  $qa['pdo']->prepare('INSERT INTO funcionarios (rut,nombres,unidad,grado,estado,fecha_actualizacion,imagen) VALUES (?,?,?,?,?,?,?)')->execute([$legacy,$original['nombres'],$original['unidad'],$original['grado'],$original['estado'],$original['fecha_actualizacion'],$original['imagen']]);
  $qa['staff'][]=$legacy;
  try{migrar_rut_funcionarios($qa['pdo']);throw new LogicException('La conversión ignoró una colisión.');}
  catch(RuntimeException $error){qa_check(str_contains($error->getMessage(),'Dos fichas'),'Falló la validación de colisiones.');}
  $qa['pdo']->prepare('DELETE FROM registros_acceso WHERE rut=?')->execute([$rut]);
  $qa['pdo']->prepare('DELETE FROM funcionarios WHERE rut=?')->execute([$rut]);
  $event=qa_event($qa,$legacy,'ENTRADA','2001-01-01 07:00:00');
  $result=migrar_rut_funcionarios($qa['pdo']);
  qa_check($result===['funcionarios'=>1,'movimientos'=>1],'La conversión perdió filas.');
  qa_check($qa['pdo']->query('SELECT rut FROM registros_acceso WHERE id='.$event)->fetchColumn()===$rut,'La conversión perdió la relación del movimiento.');
  qa_check($qa['pdo']->query('SELECT * FROM funcionarios WHERE rut='.$qa['pdo']->quote($rut))->fetch()===$original,'La conversión cambió datos personales o la imagen.');
  qa_check(migrar_rut_funcionarios($qa['pdo'])===['funcionarios'=>0,'movimientos'=>0],'La conversión no es repetible.');
  echo "OK: conversión transaccional, colisiones, relaciones, imágenes e idempotencia.\n";
});
