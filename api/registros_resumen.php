<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__.'/../app/tablas.php';

require_api_roles(['ADMIN','GUARDIA','AUDITOR']);

try {
  $rut = campo_texto($_GET['rut'] ?? '', 'RUT',30);
  $search = campo_texto($_GET['q'] ?? '', 'búsqueda',120);
  $puesto = campo_texto($_GET['puesto'] ?? '', 'puesto',60);
  $fecha = $_GET['fecha'] ?? '';
  $resultado = campo_texto($_GET['resultado'] ?? '', 'resultado',10);
  if(!in_array($resultado,['','APROBADO','RECHAZADO'],true))throw new InvalidArgumentException('Resultado inválido.');
  $where = [];
  $params = [];
  if ($rut !== '') {
    $where[] = "e.rut LIKE ? ESCAPE '!'";
    $params[] = tabla_like(rut_funcionario_busqueda($rut));
  }
  if($search!==''){
    $where[]="(f.nombres LIKE ? ESCAPE '!' OR e.rut LIKE ? ESCAPE '!')";
    $params[]=tabla_like($search);$params[]=tabla_like(rut_funcionario_busqueda($search));
  }
  if($puesto!==''){
    $where[]='(e.puesto=? OR s.puesto=?)';$params[]=$puesto;$params[]=$puesto;
  }
  $desde=$_GET['desde'] ?? ''; $hasta=$_GET['hasta'] ?? '';
  if($desde!=='') { [$start]=limites_dia_chile($desde); }
  if($hasta!=='') { [, $end]=limites_dia_chile($hasta); }
  if($desde!=='' && $hasta!=='')limites_rango_chile($desde,$hasta);
  if($desde!=='' || $hasta!==''){
    $entryBounds=[];$exitBounds=[];$bounds=[];
    if($desde!==''){$entryBounds[]='e.fecha_hora>=?';$exitBounds[]='s.fecha_hora>=?';$bounds[]=$start;}
    if($hasta!==''){$entryBounds[]='e.fecha_hora<?';$exitBounds[]='s.fecha_hora<?';$bounds[]=$end;}
    $where[]='(('.implode(' AND ',$entryBounds).') OR ('.implode(' AND ',$exitBounds).'))';
    $params=array_merge($params,$bounds,$bounds);
  }
  if ($fecha !== '') {
    [$start, $end] = limites_dia_chile($fecha);
    $where[] = '((e.fecha_hora >= ? AND e.fecha_hora < ?) OR (s.fecha_hora >= ? AND s.fecha_hora < ?))';
    $params = array_merge($params, [$start, $end, $start, $end]);
  }
  if (in_array($resultado, ['APROBADO','RECHAZADO'], true)) {
    $where[] = 'e.resultado = ?';
    $params[] = $resultado;
  }

  // Solo dos movimientos aprobados consecutivos ENTRADA -> SALIDA forman una pareja.
  // Los rechazos y las salidas antiguas sin entrada permanecen como filas individuales.
  $cte = "
    WITH aprobados AS (
      SELECT id, tipo,
        LAG(tipo) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS anterior_tipo,
        LEAD(tipo) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS siguiente_tipo,
        LEAD(id) OVER (PARTITION BY rut ORDER BY fecha_hora, id) AS siguiente_id
      FROM registros_acceso WHERE resultado = 'APROBADO'
    ), filas AS (
      SELECT id, CASE WHEN tipo = 'ENTRADA' AND siguiente_tipo = 'SALIDA' THEN siguiente_id END AS salida_id,
        CASE WHEN siguiente_tipo = 'ENTRADA' AND tipo = 'ENTRADA' THEN 'Entrada seguida de otra entrada aprobada'
             WHEN tipo = 'SALIDA' THEN 'Salida sin entrada consecutiva registrada' END AS incidencia
      FROM aprobados
      WHERE tipo = 'ENTRADA' OR (tipo = 'SALIDA' AND COALESCE(anterior_tipo, '') <> 'ENTRADA')
      UNION ALL
      SELECT id, NULL, NULL FROM registros_acceso WHERE resultado = 'RECHAZADO'
    )
  ";
  $columns="e.id, e.tipo AS tipo_movimiento, e.fecha_hora AS fecha_movimiento,
      e.rut, f.nombres,
      CASE WHEN e.tipo = 'ENTRADA' THEN e.fecha_hora END AS entrada,
      CASE WHEN e.tipo = 'SALIDA' THEN e.fecha_hora ELSE s.fecha_hora END AS salida,
      e.modo, e.puesto, s.puesto AS puesto_salida, e.resultado, e.motivo, filas.incidencia,
      CASE WHEN e.tipo = 'ENTRADA' THEN e.operador_id END AS operador_entrada_id,
      CASE WHEN e.tipo = 'SALIDA' THEN e.operador_id ELSE s.operador_id END AS operador_salida_id,
      CASE WHEN e.tipo = 'ENTRADA' THEN ue.username END AS operador_entrada,
      CASE WHEN e.tipo = 'SALIDA' THEN ue.username ELSE us.username END AS operador_salida";
  $from=" FROM filas
    INNER JOIN registros_acceso e ON e.id = filas.id
    INNER JOIN funcionarios f ON f.rut=e.rut
    LEFT JOIN registros_acceso s ON s.id = filas.salida_id
    LEFT JOIN usuarios ue ON ue.id = e.operador_id
    LEFT JOIN usuarios us ON us.id = s.operador_id
  ";
  if($where)$from.=' WHERE '.implode(' AND ',$where);
  $pdo=db();$pdo->beginTransaction();
  $count=$pdo->prepare($cte.' SELECT COUNT(*)'.$from);$count->execute($params);
  $pagination=tabla_pagina($_GET,(int)$count->fetchColumn(),50);
  $st=$pdo->prepare($cte.' SELECT '.$columns.$from.' ORDER BY e.fecha_hora DESC,e.id DESC LIMIT '.$pagination['limit'].' OFFSET '.$pagination['offset']);
  $st->execute($params);$items=$st->fetchAll();$puestos=tabla_puestos($pdo);$pdo->commit();
  echo json_encode(['ok'=>true,'items'=>$items,'puestos'=>$puestos]+$pagination,JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
  http_response_code(500);
  error_log('Historial de accesos: ' . $error->getMessage());
  echo json_encode(['ok'=>false, 'error'=>'No se pudo consultar el historial.'], JSON_UNESCAPED_UNICODE);
}