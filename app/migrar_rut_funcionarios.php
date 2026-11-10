<?php
require_once __DIR__.'/rut_funcionario.php';
require_once __DIR__.'/concurrencia.php';

// Conserva las relaciones sin desactivar claves foráneas. Toda la conversión es atómica.
function migrar_rut_funcionarios(PDO $pdo): array {
  return ejecutar_por_rut($pdo,'administracion','funcionarios',function()use($pdo):array{
    $rows=$pdo->query('SELECT rut,nombres,unidad,grado,estado,fecha_actualizacion,imagen FROM funcionarios ORDER BY rut FOR UPDATE')->fetchAll();
    $targets=[];$changes=[];
    foreach($rows as $row){
      $target=rut_funcionario_normalizar($row['rut']);
      if($target==='')throw new RuntimeException('Hay un RUT de funcionario inválido. La conversión se canceló sin modificar datos.');
      if(isset($targets[$target]))throw new RuntimeException('Dos fichas comparten el mismo número de RUT. Revisa el padrón antes de convertirlo.');
      $targets[$target]=true;
      if($target!==$row['rut'])$changes[]=[$row,$target];
    }
    $insert=$pdo->prepare('INSERT INTO funcionarios (rut,nombres,unidad,grado,estado,fecha_actualizacion,imagen) VALUES (?,?,?,?,?,?,?)');
    $events=$pdo->prepare('UPDATE registros_acceso SET rut=? WHERE rut=?');
    $remove=$pdo->prepare('DELETE FROM funcionarios WHERE rut=?');
    $count=0;
    foreach($changes as [$row,$target]){
      $insert->execute([$target,$row['nombres'],$row['unidad'],$row['grado'],$row['estado'],$row['fecha_actualizacion'],$row['imagen']]);
      $events->execute([$target,$row['rut']]);$count+=$events->rowCount();
      $remove->execute([$row['rut']]);
    }
    return ['funcionarios'=>count($changes),'movimientos'=>$count];
  });
}
