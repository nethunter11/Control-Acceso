<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/funcionarios.php';
require_once __DIR__.'/../app/tablas.php';
require_api_roles(['ADMIN'],['GET','POST']);
try {
  $pdo=db();
  if($_SERVER['REQUEST_METHOD']==='GET') {
    if(($_GET['plantilla'] ?? '')==='1') {
      header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="plantilla_funcionarios.csv"');
      echo "\xEF\xBB\xBFrut,nombres,unidad,grado,estado\r\n"; exit;
    }
    $q=campo_texto($_GET['q'] ?? '','búsqueda',120);
    $state=campo_texto($_GET['estado'] ?? '','estado',8);
    if(!in_array($state,['','ACTIVO','INACTIVO'],true)) throw new InvalidArgumentException('Estado inválido.');
    $page=filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($page===false) throw new InvalidArgumentException('Página inválida.');
    $where=[]; $params=[];
    if($q!=='') { $where[]="(rut LIKE ? ESCAPE '!' OR nombres LIKE ? ESCAPE '!' OR unidad LIKE ? ESCAPE '!')"; $params=[tabla_like(tabla_rut_busqueda($q)),tabla_like($q),tabla_like($q)]; }
    if($state!=='') { $where[]='estado=?'; $params[]=$state; }
    $clause=$where?' WHERE '.implode(' AND ',$where):'';
    $pdo->beginTransaction();
    $query=$pdo->prepare('SELECT COUNT(*) FROM funcionarios'.$clause); $query->execute($params); $total=(int)$query->fetchColumn();
    $pagination=tabla_pagina($_GET,$total);$page=$pagination['page'];
    $query=$pdo->prepare('SELECT rut,nombres,unidad,grado,estado,fecha_actualizacion FROM funcionarios'.$clause.' ORDER BY nombres,rut LIMIT '.$pagination['limit'].' OFFSET '.$pagination['offset']);
    $query->execute($params); $rows=$query->fetchAll();
    foreach($rows as &$row) $row['revision']=funcionario_revision($row); unset($row);
    $pdo->commit();
    echo json_encode(['ok'=>true,'items'=>$rows]+$pagination,JSON_UNESCAPED_UNICODE); exit;
  }
  if((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>2097152) throw new InvalidArgumentException('El archivo es demasiado grande.');
  $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
  if(!is_array($data)) throw new InvalidArgumentException('Solicitud inválida.');
  $action=$data['accion'] ?? '';
  if($action==='guardar') {
    $staff=funcionario_validar($data);
    $revision=$data['revision'] ?? null;
    if($revision!==null && (!is_string($revision) || !preg_match('/^[a-f0-9]{64}$/D',$revision))) throw new InvalidArgumentException('Actualiza la ficha antes de guardar.');
    ejecutar_por_rut($pdo,'administracion','funcionarios',function () use($pdo,$staff,$revision): array { funcionario_escribir($pdo,$staff,$revision); return []; });
    echo json_encode(['ok'=>true,'rut'=>$staff['rut']],JSON_UNESCAPED_UNICODE);
  } elseif($action==='preview') {
    unset($_SESSION['staff_import']);
    $preview=funcionarios_preview($pdo,$data['csv'] ?? null,$data['modo'] ?? 'crear');
    $id=bin2hex(random_bytes(16));
    if(!$preview['errores']) $_SESSION['staff_import']=['id'=>$id,'expires'=>time()+600,'preview'=>$preview];
    echo json_encode(['ok'=>true,'preview'=>$preview,'id'=>$preview['errores']?null:$id],JSON_UNESCAPED_UNICODE);
  } elseif($action==='importar') {
    $pending=$_SESSION['staff_import'] ?? null;
    if(!$pending || !is_string($data['id'] ?? null) || !hash_equals($pending['id'],$data['id']) || $pending['expires']<=time()) throw new FuncionarioConflicto('La vista previa venció. Vuelve a revisar el archivo.');
    $preview=$pending['preview'];
    unset($_SESSION['staff_import']);
    ejecutar_por_rut($pdo,'administracion','funcionarios',function () use($pdo,$preview): array {
      $rows=$preview['rows']; usort($rows,fn($a,$b)=>strcmp($a['data']['rut'],$b['data']['rut']));
      foreach($rows as $row) funcionario_escribir($pdo,$row['data'],$row['revision']);
      return [];
    });
    echo json_encode(['ok'=>true,'creados'=>$preview['crear'],'actualizados'=>$preview['actualizar']],JSON_UNESCAPED_UNICODE);
  } else throw new InvalidArgumentException('Operación inválida.');
} catch(FuncionarioConflicto $error) {
  http_response_code(409); echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException|JsonException $error) {
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(422); echo json_encode(['ok'=>false,'error'=>$error instanceof JsonException?'Solicitud JSON inválida.':$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch(BloqueoRutOcupado $error) {
  http_response_code(409); echo json_encode(['ok'=>false,'error'=>'Otra edición está en curso. Reintenta.'],JSON_UNESCAPED_UNICODE);
} catch(Throwable $error) {
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log('Funcionarios: '.$error->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'No se pudieron guardar o consultar los funcionarios.'],JSON_UNESCAPED_UNICODE);
}