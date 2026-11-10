<?php
require_once __DIR__.'/support/accesos_fixture.php';
qa_run(function(array &$qa):void{
  $ruts=[qa_staff($qa),qa_staff($qa),qa_staff($qa,'INACTIVO')];
  $users=[];
  try{
    $qa['pdo']->prepare('UPDATE funcionarios SET nombres=? WHERE rut=?')->execute([$qa['marker'].' %',$ruts[0]]);
    $qa['pdo']->prepare('UPDATE funcionarios SET nombres=? WHERE rut=?')->execute([$qa['marker'].' Muñoz',$ruts[1]]);
    $entryIds=[];
    for($i=0;$i<61;$i++){
      $entryIds[]=qa_event($qa,$ruts[0],'ENTRADA','2001-02-03 12:00:00');
      $exit=qa_event($qa,$ruts[0],'SALIDA','2001-02-03 12:00:00');
      if($i===0)$qa['pdo']->prepare("UPDATE registros_acceso SET puesto='Pórtico Sur' WHERE id=?")->execute([$exit]);
    }
    $found=[];
    for($page=1;$page<=3;$page++){
      $data=qa_json($qa,'api/registros_resumen.php?rut='.$ruts[0].'&limit=25&page='.$page);
      qa_check($data['total']===61 && $data['pages']===3 && $data['page']===$page,'El historial oculta el total o páginas.');
      qa_check($data['from']===($page-1)*25+1 && $data['to']===min(61,$page*25),'El rango mostrado no corresponde a la página.');
      $found=array_merge($found,array_map('intval',array_column($data['items'],'id')));
      foreach($data['items'] as $row)qa_check($row['entrada']!==null && $row['salida']!==null,'La paginación corta una pareja.');
    }
    qa_check($found===array_reverse($entryIds),'Las páginas repiten, pierden o desordenan parejas en el mismo segundo.');
    $filtered=qa_json($qa,'api/registros_resumen.php?rut='.$ruts[0].'&puesto='.rawurlencode('Pórtico Sur'));
    qa_check($filtered['total']===1 && $filtered['items'][0]['puesto_salida']==='Pórtico Sur','Filtrar por el puesto de salida rompe la pareja.');
    $literal=qa_json($qa,'api/registros_resumen.php?q='.rawurlencode($qa['marker'].' %').'&limit=100');
    qa_check($literal['total']===61 && count($literal['items'])===61,'El porcentaje en la búsqueda funciona como comodín.');
    $formatted=number_format((int)$ruts[0],0,',','.');
    qa_check(qa_json($qa,'api/registros_resumen.php?q='.rawurlencode($formatted))['total']===61,'La búsqueda de RUT con puntos falla.');
    $last=qa_json($qa,'api/ultimos.php?rut='.$ruts[0].'&limit=100&page=2');
    qa_check($last['total']===122 && count($last['items'])===22,'El historial individual mantiene un límite oculto.');
    $clamped=qa_json($qa,'api/registros_resumen.php?rut='.$ruts[0].'&limit=25&page=999');
    qa_check($clamped['page']===3 && count($clamped['items'])===11,'Una página fuera del rango queda vacía sin corregirse.');
    echo "OK: 61 parejas y 122 movimientos accesibles, orden estable, nombre/RUT/puesto y búsquedas literales\n";

    qa_event($qa,$ruts[1],'ENTRADA','2001-02-03 23:00:00');
    $south=qa_event($qa,$ruts[2],'ENTRADA','2001-02-02 23:00:00');
    $qa['pdo']->prepare("UPDATE registros_acceso SET puesto='Pórtico Sur' WHERE id=?")->execute([$south]);
    $inside=qa_json($qa,'api/personal_dentro.php?q='.$qa['marker'].'&limit=1&page=1');
    $inside2=qa_json($qa,'api/personal_dentro.php?q='.$qa['marker'].'&limit=1&page=2');
    qa_check($inside['total']===2 && $inside2['total']===2 && $inside['items'][0]['rut']!==$inside2['items'][0]['rut'],'La tabla de presencia pierde personas.');
    $insideFiltered=qa_json($qa,'api/personal_dentro.php?q='.$qa['marker'].'&puesto='.rawurlencode('Pórtico Sur').'&page=1');
    qa_check($insideFiltered['total']===1 && $insideFiltered['total_dentro']===$inside['total_dentro'],'El filtro cambia el total físico dentro.');
    $accent=qa_json($qa,'api/personal_dentro.php?q='.rawurlencode($qa['marker'].' Munoz').'&page=1');
    qa_check($accent['total']===1 && $accent['items'][0]['rut']===$ruts[1],'No se encuentra un nombre sin escribir la tilde.');
    $visit=$qa['pdo']->prepare("INSERT INTO visitas(nombre,rut,modo,patente,puesto,fecha_hora_entrada,fecha_hora_salida,estado) VALUES (?,?,'VEHICULO',?,'Pórtico de Visitas','2001-02-03 12:00:00','2001-02-03 13:00:00','SALIO')");
    for($i=0;$i<81;$i++){$visit->execute([$qa['marker'].' Muñoz',qa_rut_completo($ruts[0]),$i===0?'ABCD12':'EFGH34']);$qa['visits'][]=(int)$qa['pdo']->lastInsertId();}
    $ids=[];
    for($page=1;$page<=4;$page++){
      $data=qa_json($qa,'api/visitas_listar.php?vista=historial&q='.rawurlencode($qa['marker'].' Munoz').'&limit=25&page='.$page);
      qa_check($data['total']===81 && $data['pages']===4,'La búsqueda de visitas mantiene un límite de 80.');
      $ids=array_merge($ids,array_map('intval',array_column($data['items'],'id')));
    }
    qa_check($ids===array_reverse($qa['visits']),'Las páginas de visitas duplican o pierden filas.');
    qa_check(qa_json($qa,'api/visitas_listar.php?rut='.qa_rut_completo($ruts[0]).'&q=ABCD12')['total']===1,'No se busca por patente.');
    echo "OK: presencia paginada sin alterar el total, nombres con tildes y 81 visitas por nombre/RUT/patente\n";

    $create=$qa['pdo']->prepare('INSERT INTO usuarios(username,password_hash,rol,activo) VALUES (?,?,?,?)');
    for($i=0;$i<3;$i++){$create->execute([$qa['marker'].'_'.$i,'sin-acceso-de-prueba',$i===2?'AUDITOR':'GUARDIA',$i===0?0:1]);$users[]=(int)$qa['pdo']->lastInsertId();}
    $userPage=qa_json($qa,'api/admin_users_list.php?q='.$qa['marker'].'&limit=1&page=2');
    qa_check($userPage['total']===3 && $userPage['pages']===3 && count($userPage['items'])===1,'Los usuarios no se paginan.');
    qa_check(qa_json($qa,'api/admin_users_list.php?q='.$qa['marker'].'&activo=0')['total']===1,'No se filtra usuario inactivo.');
    qa_check(qa_json($qa,'api/admin_users_list.php?q='.$qa['marker'].'&rol=GUARDIA')['total']===2,'No se filtra por rol.');
    $staff=qa_json($qa,'api/admin_funcionarios.php?q='.$qa['marker'].'&limit=1&page=3');
    qa_check($staff['total']===3 && $staff['page']===3 && count($staff['items'])===1,'Funcionarios no respeta filas por página.');
    foreach(['api/registros_resumen.php','api/personal_dentro.php','api/ultimos.php','api/visitas_listar.php','api/admin_users_list.php'] as $endpoint){
      qa_json($qa,$endpoint.'?limit=0',400);qa_json($qa,$endpoint.'?page%5B%5D=1',400);
      qa_json($qa,$endpoint.'?q%5B%5D=nombre',400);
      qa_check(qa_json($qa,$endpoint.'?q='.rawurlencode("zz' OR 1=1 --"))['total']===0,'Una búsqueda altera el SQL.');
    }
    echo "OK: usuarios por rol/estado, funcionarios con filas seleccionables y parámetros inválidos rechazados\n";
  }finally{
    foreach($users as $id)$qa['pdo']->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    foreach($ruts as $rut)$qa['pdo']->prepare('UPDATE funcionarios SET nombres=? WHERE rut=?')->execute([$qa['marker'],$rut]);
  }
});