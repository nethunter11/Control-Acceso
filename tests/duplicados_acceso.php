<?php
require_once __DIR__.'/support/accesos_fixture.php';
function duplicados_enviar(array $qa,string $rut,string $tipo,string $role='GUARDIA',string $puesto='Pórtico Principal',string $modo='PEATONAL'):array {
  $response=qa_http($qa['sessions'][$role],'api/registrar_evento.php',['rut'=>$rut,'tipo'=>$tipo,'puesto'=>$puesto,'modo'=>$modo]);
  qa_check($response['status']===200,'Falló la solicitud de movimiento.');
  $data=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
  qa_check(($data['ok'] ?? false)===true,'El movimiento devolvió un error inesperado.');
  return $data;
}
function duplicados_rechazado(array $data):void {
  qa_check($data['resultado']==='RECHAZADO' && $data['guardado']===false && str_contains($data['motivo'],'Movimiento duplicado'),'Se aprobó o guardó un movimiento duplicado.');
}
function duplicados_presencia(array $qa,string $rut,string $presencia,int $ultimoId):void {
  $response=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>$rut]);
  $data=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR);
  qa_check($response['status']===200 && $data['presencia']===$presencia && $data['ultimo_movimiento']['id']===$ultimoId,'La ficha no refleja el último movimiento aprobado registrado.');
  $inside=qa_json($qa,'api/personal_dentro.php?rut='.rawurlencode($rut).'&limit=25&page=1');
  $ruts=array_column($inside['items'],'rut');
  qa_check(in_array($rut,$ruts,true)===($presencia==='DENTRO'),'Personal dentro discrepa del estado que controla los duplicados.');
}
qa_run(function(array &$qa):void {
  $rut=qa_staff($qa);
  $yesterday=date('Y-m-d',strtotime('-1 day'));
  $future=date('Y-m-d',strtotime('+2 days'));
  qa_event($qa,$rut,'ENTRADA',$yesterday.' 07:15:00');
  qa_event($qa,$rut,'SALIDA',$future.' 17:00:00');
  $entry=duplicados_enviar($qa,$rut,'ENTRADA');
  qa_check($entry['resultado']==='APROBADO' && $entry['guardado'],'La primera entrada no fue aprobada.');
  $count=$qa['pdo']->prepare('SELECT COUNT(*) FROM registros_acceso WHERE rut=?');$count->execute([$rut]);$before=(int)$count->fetchColumn();
  duplicados_rechazado(duplicados_enviar($qa,str_replace('-','',$rut),'ENTRADA','ADMIN','Pórtico Sur','VEHICULO'));
  $count->execute([$rut]);qa_check((int)$count->fetchColumn()===$before,'El rechazo agregó una fila duplicada.');
  duplicados_presencia($qa,$rut,'DENTRO',$entry['id']);
  qa_event($qa,$rut,'SALIDA',$future.' 18:00:00','RECHAZADO');
  duplicados_rechazado(duplicados_enviar($qa,$rut,'ENTRADA'));
  duplicados_presencia($qa,$rut,'DENTRO',$entry['id']);
  $exit=duplicados_enviar($qa,$rut,'SALIDA');
  qa_check($exit['resultado']==='APROBADO' && $exit['guardado'],'La salida posterior a la entrada fue rechazada.');
  duplicados_rechazado(duplicados_enviar($qa,$rut,'SALIDA','ADMIN','Pórtico Norte'));
  duplicados_presencia($qa,$rut,'FUERA',$exit['id']);
  $again=duplicados_enviar($qa,$rut,'ENTRADA','ADMIN');
  qa_check($again['resultado']==='APROBADO','No se permite reingresar después de salir.');
  duplicados_rechazado(duplicados_enviar($qa,$rut,'ENTRADA'));
  duplicados_presencia($qa,$rut,'DENTRO',$again['id']);
  echo "OK: entrada, rechazo de duplicados entre puestos y operadores, salida y reingreso con una fecha futura en el historial\n";

  $other=qa_staff($qa);
  qa_event($qa,$other,'ENTRADA',$future.' 08:00:00');
  $closed=duplicados_enviar($qa,$other,'SALIDA');
  qa_check($closed['resultado']==='APROBADO','No se puede cerrar una entrada registrada con fecha futura.');
  duplicados_rechazado(duplicados_enviar($qa,$other,'SALIDA'));
  duplicados_presencia($qa,$other,'FUERA',$closed['id']);
  echo "OK: salida no duplicable ante fechas que no coinciden con el orden de registro; ficha y Personal dentro coherentes\n";
});
