<?php
require_once __DIR__.'/entrada.php';
require_once __DIR__.'/rut_funcionario.php';
require_once __DIR__.'/concurrencia.php';
require_once __DIR__.'/imagenes_funcionarios.php';

final class FuncionarioConflicto extends RuntimeException {}
function funcionario_validar(array $data): array {
  $rut=rut_funcionario_normalizar(campo_texto($data['rut'] ?? '','RUT',20,true));
  if(!rut_funcionario_es_valido($rut)) throw new InvalidArgumentException('RUT inválido: ingresa el número sin dígito verificador (1 a 8 dígitos).');
  $state=campo_texto($data['estado'] ?? 'ACTIVO','estado',8,true);
  if(!in_array($state,['ACTIVO','INACTIVO'],true)) throw new InvalidArgumentException('El estado debe ser ACTIVO o INACTIVO.');
  $staff=['rut'=>$rut,'nombres'=>campo_texto($data['nombres'] ?? '','nombre',120,true),
    'unidad'=>campo_texto($data['unidad'] ?? '','unidad',120),
    'grado'=>campo_texto($data['grado'] ?? '','grado',80),'estado'=>$state];
  // Los CSV y clientes anteriores conservan la imagen si no envían este campo.
  if(array_key_exists('imagen',$data))$staff['imagen']=funcionario_imagen_validar($data['imagen']);
  return $staff;
}
function funcionario_revision(array $row): string {
  return hash('sha256',json_encode([$row['rut'],$row['nombres'],$row['unidad'],$row['grado'],$row['estado'],$row['fecha_actualizacion'],$row['imagen'] ?? null],JSON_THROW_ON_ERROR));
}
function funcionario_buscar(PDO $pdo,string $rut,bool $lock=false): ?array {
  $query=$pdo->prepare('SELECT rut,nombres,unidad,grado,estado,fecha_actualizacion,imagen FROM funcionarios WHERE rut=?'.($lock?' FOR UPDATE':''));
  $query->execute([$rut]); return $query->fetch() ?: null;
}
function funcionario_escribir(PDO $pdo,array $data,?string $revision): void {
  if(!rut_funcionario_es_valido($data['rut'])) throw new FuncionarioConflicto('El formato del RUT cambió. Vuelve a generar la vista previa o a abrir la ficha.');
  $current=funcionario_buscar($pdo,$data['rut'],true);
  if($revision===null && $current) throw new FuncionarioConflicto('El RUT ya está registrado. Busca y edita al funcionario.');
  if($revision!==null && (!$current || !hash_equals(funcionario_revision($current),$revision))) throw new FuncionarioConflicto('El funcionario cambió desde la consulta. Actualiza la información antes de guardar.');
  $image=array_key_exists('imagen',$data)?$data['imagen']:($current['imagen'] ?? null);
  $values=[$data['nombres'],$data['unidad'] ?: null,$data['grado'] ?: null,$data['estado'],$image,$data['rut']];
  if($current) $pdo->prepare('UPDATE funcionarios SET nombres=?,unidad=?,grado=?,estado=?,imagen=? WHERE rut=?')->execute($values);
  else $pdo->prepare('INSERT INTO funcionarios (nombres,unidad,grado,estado,imagen,rut) VALUES (?,?,?,?,?,?)')->execute($values);
}
function funcionarios_preview(PDO $pdo,mixed $csv,mixed $mode): array {
  if(!is_string($csv) || strlen($csv)>1048576 || !preg_match('//u',$csv) || str_contains($csv,"\0")) throw new InvalidArgumentException('Usa un CSV UTF-8 de hasta 1 MB.');
  if(!in_array($mode,['crear','actualizar'],true)) throw new InvalidArgumentException('Selecciona un modo de importación válido.');
  $csv=preg_replace('/^\\xEF\\xBB\\xBF/','',$csv);
  $first=strtok($csv,"\r\n");
  if($first===false) throw new InvalidArgumentException('El CSV está vacío.');
  $separator=count(str_getcsv($first,';','"','')) > count(str_getcsv($first,',','"','')) ? ';' : ',';
  $stream=fopen('php://temp','w+'); fwrite($stream,$csv); rewind($stream);
  try {
    $header=fgetcsv($stream,0,$separator,'"','');
    if(!$header) throw new InvalidArgumentException('Faltan los encabezados del CSV.');
    $header=array_map(fn($name)=>mb_strtolower(trim((string)$name),'UTF-8'),$header);
    $required=['rut','nombres','unidad','grado','estado'];
    if(count(array_unique($header))!==count($header) || array_diff($required,$header) || array_diff($header,array_merge($required,['fecha_actualizacion']))) throw new InvalidArgumentException('Encabezados requeridos: rut,nombres,unidad,grado,estado.');
    $rows=[]; $seen=[]; $line=1; $create=0; $update=0; $errors=0;
    while(($cells=fgetcsv($stream,0,$separator,'"',''))!==false) {
      $line++;
      if($cells===[null]) continue;
      if(count($rows)>=1000) throw new InvalidArgumentException('El archivo admite un máximo de 1000 filas.');
      $row=['fila'=>$line,'data'=>null,'revision'=>null,'accion'=>'','cambios'=>[],'error'=>null];
      try {
        if(count($cells)!==count($header)) throw new InvalidArgumentException('La cantidad de columnas no coincide con el encabezado.');
        $data=funcionario_validar(array_combine($header,$cells));
        $row['data']=$data;
        if(isset($seen[$data['rut']])) throw new InvalidArgumentException('RUT repetido en el archivo (fila '.$seen[$data['rut']].').');
        $seen[$data['rut']]=$line;
        $current=funcionario_buscar($pdo,$data['rut']);
        if($current && $mode==='crear') throw new InvalidArgumentException('El RUT ya existe. Elige crear y actualizar si deseas modificarlo.');
        $row['revision']=$current ? funcionario_revision($current) : null;
        $row['accion']=$current ? 'Actualizar' : 'Crear';
        if($current) {
          foreach(['nombres','unidad','grado','estado'] as $field) {
            if((string)$current[$field]!==$data[$field]) $row['cambios'][]=['campo'=>$field,'anterior'=>$current[$field] ?? '','nuevo'=>$data[$field]];
          }
          $update++;
        } else $create++;
      } catch(InvalidArgumentException $error) { $row['error']=$error->getMessage(); $errors++; }
      $rows[]=$row;
    }
    if(!$rows) throw new InvalidArgumentException('El CSV no contiene funcionarios.');
    return ['rows'=>$rows,'crear'=>$create,'actualizar'=>$update,'errores'=>$errors,'mode'=>$mode];
  } finally { fclose($stream); }
}
