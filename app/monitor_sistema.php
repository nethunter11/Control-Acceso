<?php
const MONITOR_INTERVAL_SECONDS = 10;
const MONITOR_HISTORY_SECONDS = 21600;
const MONITOR_MAX_SAMPLES = 2161;

function monitor_number(mixed $value, float $min, float $max): ?float {
  if (!is_int($value) && !is_float($value)) return null;
  $value = (float)$value;
  return is_finite($value) && $value >= $min && $value <= $max ? $value : null;
}

function monitor_normalize(array $raw): array {
  $data = [
    'cpu_percent'=>monitor_number($raw['cpu_percent'] ?? null,0,100),
    'ram_total_bytes'=>monitor_number($raw['ram_total_bytes'] ?? null,1,1e16),
    'ram_free_bytes'=>monitor_number($raw['ram_free_bytes'] ?? null,0,1e16),
    'disk_total_bytes'=>monitor_number($raw['disk_total_bytes'] ?? null,1,1e18),
    'disk_free_bytes'=>monitor_number($raw['disk_free_bytes'] ?? null,0,1e18),
    'temperature_c'=>monitor_number($raw['temperature_c'] ?? null,0.1,149.9),
    'uptime_seconds'=>monitor_number($raw['uptime_seconds'] ?? null,0,1e12),
  ];
  foreach (['ram','disk'] as $type) {
    $total = $data[$type.'_total_bytes'];
    $free = $data[$type.'_free_bytes'];
    if ($total === null || $free === null || $free > $total) {
      $data[$type.'_total_bytes'] = $data[$type.'_free_bytes'] = null;
      $data[$type.'_used_bytes'] = $data[$type.'_percent'] = null;
    } else {
      $data[$type.'_used_bytes'] = $total - $free;
      $data[$type.'_percent'] = round(100 * ($total-$free)/$total,1);
    }
  }
  $data['temperature_source'] = $data['temperature_c'] !== null ? 'Zona térmica ACPI (máxima)' : null;
  $data['temperature_note'] = $data['temperature_c'] === null ? 'Windows no expone un sensor térmico compatible.' : 'Lectura de zona térmica ACPI; no identifica la temperatura interna de la CPU.';
  return $data;
}

function monitor_read_windows(): array {
  if (PHP_OS_FAMILY !== 'Windows' || !function_exists('proc_open')) return [];
  $executable = (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
  $script = @file_get_contents(__DIR__.'/../tools/medir-sistema.ps1');
  if (!is_file($executable) || $script === false) return [];
  $environment = getenv();
  $environment['CONTROL_ACCESO_MONITOR_ROOT'] = dirname(__DIR__);
  $process = @proc_open([$executable,'-NoProfile','-NonInteractive','-WindowStyle','Hidden','-Command',$script],
    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment,['bypass_shell'=>true]);
  if (!is_resource($process)) return [];
  fclose($pipes[0]);
  stream_set_blocking($pipes[1],false);
  stream_set_blocking($pipes[2],false);
  $output = '';
  $deadline = microtime(true)+8;
  $timedOut = false;
  try {
    do {
      $output .= stream_get_contents($pipes[1]);
      stream_get_contents($pipes[2]); // No se publican mensajes de WMI ni rutas internas.
      $status = proc_get_status($process);
      if (!$status['running']) break;
      if (microtime(true)>$deadline || strlen($output)>65536) {
        $timedOut = true;
        proc_terminate($process);
        break;
      }
      usleep(20000);
    } while (true);
    $output .= stream_get_contents($pipes[1]);
  } finally {
    fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
  }
  if ($timedOut) return [];
  $decoded = json_decode(trim($output,"\xEF\xBB\xBF \r\n\t"),true);
  return is_array($decoded) ? $decoded : [];
}

function monitor_directory(): string {
  $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
  return rtrim(sys_get_temp_dir(),'/\\') . DIRECTORY_SEPARATOR . 'control-acceso-monitor-' . substr(hash('sha256',$root),0,16);
}

function monitor_history(array $rows, array $sample): array {
  $cutoff = $sample['timestamp'] - MONITOR_HISTORY_SECONDS;
  $rows = array_values(array_filter($rows,fn($row)=>is_array($row) && isset($row['timestamp']) && is_int($row['timestamp']) && $row['timestamp'] >= $cutoff && $row['timestamp'] < $sample['timestamp']));
  $rows[] = $sample;
  return array_slice($rows,-MONITOR_MAX_SAMPLES);
}

function monitor_snapshot(string $directory, callable $collect, ?int $now = null): array {
  $now ??= time();
  if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo preparar el historial de diagnóstico.');
  }
  $statePath = $directory . DIRECTORY_SEPARATOR . 'state.json';
  $readState = static function () use ($statePath): ?array {
    if (!is_file($statePath)) return null;
    $size = @filesize($statePath);
    if ($size === false || $size>2000000) return null;
    $raw = @file_get_contents($statePath);
    $data = $raw !== false ? json_decode($raw,true) : null;
    return is_array($data) && ($data['schema'] ?? null) === 1 && is_int($data['sample']['timestamp'] ?? null) && is_array($data['sample']['metrics'] ?? null) && is_array($data['sample']['database'] ?? null) && is_array($data['history'] ?? null) ? $data : null;
  };
  $state = $readState();
  if ($state && $now >= $state['sample']['timestamp'] && $now-$state['sample']['timestamp'] < MONITOR_INTERVAL_SECONDS) {
    $state['cached'] = true;
    return $state;
  }
  $lock = @fopen($directory . DIRECTORY_SEPARATOR . 'collector.lock','c');
  if ($lock === false) throw new RuntimeException('No se pudo preparar la lectura de diagnóstico.');
  try {
    if (!flock($lock,LOCK_EX|LOCK_NB)) {
      if ($state) { $state['cached']=true; return $state; }
      throw new RuntimeException('Se está preparando la primera lectura. Inténtalo nuevamente.');
    }
    // Otro proceso puede haber actualizado el archivo antes de obtener el turno.
    clearstatcache(true,$statePath);
    $latest = $readState();
    if ($latest) $state = $latest;
    if ($state && $now >= $state['sample']['timestamp'] && $now-$state['sample']['timestamp'] < MONITOR_INTERVAL_SECONDS) {
      $state['cached']=true;
      return $state;
    }
    $collected = $collect();
    $sample = ['timestamp'=>$now,'metrics'=>monitor_normalize($collected['metrics'] ?? []),'database'=>$collected['database'] ?? ['ok'=>false,'latency_ms'=>null,'version'=>null]];
    $state = ['schema'=>1,'sample'=>$sample,'history'=>monitor_history($state['history'] ?? [],[
      'timestamp'=>$now,'cpu_percent'=>$sample['metrics']['cpu_percent'],'ram_percent'=>$sample['metrics']['ram_percent'],
      'disk_percent'=>$sample['metrics']['disk_percent'],'temperature_c'=>$sample['metrics']['temperature_c'],
      'db_latency_ms'=>$sample['database']['ok'] ? $sample['database']['latency_ms'] : null,
    ])];
    $temp = @tempnam($directory,'reading-');
    if ($temp === false) throw new RuntimeException('No se pudo guardar el historial.');
    try {
      $written = @file_put_contents($temp,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX);
      if ($written === false || !@rename($temp,$statePath)) throw new RuntimeException('No se pudo actualizar el historial.');
    } finally { if (is_file($temp)) @unlink($temp); }
    $state['cached']=false;
    return $state;
  } finally { flock($lock,LOCK_UN); fclose($lock); }
}
