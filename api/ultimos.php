<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/tablas.php';
require_api_roles(['ADMIN','GUARDIA','AUDITOR']);
try{
  $pdo=db();$where=[];$params=[];
  $rut=campo_texto($_GET['rut'] ?? '','RUT',30);
  $search=campo_texto($_GET['q'] ?? '','búsqueda',120);
  $puesto=campo_texto($_GET['puesto'] ?? '','puesto',60);
  $result=campo_texto($_GET['resultado'] ?? '','resultado',10);
  if(!in_array($result,['','APROBADO','RECHAZADO'],true))throw new InvalidArgumentException('Resultado inválido.');
  if($rut!==''){$where[]="r.rut LIKE ? ESCAPE '!'";$params[]=tabla_like(tabla_rut_busqueda($rut));}
  if($search!==''){$where[]="(f.nombres LIKE ? ESCAPE '!' OR r.rut LIKE ? ESCAPE '!')";array_push($params,tabla_like($search),tabla_like(tabla_rut_busqueda($search)));}
  if($puesto!==''){$where[]='r.puesto=?';$params[]=$puesto;}
  if($result!==''){$where[]='r.resultado=?';$params[]=$result;}
  $desde=$_GET['desde'] ?? ''; $hasta=$_GET['hasta'] ?? '';
  if($desde!==''){[$start]=limites_dia_chile($desde);$where[]='r.fecha_hora>=?';$params[]=$start;}
  if($hasta!==''){[, $end]=limites_dia_chile($hasta);$where[]='r.fecha_hora<?';$params[]=$end;}
  if($desde!=='' && $hasta!=='')limites_rango_chile($desde,$hasta);
  $from=' FROM registros_acceso r INNER JOIN funcionarios f ON f.rut=r.rut LEFT JOIN usuarios u ON u.id=r.operador_id'.($where?' WHERE '.implode(' AND ',$where):'');
  $pdo->beginTransaction();
  $count=$pdo->prepare('SELECT COUNT(*)'.$from);$count->execute($params);
  $pagination=tabla_pagina($_GET,(int)$count->fetchColumn(),15);
  $query=$pdo->prepare('SELECT r.id,r.rut,f.nombres,r.tipo,r.modo,r.fecha_hora,r.puesto,r.resultado,r.motivo,r.operador_id,u.username AS operador'.$from.' ORDER BY r.fecha_hora DESC,r.id DESC LIMIT '.$pagination['limit'].' OFFSET '.$pagination['offset']);
  $query->execute($params);$items=$query->fetchAll();$pdo->commit();
  echo json_encode(['ok'=>true,'items'=>$items]+$pagination,JSON_UNESCAPED_UNICODE);
}catch(InvalidArgumentException $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(400);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log('Movimientos individuales: '.$error->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudieron consultar los movimientos.'],JSON_UNESCAPED_UNICODE);
}