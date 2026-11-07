<?php
require_once __DIR__.'/../app/auth.php';
require_api_roles(['ADMIN','CONTROL_VISITA']);
try {
  $pdo = db();
  $view = $_GET['vista'] ?? 'historial';
  if (!is_string($view) || !in_array($view,['dentro','historial'],true)) throw new InvalidArgumentException('Selecciona una vista válida.');
  $rut = $_GET['rut'] ?? '';
  if (!is_string($rut) || strlen($rut)>30) throw new InvalidArgumentException('Filtro de RUT inválido.');
  $where = []; $params = [];
  if ($rut !== '') { $where[]='v.rut LIKE ?'; $params[]='%'.trim($rut).'%'; }
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
  $query=$pdo->prepare('SELECT COUNT(*) FROM visitas v'.$clause); $query->execute($params);
  $total=(int)$query->fetchColumn();
  $limit=filter_var($_GET['limit'] ?? 50,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>200]]);
  $page=filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if ($limit===false || $page===false) throw new InvalidArgumentException('Paginación inválida.');
  $pages=max(1,(int)ceil($total/$limit)); $page=min($page,$pages); $offset=($page-1)*$limit;
  $query=$pdo->prepare('SELECT v.id,v.nombre,v.rut,v.telefono,v.modo,v.patente,v.puesto,v.motivo,v.fecha_hora_entrada,v.fecha_hora_salida,v.estado,v.operador_id,v.operador_salida_id,ue.username AS operador_entrada,us.username AS operador_salida FROM visitas v LEFT JOIN usuarios ue ON ue.id=v.operador_id LEFT JOIN usuarios us ON us.id=v.operador_salida_id'.$clause.' ORDER BY v.fecha_hora_entrada DESC,v.id DESC LIMIT '.$limit.' OFFSET '.$offset);
  $query->execute($params);
  $inside=(int)$pdo->query("SELECT COUNT(*) FROM visitas WHERE estado='DENTRO'")->fetchColumn();
  echo json_encode(['ok'=>true,'items'=>$query->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'dentro'=>$inside,'checked_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log('Listado de visitas: '.$error->getMessage());
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'No se pudieron consultar las visitas.'],JSON_UNESCAPED_UNICODE);
}