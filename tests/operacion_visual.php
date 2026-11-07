<?php
require_once __DIR__.'/support/accesos_fixture.php';
function visual_lookup(array $qa,string $rut): array {
  $response=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>$rut]);
  qa_check($response['status']===200,'La consulta de validación falló.');
  return json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
}
qa_run(function(array &$qa):void{
  $rut=qa_staff($qa);
  $fresh=visual_lookup($qa,$rut);
  qa_check($fresh['presencia']==='SIN_MOVIMIENTOS' && $fresh['ultimo_movimiento']===null,'Se inventó presencia para una persona sin movimientos.');
  qa_event($qa,$rut,'ENTRADA','2001-02-03 23:59:59','APROBADO','ADMIN');
  qa_event($qa,$rut,'SALIDA','2001-02-04 01:00:00','RECHAZADO','GUARDIA');
  $inside=visual_lookup($qa,$rut);
  qa_check($inside['presencia']==='DENTRO' && $inside['ultimo_movimiento']['fecha_hora']==='2001-02-03 23:59:59','El rechazo cambió la presencia consultada.');
  qa_check($inside['ultimo_movimiento']['operador']===$qa['users']['ADMIN']['username'] && $inside['ultimo_movimiento']['puesto']==='Pórtico Norte','Se perdió el puesto u operador del último aprobado.');
  qa_event($qa,$rut,'SALIDA','2001-02-03 23:59:59','APROBADO','GUARDIA');
  $outside=visual_lookup($qa,$rut);
  qa_check($outside['presencia']==='FUERA' && $outside['ultimo_movimiento']['tipo']==='SALIDA','El desempate del mismo segundo no funciona en la ficha.');
  $response=qa_http($qa['sessions']['GUARDIA'],'api/registrar_evento.php',['rut'=>$rut,'tipo'=>'ENTRADA','puesto'=>'Pórtico Sur']);
  $saved=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
  qa_check($saved['resultado']==='APROBADO' && $saved['guardado'] && $saved['operador']===$qa['users']['GUARDIA']['username'],'La confirmación no identifica a quien guardó.');
  $query=$qa['pdo']->prepare('SELECT fecha_hora FROM registros_acceso WHERE id=?');$query->execute([$saved['id']]);
  qa_check($saved['fecha_hora']===$query->fetchColumn(),'La hora mostrada no corresponde al registro real.');
  $bad=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>[]]);
  qa_check(json_decode($bad['body'],true)['ok']===false && $bad['status']===200,'Un RUT mal formado provoca error interno.');
  $page=qa_http($qa['sessions']['ADMIN'],'index.php');
  qa_check(str_contains($page['body'],'id="fichaFuncionario"') && str_contains($page['body'],'validacion.js') && str_contains($page['body'],'Enter</kbd> consulta'),'No se integra la nueva ficha y operación con Enter.');
  echo "OK: ficha sin movimientos/dentro/fuera, rechazos, mismo segundo y confirmación con fecha y operador reales\n";
});