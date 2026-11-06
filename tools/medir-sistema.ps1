# Solo lectura. El proceso PHP ejecuta este contenido sin cambiar políticas de Windows.
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$result = [ordered]@{
  cpu_percent = $null
  ram_total_bytes = $null
  ram_free_bytes = $null
  disk_total_bytes = $null
  disk_free_bytes = $null
  temperature_c = $null
  temperature_source = $null
  uptime_seconds = $null
}
try {
  $os = Get-CimInstance Win32_OperatingSystem -OperationTimeoutSec 2
  if ($null -ne $os.TotalVisibleMemorySize -and $null -ne $os.FreePhysicalMemory) {
    $result.ram_total_bytes = [double]$os.TotalVisibleMemorySize * 1024
    $result.ram_free_bytes = [double]$os.FreePhysicalMemory * 1024
  }
  if ($null -ne $os.LastBootUpTime) {
    $result.uptime_seconds = [math]::Max(0,[math]::Floor(((Get-Date)-$os.LastBootUpTime).TotalSeconds))
  }
} catch {}
try {
  # PERF_100NSEC_TIMER_INV: dos muestras, sin depender del idioma de los contadores.
  $first = Get-CimInstance Win32_PerfRawData_PerfOS_Processor -Filter "Name='_Total'" -OperationTimeoutSec 2
  Start-Sleep -Milliseconds 400
  $second = Get-CimInstance Win32_PerfRawData_PerfOS_Processor -Filter "Name='_Total'" -OperationTimeoutSec 2
  if ($null -ne $first.PercentProcessorTime -and $null -ne $second.PercentProcessorTime) {
    $elapsed = [double]$second.Timestamp_Sys100NS - [double]$first.Timestamp_Sys100NS
    $idle = [double]$second.PercentProcessorTime - [double]$first.PercentProcessorTime
    if ($elapsed -gt 0 -and $idle -ge 0) {
      $result.cpu_percent = [math]::Round([math]::Min(100,[math]::Max(0,100*(1-$idle/$elapsed))),1)
    }
  }
} catch {}
try {
  # El volumen de este proyecto; no se enumeran archivos ni otros dispositivos.
  $driveRoot = [System.IO.Path]::GetPathRoot($env:CONTROL_ACCESO_MONITOR_ROOT)
  $drive = New-Object System.IO.DriveInfo($driveRoot)
  if ($drive.IsReady) {
    $result.disk_total_bytes = [double]$drive.TotalSize
    $result.disk_free_bytes = [double]$drive.TotalFreeSpace
  }
} catch {}
try {
  $zones = @(Get-CimInstance -Namespace root/wmi -ClassName MSAcpi_ThermalZoneTemperature -OperationTimeoutSec 2 | Where-Object { $_.Active -ne $false -and $null -ne $_.CurrentTemperature })
  $values = @($zones | ForEach-Object { ([double]$_.CurrentTemperature/10)-273.15 } | Where-Object { $_ -gt 0 -and $_ -lt 150 })
  if ($values.Count -gt 0) {
    $result.temperature_c = [math]::Round(($values | Measure-Object -Maximum).Maximum,1)
    $result.temperature_source = 'Zona termica ACPI (maxima)'
  }
} catch {}
$result | ConvertTo-Json -Compress
