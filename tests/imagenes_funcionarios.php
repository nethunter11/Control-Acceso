<?php
require_once __DIR__.'/support/accesos_fixture.php';
function imagen_guardar(array $qa,array $data,int $expected=200):array {
  $r=qa_http($qa['sessions']['ADMIN'],'api/admin_funcionarios.php',$data);
  qa_check($r['status']===$expected,'Estado inesperado al guardar imagen: '.$r['status']);
  return json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
}
qa_run(function(array &$qa):void {
  $rut=qa_staff($qa);
  $get=fn()=>qa_json($qa,'api/admin_funcionarios.php?q='.rawurlencode($rut))['items'][0];
  $lookup=function()use($qa,$rut):array{
    $r=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>$rut]);
    qa_check($r['status']===200,'Fallo de consulta de la ficha.');
    return json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
  };
  qa_check($get()['imagen']===null && $lookup()['funcionario']['imagen_url']===null,'Se asigna una imagen sin indicación.');
  $data=['accion'=>'guardar','rut'=>$rut,'nombres'=>$qa['marker'],'unidad'=>'Unidad de prueba','grado'=>'Cabo','estado'=>'ACTIVO'];
  $stale=null;
  foreach(['Imagen_hombre.png','Imagen_mujer.png'] as $file){
    $current=$get();$stale=$current['revision'];
    imagen_guardar($qa,$data+['revision'=>$current['revision'],'imagen'=>$file]);
    qa_check($get()['imagen']===$file,'La imagen elegida no se conserva.');
    $card=$lookup();
    qa_check($card['existe'] && $card['funcionario']['imagen_url']==='/Control-Acceso/img/funcionarios/'.$file,'La consulta no devuelve la imagen asociada.');
  }
  imagen_guardar($qa,$data+['revision'=>$stale,'imagen'=>'Imagen_hombre.png'],409);
  foreach(['../../app/config.local.php','https://example.com/foto.png','otro.png',[],42] as $bad){
    imagen_guardar($qa,$data+['revision'=>$get()['revision'],'imagen'=>$bad],422);
    qa_check($get()['imagen']==='Imagen_mujer.png','Un valor inválido cambió la imagen.');
  }
  imagen_guardar($qa,array_replace($data,['revision'=>$get()['revision'],'unidad'=>'Unidad modificada']));
  qa_check($get()['imagen']==='Imagen_mujer.png' && $get()['unidad']==='Unidad modificada','Un cliente anterior perdió la imagen o no guardó la edición.');
  $csv="rut,nombres,unidad,grado,estado\r\n".$rut.','.$qa['marker'].",Unidad CSV,Cabo,ACTIVO\r\n";
  $preview=imagen_guardar($qa,['accion'=>'preview','csv'=>$csv,'modo'=>'actualizar']);
  qa_check($preview['preview']['errores']===0,'La vista previa dejó de admitir el CSV anterior.');
  imagen_guardar($qa,['accion'=>'importar','id'=>$preview['id']]);
  qa_check($get()['imagen']==='Imagen_mujer.png' && $get()['unidad']==='Unidad CSV','El CSV borró la imagen o no actualizó sus datos.');
  imagen_guardar($qa,$data+['revision'=>$get()['revision'],'imagen'=>'']);
  qa_check($get()['imagen']===null && $lookup()['funcionario']['imagen_url']===null,'No se puede retirar la imagen elegida.');
  qa_check((int)$qa['pdo']->query('SELECT COUNT(*) FROM registros_acceso WHERE rut='.$qa['pdo']->quote($rut))->fetchColumn()===0,'Consultar la imagen creó movimientos.');
  qa_check(!in_array('imagen',$qa['pdo']->query('SHOW COLUMNS FROM visitas')->fetchAll(PDO::FETCH_COLUMN),true),'Se modificó la tabla visitas.');
  $unknown=qa_staff($qa);
  $qa['pdo']->prepare('DELETE FROM funcionarios WHERE rut=?')->execute([$unknown]);
  $r=qa_http($qa['sessions']['GUARDIA'],'api/validar_rut.php',['rut'=>$unknown]);
  $missing=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
  qa_check(!$missing['existe'] && $missing['funcionario']===null,'Un RUT ajeno al padrón recibe una ficha con imagen.');
  echo "OK: imágenes asociadas a funcionarios, rutas permitidas, revisión concurrente, CSV compatible y visitas sin cambios.\n";
});
