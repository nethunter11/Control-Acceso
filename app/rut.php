<?php
// app/rut.php

function rut_normalizar(string $rut): string {
  $rut = strtoupper(trim($rut));
  $rut = str_replace(['.', ' '], '', $rut);

  // Si viene sin guion, se lo ponemos antes del DV
  if (strpos($rut, '-') === false && strlen($rut) >= 2) {
    $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
  }

  return $rut;
}

function rut_es_valido(string $rut): bool {
  $rut = rut_normalizar($rut);

  if (!preg_match('/^\d{1,8}-[\dK]$/', $rut)) return false;

  [$num, $dv] = explode('-', $rut);
  $dv = strtoupper($dv);

  $suma = 0;
  $multiplo = 2;

  for ($i = strlen($num) - 1; $i >= 0; $i--) {
    $suma += intval($num[$i]) * $multiplo;
    $multiplo = ($multiplo == 7) ? 2 : $multiplo + 1;
  }

  $resto = $suma % 11;
  $calc = 11 - $resto;

  $dvCalc = '';
  if ($calc == 11) $dvCalc = '0';
  else if ($calc == 10) $dvCalc = 'K';
  else $dvCalc = strval($calc);

  return $dvCalc === $dv;
}
