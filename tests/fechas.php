<?php
date_default_timezone_set('Europe/Berlin');
require_once __DIR__.'/support/accesos_fixture.php';

qa_run(function (array &$qa): void {
  qa_check(date_default_timezone_get() === 'America/Santiago','PHP conserva el huso horario del servidor.');
  qa_check(hora_chile(new DateTimeImmutable('2026-11-08T02:30:00Z'))->format('Y-m-d H:i') === '2026-11-07 23:30','La noche chilena se convirtió en el día UTC siguiente.');
  qa_check(desfase_horario_chile(new DateTimeImmutable('2026-01-15T12:00:00Z')) === '-03:00','Desfase de verano incorrecto.');
  qa_check(desfase_horario_chile(new DateTimeImmutable('2026-07-15T12:00:00Z')) === '-04:00','Desfase de invierno incorrecto.');
  foreach (['2026-04-05','2026-09-06','2024-02-29'] as $day) {
    [$start,$end] = limites_dia_chile($day);
    qa_check($start === $day.' 00:00:00' && substr($end,11) === '00:00:00','Un cambio de horario alteró los límites civiles.');
  }
  foreach (['2026-02-30','2025-02-29','2026-13-01','2026-01-01 extra','0999-01-01','9999-12-31',[],null] as $bad) {
    try { limites_dia_chile($bad); throw new RuntimeException('Se aceptó un día inválido.'); }
    catch (InvalidArgumentException $expected) {}
  }
  try { limites_rango_chile('2026-02-02','2026-02-01'); throw new RuntimeException('Se aceptó un rango invertido.'); }
  catch (InvalidArgumentException $expected) {}
  $clock = $qa['pdo']->query('SELECT @@session.time_zone AS zone, NOW() AS local_now, UTC_TIMESTAMP() AS utc_now')->fetch();
  qa_check($clock['zone'] === desfase_horario_chile(),'La conexión MariaDB no usa el desfase chileno.');
  $utc = new DateTimeImmutable($clock['utc_now'],new DateTimeZone('UTC'));
  qa_check(hora_chile($utc)->format('Y-m-d H:i:s') === $clock['local_now'],'PHP y SQL no coinciden en la hora local.');
  echo "OK: PHP y MariaDB en horario chileno; verano, invierno y medianoche UTC\n";

  $day = '2001-04-02';
  [$start,$end] = limites_dia_chile($day);
  $check = $qa['pdo']->prepare('SELECT COUNT(*) FROM registros_acceso WHERE fecha_hora>=? AND fecha_hora<?');
  $check->execute([$start,$end]);
  qa_check((int)$check->fetchColumn() === 0,'El día reservado para probar reportes ya tiene movimientos.');
  $rut = qa_staff($qa);
  qa_event($qa,$rut,'ENTRADA','2001-04-01 23:59:59');
  $id1 = qa_event($qa,$rut,'ENTRADA',$start);
  $id2 = qa_event($qa,$rut,'ENTRADA','2001-04-02 23:59:59','RECHAZADO');
  qa_event($qa,$rut,'ENTRADA',$end);
  $range = 'desde='.$day.'&hasta='.$day;
  $kpi = qa_json($qa,'api/reportes_kpi.php?'.$range)['kpi'];
  qa_check($kpi['total'] === 2 && $kpi['aprobados'] === 1 && $kpi['rechazados'] === 1,'Los KPI no respetan los límites del día.');
  $daily = qa_json($qa,'api/reportes_diario.php?'.$range)['items'];
  qa_check(count($daily) === 1 && $daily[0]['fecha'] === $day && (int)$daily[0]['total'] === 2,'El reporte diario incluye otro día.');
  $hourly = qa_json($qa,'api/reportes_horas.php?fecha='.$day)['items'];
  qa_check(count($hourly) === 1 && (int)$hourly[0]['hora'] === 0 && (int)$hourly[0]['entradas'] === 1,'El reporte por hora incluye rechazos u otro día.');
  $latest = qa_json($qa,'api/ultimos.php?rut='.$rut.'&'.$range)['items'];
  qa_check(array_map('intval',array_column($latest,'id')) === [$id2,$id1],'El historial individual no respeta los límites del día.');
  echo "OK: reportes e historial incluyen 00:00:00 y 23:59:59 y excluyen el día siguiente\n";

  $invalid = [
    'api/registros_resumen.php?fecha=2026-02-30',
    'api/visitas_listar.php?fecha=2026-02-30',
    'api/ultimos.php?desde=2026-02-30',
    'api/ultimos.php?desde=2026-02-02&hasta=2026-02-01',
    'api/reportes_horas.php?fecha=2026-02-30',
    'api/reportes_kpi.php?desde=2026-02-02&hasta=2026-02-01',
    'api/reportes_diario.php?desde=2026-02-30&hasta=2026-03-01',
    'api/registros_resumen.php?fecha%5B%5D=2026-01-01',
    'api/visitas_listar.php?fecha%5B%5D=2026-01-01',
    'api/reportes_horas.php?fecha%5B%5D=2026-01-01',
    'api/reportes_diario.php?desde%5B%5D=2026-01-01&hasta=2026-01-01',
  ];
  foreach ($invalid as $url) qa_check(qa_json($qa,$url,400)['ok'] === false,'Un filtro inválido no fue rechazado.');
  foreach (['index.php','registros.php','visitas.php','reportes.php','configuracion/exportaciones.php','configuracion/sistema.php'] as $page) {
    $response = qa_http($qa['sessions']['ADMIN'],$page);
    qa_check($response['status'] === 200,'Pantalla inaccesible: '.$page);
    $datesPos = strpos($response['body'],'assets/js/fechas.js');
    $uiPos = strpos($response['body'],'assets/js/ui.js');
    qa_check($datesPos !== false && $uiPos !== false && $datesPos < $uiPos,'Carga incorrecta del calendario común: '.$page);
    qa_check(!str_contains($response['body'],'new Date().toISOString().slice(0,10)'),'Una pantalla usa el día UTC: '.$page);
  }
  $inside = qa_json($qa,'api/personal_dentro.php');
  qa_check($inside['fecha'] === hora_chile()->format('Y-m-d'),'La consulta de presencia muestra otro día.');
  $status = qa_json($qa,'api/admin_db_status.php');
  qa_check(abs(strtotime($status['checked_at'])-time()) < 5,'Diagnóstico muestra la hora del servidor en otro huso.');
  echo "OK: filtros inválidos devuelven 400; pantallas y diagnóstico usan el calendario común\n";
});