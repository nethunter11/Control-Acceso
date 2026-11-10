<?php
const FUNCIONARIO_IMAGENES = ['Imagen_hombre.png','Imagen_mujer.png'];
function funcionario_imagen_validar(mixed $value): ?string {
  if($value===null || $value==='')return null;
  if(!is_string($value) || !in_array($value,FUNCIONARIO_IMAGENES,true)) {
    throw new InvalidArgumentException('Selecciona una imagen válida para el funcionario.');
  }
  return $value;
}
function funcionario_imagen_url(mixed $value): ?string {
  return is_string($value) && in_array($value,FUNCIONARIO_IMAGENES,true)
    ? '/Control-Acceso/img/funcionarios/'.$value : null;
}
