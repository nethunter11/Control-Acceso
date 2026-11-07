<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/tablas.php';
require_api_roles(['ADMIN']);
try{
  $search=campo_texto($_GET['q'] ?? '','usuario',60);
  $role=campo_texto($_GET['rol'] ?? '','rol',30);
  $active=campo_texto($_GET['activo'] ?? '','estado',1);
  if(!in_array($role,['','ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'],true) || !in_array($active,['','0','1'],true))throw new InvalidArgumentException('Rol o estado inválido.');
  $where=[];$params=[];
  if($search!==''){$where[]="username LIKE ? ESCAPE '!'";$params[]=tabla_like($search);}
  if($role!==''){$where[]='rol=?';$params[]=$role;}
  if($active!==''){$where[]='activo=?';$params[]=(int)$active;}
  $from=' FROM usuarios'.($where?' WHERE '.implode(' AND ',$where):'');
  $actor=auth_user();$pdo=db();$pdo->beginTransaction();
  $count=$pdo->prepare('SELECT COUNT(*)'.$from);$count->execute($params);
  $pagination=tabla_pagina($_GET,(int)$count->fetchColumn());
  $query=$pdo->prepare('SELECT id,username,rol,activo'.$from.' ORDER BY username,id LIMIT '.$pagination['limit'].' OFFSET '.$pagination['offset']);
  $query->execute($params);$items=$query->fetchAll();$pdo->commit();
  echo json_encode(['ok'=>true,'items'=>$items,'current_user_id'=>(int)$actor['id']]+$pagination,JSON_UNESCAPED_UNICODE);
}catch(InvalidArgumentException $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(400);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log('Lista de usuarios: '.$error->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudieron consultar los usuarios.'],JSON_UNESCAPED_UNICODE);
}