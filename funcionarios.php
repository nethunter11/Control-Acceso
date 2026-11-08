<?php
require_once __DIR__.'/app/auth.php';
require_roles(['ADMIN']);
$activePage='funcionarios';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Funcionarios | Control de Acceso</title>
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body><div class="app">
<?php include __DIR__.'/partials/sidebar.php'; ?>
<main class="main" id="mainContent" tabindex="-1">
<?php include __DIR__.'/partials/topbar.php'; ?>
<section class="content">
<div class="row g-3">
  <div class="col-12 col-xl-6">
    <section class="card card-dark h-100">
      <div class="card-header card-header-dark"><h2 class="h6 mb-0" id="staffFormTitle">Nuevo funcionario</h2></div>
      <div class="card-body">
        <form id="staffForm">
          <div class="row g-3">
            <div class="col-12 col-sm-5"><label class="form-label" for="sf_rut">RUT</label><input id="sf_rut" class="form-control form-control-dark mono" required maxlength="20" autocomplete="off" placeholder="12345678-5"><div class="input-hint">El RUT no se modifica después de crear la ficha.</div></div>
            <div class="col-12 col-sm-7"><label class="form-label" for="sf_nombres">Nombre completo</label><input id="sf_nombres" class="form-control form-control-dark" required maxlength="120" autocomplete="off"></div>
            <div class="col-12 col-sm-6"><label class="form-label" for="sf_unidad">Unidad</label><input id="sf_unidad" class="form-control form-control-dark" maxlength="120"></div>
            <div class="col-12 col-sm-6"><label class="form-label" for="sf_grado">Grado</label><input id="sf_grado" class="form-control form-control-dark" maxlength="80"></div>
            <div class="col-12"><label class="form-label" for="sf_estado">Estado</label><select id="sf_estado" class="form-select form-control-dark"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select><div class="input-hint">Desactivar impide nuevos ingresos y conserva el historial.</div></div>
          </div>
          <div class="d-flex gap-2 mt-4"><button id="staffSave" type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Guardar funcionario</button><button id="staffNew" type="button" class="btn btn-outline-secondary">Limpiar / Nuevo</button></div>
          <p class="small mt-3 mb-0" id="staffMessage" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-6">
    <section class="card card-dark h-100">
      <div class="card-header card-header-dark"><h2 class="h6 mb-0">Importar funcionarios desde CSV</h2></div>
      <div class="card-body">
        <p class="small text-muted">Archivo UTF-8, separado por comas o punto y coma. Máximo 1 MB y 1000 filas. Primero revisa los datos; se guardan al confirmar.</p>
        <form id="staffImportForm">
          <label class="form-label" for="staffCsv">Archivo CSV</label><input id="staffCsv" type="file" accept=".csv,text/csv" class="form-control form-control-dark" required>
          <label class="form-label mt-3" for="staffImportMode">Modo de importación</label><select id="staffImportMode" class="form-select form-control-dark"><option value="crear">Solo crear funcionarios nuevos</option><option value="actualizar">Crear nuevos y actualizar los existentes</option></select>
          <div class="d-flex gap-2 mt-4 flex-wrap"><button id="staffPreview" type="submit" class="btn btn-outline-light"><i class="bi bi-search" aria-hidden="true"></i>Revisar archivo</button><a class="btn btn-outline-secondary" href="/Control-Acceso/api/admin_funcionarios.php?plantilla=1"><i class="bi bi-download" aria-hidden="true"></i>Plantilla CSV</a></div>
        </form>
        <p class="small mt-3 mb-0" id="staffImportMessage" role="status" aria-live="polite"></p>
      </div>
    </section>
  </div>
</div>
<section class="card card-dark mt-3" id="staffPreviewPanel" hidden>
  <div class="card-header card-header-dark"><h2 class="h6 mb-0">Vista previa de importación</h2></div>
  <div class="card-body"><p id="staffPreviewSummary"></p><div id="staffPreviewTable" class="table-wrap"></div><button id="staffImportApply" type="button" class="btn btn-primary mt-3" disabled>Confirmar importación</button></div>
</section>
<section class="card card-dark mt-3">
  <div class="card-header card-header-dark"><h2 class="h6 mb-0">Funcionarios registrados</h2></div>
  <div class="card-body">
    <form id="staffSearchForm" class="row g-3 align-items-end">
      <div class="col-12 col-md-7"><label class="form-label" for="staffSearch">Buscar por nombre, RUT o unidad</label><input id="staffSearch" class="form-control form-control-dark" maxlength="120" autocomplete="off"></div>
      <div class="col-12 col-md-3"><label class="form-label" for="staffStateFilter">Estado</label><select id="staffStateFilter" class="form-select form-control-dark"><option value="">Todos</option><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select></div>
      <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100">Buscar</button></div>
    </form>
    <div class="table-toolbar mt-3"><p class="small text-muted mb-0" id="staffListSummary" role="status" aria-live="polite">Consultando funcionarios…</p><label class="small rows-label" for="staffLimit">Filas por página<select id="staffLimit" class="form-select form-control-dark"><option>25</option><option>50</option><option>100</option></select></label></div>
    <div id="staffTable" class="table-wrap mt-3"></div>
    <div class="table-pagination mt-3"><button id="staffPrev" type="button" class="btn btn-outline-light" disabled>Anterior</button><label for="staffPage" class="small">Página</label><input id="staffPage" class="form-control form-control-dark page-number" type="number" min="1" value="1" disabled><button id="staffNext" type="button" class="btn btn-outline-light" disabled>Siguiente</button></div>
  </div>
</section>
</section></main></div>
<?php include __DIR__.'/partials/scripts.php'; ?>
<script src="/Control-Acceso/assets/js/funcionarios.js?v=<?= filemtime(__DIR__.'/assets/js/funcionarios.js') ?>"></script>
</body></html>