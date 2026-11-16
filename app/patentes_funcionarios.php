<?php
require_once __DIR__.'/concurrencia.php';

function patente_funcionario_validar(mixed $value): ?string {
  if($value===null || $value==='')return null;
  if(!is_string($value) || strlen($value)>20)throw new InvalidArgumentException('La patente debe tener cuatro letras y dos números, por ejemplo PTCL21.');
  $plate=strtoupper(str_replace([' ','-'],'',trim($value)));
  if($plate==='')return null;
  if(!preg_match('/^[A-Z]{4}[0-9]{2}$/D',$plate))throw new InvalidArgumentException('La patente debe tener cuatro letras y dos números, por ejemplo PTCL21.');
  return $plate;
}

function patente_funcionario_generar(): string {
  $letters='BCDFGHJKLPRSTVWXYZ';$plate='';
  for($i=0;$i<4;$i++)$plate.=$letters[random_int(0,strlen($letters)-1)];
  return $plate.(string)random_int(10,99);
}

function asignar_patentes_funcionarios(PDO $pdo): array {
  return ejecutar_por_rut($pdo,'administracion','funcionarios',function()use($pdo):array{
    $rows=$pdo->query('SELECT rut,patentes FROM funcionarios ORDER BY rut FOR UPDATE')->fetchAll();$used=[];$count=0;
    foreach($rows as $row)if($row['patentes']!==null){
      $plate=patente_funcionario_validar($row['patentes']);
      if($plate!==$row['patentes'] || isset($used[$plate]))throw new RuntimeException('Revisa las patentes existentes antes de asignar nuevas.');
      $used[$plate]=true;
    }
    $update=$pdo->prepare('UPDATE funcionarios SET patentes=?,fecha_actualizacion=fecha_actualizacion WHERE rut=?');
    foreach($rows as $row){
      if($row['patentes']!==null)continue;
      for($attempt=0;$attempt<200;$attempt++){
        $plate=patente_funcionario_generar();
        if(!isset($used[$plate]))break;
      }
      if(isset($used[$plate]))throw new RuntimeException('No se pudo reservar una patente distinta.');
      $update->execute([$plate,$row['rut']]);$used[$plate]=true;$count++;
    }
    return ['asignadas'=>$count,'total'=>count($rows)];
  });
}
