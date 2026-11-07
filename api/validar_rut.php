<?php
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/rut.php';
require_api_roles(['ADMIN','GUARDIA'],['POST']);
try {
  $data=stripos($_SERVER['CONTENT_TYPE'] ?? '','application/json')!==false ? json_decode(file_get_contents('php://input'),true) : $_POST;
  $input=is_array($data)?($data['rut'] ?? ''):'';
  $rut=is_string($input) && preg_match('//u',$input)===1?rut_normalizar($input):'';
  if(!rut_es_valido($rut)) {
    echo json_encode(['ok'=>false,'error'=>'RUT inválido (formato o dígito verificador).','rut'=>$rut],JSON_UNESCAPED_UNICODE);exit;
  }
  // La ficha y el último aprobado se consultan en una sola sentencia.
  $query=db()->prepare("SELECT f.rut,f.nombres,f.unidad,f.grado,f.estado,
      r.id AS ultimo_id,r.tipo AS ultimo_tipo,r.fecha_hora AS ultimo_fecha,r.puesto AS ultimo_puesto,
      r.modo AS ultimo_modo,u.username AS ultimo_operador
    FROM funcionarios f
    LEFT JOIN registros_acceso r ON r.id=(SELECT a.id FROM registros_acceso a
      WHERE a.rut=f.rut AND a.resultado='APROBADO' ORDER BY a.fecha_hora DESC,a.id DESC LIMIT 1)
    LEFT JOIN usuarios u ON u.id=r.operador_id
    WHERE f.rut=?");
  $query->execute([$rut]);$row=$query->fetch();
  $last=$row && $row['ultimo_id']!==null?[
    'id'=>(int)$row['ultimo_id'],'tipo'=>$row['ultimo_tipo'],'fecha_hora'=>$row['ultimo_fecha'],
    'puesto'=>$row['ultimo_puesto'],'modo'=>$row['ultimo_modo'],'operador'=>$row['ultimo_operador']
  ]:null;
  echo json_encode(['ok'=>true,'rut'=>$rut,'existe'=>(bool)$row,'activo'=>$row && $row['estado']==='ACTIVO',
    'funcionario'=>$row?array_intersect_key($row,array_flip(['rut','nombres','unidad','grado','estado'])):null,
    'presencia'=>$last?($last['tipo']==='ENTRADA'?'DENTRO':'FUERA'):'SIN_MOVIMIENTOS',
    'ultimo_movimiento'=>$last,'checked_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE);
} catch(Throwable $error) {
  error_log('Consulta de RUT: '.$error->getMessage());
  http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudo consultar al funcionario. Inténtalo nuevamente.'],JSON_UNESCAPED_UNICODE);
}