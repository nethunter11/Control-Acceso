<?php
require_once __DIR__.'/../app/auth.php';
require_roles(['ADMIN']);
$configMeta = [
  'config_gestion'=>['Gestión de usuarios','config-gestion'],
  'config_sistema'=>['Sistema','config-sistema'],
  'config_exportaciones'=>['Exportaciones','config-exportaciones'],
];
if (!isset($configMeta[$activePage ?? ''])) { http_response_code(404); exit; }
[$configTitle,$configScript] = $configMeta[$activePage];
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $configTitle ?> | Control de Acceso</title>
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/../assets/css/theme.css') ?>" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/configuracion.css?v=<?= filemtime(__DIR__.'/../assets/css/configuracion.css') ?>" rel="stylesheet">
  <?php if ($activePage === 'config_sistema'): ?>
  <link href="/Control-Acceso/assets/css/sistema.css?v=<?= filemtime(__DIR__.'/../assets/css/sistema.css') ?>" rel="stylesheet">
  <?php endif; ?>
</head>
<body>
<div class="app">
  <?php include __DIR__.'/sidebar.php'; ?>
  <main class="main" id="mainContent" tabindex="-1">
    <?php include __DIR__.'/topbar.php'; ?>
    <section class="content">
      <?php include __DIR__.'/page-heading.php'; ?>
