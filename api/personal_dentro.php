<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/tablas.php';
require_api_roles(['ADMIN','GUARDIA']);
try{
  $pdo=db();$search=campo_texto($_GET['q'] ?? $_GET['rut'] ?? '','búsqueda',120);
  $puesto=campo_texto($_GET['puesto'] ?? '','puesto',60);
  $cte="WITH ultimos AS (SELECT rut,tipo,modo,puesto,fecha_hora,id,ROW_NUMBER() OVER (PARTITION BY rut ORDER BY id DESC) AS rn FROM registros_acceso WHERE resultado='APROBADO')";
  $from=" FROM ultimos t INNER JOIN funcionarios f ON f.rut=t.rut WHERE t.rn=1 AND t.tipo='ENTRADA'";
  $params=[];
  $pdo->beginTransaction();
  $totalInside=(int)$pdo->query($cte.' SELECT COUNT(*)'.$from)->fetchColumn();
  if($search!==''){$from.=" AND (f.nombres LIKE ? ESCAPE '!' OR t.rut LIKE ? ESCAPE '!' OR f.unidad LIKE ? ESCAPE '!')";$params=[tabla_like($search),tabla_like(tabla_rut_busqueda($search)),tabla_like($search)];}
  if($puesto!==''){$from.=' AND t.puesto=?';$params[]=$puesto;}
  $query=$pdo->prepare($cte.' SELECT COUNT(*)'.$from);$query->execute($params);$total=(int)$query->fetchColumn();
  $pagination=tabla_pagina($_GET,$total);
  $sql=$cte.' SELECT t.rut,f.nombres,f.unidad,f.grado,f.estado,t.modo,t.puesto,t.fecha_hora AS ultima_hora'.$from.' ORDER BY t.fecha_hora DESC,t.id DESC';
  // Los consumidores anteriores siguen recibiendo la lista completa si no solicitan página.
  if(!isset($_GET['page']) && !isset($_GET['limit']))$pagination=['total'=>$total,'page'=>1,'pages'=>1,'limit'=>$total,'offset'=>0,'from'=>$total?1:0,'to'=>$total];
  if(isset($_GET['page']) || isset($_GET['limit']))$sql.=' LIMIT '.$pagination['limit'].' OFFSET '.$pagination['offset'];
  $query=$pdo->prepare($sql);$query->execute($params);$items=$query->fetchAll();$puestos=tabla_puestos($pdo);$pdo->commit();
  echo json_encode(['ok'=>true,'fecha'=>date('Y-m-d'),'checked_at'=>date('Y-m-d H:i:s'),'items'=>$items,'total_dentro'=>$totalInside,'puestos'=>$puestos]+$pagination,JSON_UNESCAPED_UNICODE);
}catch(InvalidArgumentException $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(400);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log('Personal dentro: '.$error->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudo consultar el personal dentro.'],JSON_UNESCAPED_UNICODE);
}