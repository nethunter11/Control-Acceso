<?php
require_once __DIR__.'/entrada.php';
require_once __DIR__.'/rut.php';
require_once __DIR__.'/rut_funcionario.php';

function tabla_pagina(array $query,int $total,int $defaultLimit=25): array {
  $limit=filter_var($query['limit'] ?? $defaultLimit,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>200]]);
  $requested=filter_var($query['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if($limit===false || $requested===false)throw new InvalidArgumentException('Selecciona una página y cantidad de filas válidas.');
  $pages=max(1,(int)ceil($total/$limit));$page=min($requested,$pages);$offset=($page-1)*$limit;
  return ['total'=>$total,'page'=>$page,'pages'=>$pages,'limit'=>$limit,'offset'=>$offset,'from'=>$total?$offset+1:0,'to'=>min($total,$offset+$limit)];
}
function tabla_rut_busqueda(string $text): string {
  $plain=preg_replace('/[. ]/','',$text);
  $normalized=rut_normalizar($text);
  return rut_es_valido($normalized)?$normalized:$plain;
}
function tabla_like(string $text): string {
  // El usuario busca texto literal; % y _ no amplían la búsqueda accidentalmente.
  return '%'.strtr($text,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
}
function tabla_puestos(PDO $pdo): array {
  require_once __DIR__.'/puestos.php';
  $saved=$pdo->query("SELECT DISTINCT puesto FROM registros_acceso WHERE puesto IS NOT NULL AND puesto<>'' ORDER BY puesto")->fetchAll(PDO::FETCH_COLUMN);
  return array_values(array_unique(array_merge(PUESTOS_ACCESO,$saved)));
}