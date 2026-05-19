<?php
require_once __DIR__ . '/app/auth.php';
require_login();

$rol = auth_role();

switch ($rol) {
  case 'ADMIN':
  case 'GUARDIA':
    header('Location: /Control-Acceso/index.php');
    exit;
  case 'CONTROL_VISITA':
    header('Location: /Control-Acceso/visitas.php');
    exit;
  case 'AUDITOR':
    header('Location: /Control-Acceso/registros.php');
    exit;
  default:
    // si algo raro pasa, manda a login
    header('Location: /Control-Acceso/auth/login.php');
    exit;
}