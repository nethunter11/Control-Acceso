<?php
function login_limite(string $username, bool $failed = false, bool $reset = false): bool {
  $directory = sys_get_temp_dir().'/ca-login-'.substr(hash('sha256',__DIR__),0,16);
  if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('No se pudo verificar el acceso.');
  $key = hash('sha256',($_SERVER['REMOTE_ADDR'] ?? 'local')."\0".mb_strtolower($username,'UTF-8'));
  $file = fopen($directory.'/'.$key.'.json','c+');
  if (!$file || !flock($file,LOCK_EX)) throw new RuntimeException('No se pudo verificar el acceso.');
  try {
    $state = json_decode(stream_get_contents($file),true) ?? [];
    $times = array_values(array_filter($state,is_int(...)));
    $times = array_values(array_filter($times,fn($time)=>$time > time()-600));
    $blocked = count($times) >= 8;
    if ($reset) $times = [];
    elseif ($failed && !$blocked) $times[] = time();
    ftruncate($file,0); rewind($file); fwrite($file,json_encode($times)); fflush($file);
    return $blocked;
  } finally { flock($file,LOCK_UN); fclose($file); }
}