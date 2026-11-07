<?php

const APP_TIMEZONE = 'America/Santiago';
date_default_timezone_set(APP_TIMEZONE);

function hora_chile(?DateTimeInterface $instant = null): DateTimeImmutable {
  $zone = new DateTimeZone(APP_TIMEZONE);
  return $instant === null
    ? new DateTimeImmutable('now', $zone)
    : DateTimeImmutable::createFromInterface($instant)->setTimezone($zone);
}

function desfase_horario_chile(?DateTimeInterface $instant = null): string {
  return hora_chile($instant)->format('P');
}

// Los DATETIME existentes representan hora local de Chile. No se convierten ni reescriben.
// La aritmética de calendario en UTC evita que un cambio de horario salte la medianoche.
function limites_dia_chile(mixed $day): array {
  if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) {
    throw new InvalidArgumentException('Indica una fecha válida con formato año-mes-día.');
  }
  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
  if (!$date || $date->format('Y-m-d') !== $day || (int)$date->format('Y') < 1000) {
    throw new InvalidArgumentException('La fecha seleccionada no es válida.');
  }
  $end = $date->modify('+1 day')->format('Y-m-d').' 00:00:00';
  if (strlen($end) !== 19) throw new InvalidArgumentException('La fecha está fuera del rango admitido.');
  return [$day.' 00:00:00', $end];
}

function limites_rango_chile(mixed $from, mixed $to): array {
  [$start] = limites_dia_chile($from);
  [, $end] = limites_dia_chile($to);
  if ($from > $to) throw new InvalidArgumentException('La fecha inicial debe ser anterior o igual a la final.');
  return [$start, $end];
}