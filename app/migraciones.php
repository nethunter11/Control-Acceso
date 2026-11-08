<?php
function aplicar_migraciones(PDO $pdo,string $directory): array {
  $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  if($database==='')throw new RuntimeException('Selecciona una base de datos antes de migrar.');
  $lockName='ca:migraciones:'.substr(hash('sha256',$database),0,45);
  $lock=$pdo->prepare('SELECT GET_LOCK(?,10)');$lock->execute([$lockName]);
  if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Hay otra actualización de base de datos en curso.');
  try{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL)');
    $files=glob($directory.'/*.sql');sort($files,SORT_STRING);$applied=[];
    foreach($files as $file){
      $version=basename($file);
      if(!preg_match('/^\d{8}_\d{2}_[a-z0-9_]+\.sql$/D',$version))throw new RuntimeException('Nombre de migración inválido.');
      $lookup=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');$lookup->execute([$version]);
      if($lookup->fetchColumn())continue;
      $sql=file_get_contents($file);if($sql===false)throw new RuntimeException('No se pudo leer la migración.');
      // ALTER TABLE confirma su propia transacción; los scripts son aditivos e idempotentes.
      $pdo->exec($sql);
      $pdo->prepare('INSERT INTO schema_migrations (version,applied_at) VALUES (?,NOW())')->execute([$version]);
      $applied[]=$version;
    }
    return $applied;
  }finally{
    $release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lockName]);
  }
}