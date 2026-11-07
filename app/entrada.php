<?php
function campo_texto(mixed $value,string $label,int $max,bool $required = false): string {
  if (!is_string($value) || preg_match('//u',$value)!==1 || str_contains($value,"\0")) throw new InvalidArgumentException('Campo inválido: '.$label);
  $value=trim($value);
  if (($required && $value==='') || mb_strlen($value,'UTF-8')>$max) throw new InvalidArgumentException('Revisa '.$label.' (máximo '.$max.' caracteres).');
  return $value;
}