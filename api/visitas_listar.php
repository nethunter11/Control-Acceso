<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/tablas.php';
require_api_roles(['ADMIN','CONTROL_VISITA']);
try {
  $pdo = db();
  $view = $_GET['vista'] ?? 'historial';
  if (!is_string($view) || !in_array($view,['dentro','historial'],true)) throw new InvalidArgumentException('Selecciona una vista válida.');
  $rut = $_GET['rut'] ?? '';
  if (!is_string($rut) || strlen($rut)>30) throw new InvalidArgumentException('Filtro de RUT inválido.');
  $where = []; $params = [];
  if ($rut !== '') { $where[]="v.rut LIKE ? ESCAPE '!'"; $params[]=tabla_like(tabla_rut_busqueda($rut)); }
  $search=campo_texto($_GET['q'] ?? '', 'búsqueda',120);
  if($search!=='') { $where[]="(v.nombre LIKE ? ESCAPE '!' OR v.rut LIKE ? ESCAPE '!' OR v.patente LIKE ? ESCAPE '!')"; array_push($params,tabla_like($search),tabla_like(tabla_rut_busqueda($search)),tabla_like($search)); }
  if ($view === 'dentro') $where[]="v.estado='DENTRO'";
  else {
    $state = $_GET['estado'] ?? '';
    if (!is_string($state) || !in_array($state,['','DENTRO','SALIO'],true)) throw new InvalidArgumentException('Estado inválido.');
    if ($state !== '') { $where[]='v.estado=?'; $params[]=$state; }
    $date = $_GET['fecha'] ?? '';
    if ($date !== '') {
      [$start,$end]=limites_dia_chile($date);
      $where[]='v.fecha_hora_entrada >= ? AND v.fecha_hora_entrada < ?';
      $params[]=$start; $params[]=$end;
    }
  }
  $clause=$where ? ' WHERE '.implode(' AND ',$where) : '';
  $pdo->beginTransaction();
  $query=$pdo->prepare('SELECT COUNT(*) FROM visitas v'.$clause); $query->execute($params);
  $total=(int)$query->fetchColumn();
  $pagination=tabla_pagina($_GET,$total,50);$limit=$pagination['limit'];$offset=$pagination['offset'];
  $query=$pdo->prepare('SELECT v.id,v.nombre,v.rut,v.telefono,v.modo,v.patente,v.puesto,v.motivo,v.fecha_hora_entrada,v.fecha_hora_salida,v.estado,v.operador_id,v.operador_salida_id,ue.username AS operador_entrada,us.username AS operador_salida FROM visitas v LEFT JOIN usuarios ue ON ue.id=v.operador_id LEFT JOIN usuarios us ON us.id=v.operador_salida_id'.$clause.' ORDER BY v.fecha_hora_entrada DESC,v.id DESC LIMIT '.$limit.' OFFSET '.$offset);
  $query->execute($params);
  $inside=(int)$pdo->query("SELECT COUNT(*) FROM visitas WHERE estado='DENTRO'")->fetchColumn();
  $items=$query->fetchAll();$pdo->commit();
  echo json_encode(['ok'=>true,'items'=>$items,'dentro'=>$inside,'checked_at'=>date('Y-m-d H:i:s')]+$pagination,JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
  if($pdo->inTransaction())$pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  error_log('Listado de visitas: '.$error->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'No se pudieron consultar las visitas.'],JSON_UNESCAPED_UNICODE);
}