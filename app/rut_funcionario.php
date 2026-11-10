<?php
require_once __DIR__.'/rut.php';

// Un número sin guion siempre es el cuerpo del RUT; nunca se elimina su último dígito.
function rut_funcionario_normalizar(mixed $input): string {
  if(!is_string($input) || strlen($input)>20 || preg_match('//u',$input)!==1)return '';
  $plain=strtoupper(str_replace(['.',' '],'',trim($input)));
  // Compatibilidad con padrones CSV anteriores: validar el DV explícito antes de quitarlo.
  if(str_contains($plain,'-')) {
    if(!rut_es_valido($plain))return '';
    $plain=explode('-',$plain,2)[0];
  }
  return rut_funcionario_es_valido($plain)?$plain:'';
}

function rut_funcionario_es_valido(string $rut): bool {
  return preg_match('/^[1-9][0-9]{0,7}$/D',$rut)===1;
}

function rut_funcionario_busqueda(string $text): string {
  $normalized=rut_funcionario_normalizar($text);
  // Las búsquedas parciales y los comodines literales conservan su significado.
  return $normalized!==''?$normalized:str_replace(['.',' '],'',trim($text));
}
