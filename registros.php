<?php
require_once __DIR__.'/app/auth.php';
require_roles(['ADMIN','GUARDIA','AUDITOR']);
$activePage='registros';
?>
<!doctype html><html lang="es" data-bs-theme="dark"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registros | Control de Acceso</title>
<link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/assets/css/theme.css') ?>" rel="stylesheet">
</head><body><div class="app">
<?php include __DIR__.'/partials/sidebar.php'; ?>
<main class="main" id="mainContent" tabindex="-1">
<?php include __DIR__.'/partials/topbar.php'; ?><section class="content">
<section class="card card-dark">
<div class="card-header card-header-dark"><div class="d-flex align-items-center justify-content-between gap-3"><div><h2 class="h6 mb-0">Historial de movimientos</h2><div class="small text-muted">Incluye parejas con entrada o salida en el periodo. Los rechazos se muestran por separado.</div></div><button id="btnRegRefresh" type="button" class="btn btn-outline-light" onclick="cargarUltimos()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Actualizar</button></div></div>
<div class="card-body">
<form id="recordsFilters" class="row g-3 align-items-end">
<div class="col-12 col-lg-4"><label class="form-label" for="f_rut">Buscar por nombre o RUT</label><input id="f_rut" class="form-control form-control-dark" maxlength="120" autocomplete="off" placeholder="Nombre o RUT, con o sin puntos"></div>
<div class="col-6 col-lg-2"><label class="form-label" for="f_fecha">Desde</label><input id="f_fecha" type="date" class="form-control form-control-dark"></div>
<div class="col-6 col-lg-2"><label class="form-label" for="f_hasta">Hasta</label><input id="f_hasta" type="date" class="form-control form-control-dark"></div>
<div class="col-6 col-lg-2"><label class="form-label" for="f_puesto">Puesto</label><select id="f_puesto" class="form-select form-control-dark"><option value="">Todos</option></select></div>
<div class="col-6 col-lg-2"><label class="form-label" for="f_resultado">Resultado</label><select id="f_resultado" class="form-select form-control-dark"><option value="">Todos</option><option value="APROBADO">Aprobado</option><option value="RECHAZADO">Rechazado</option></select></div>
<div class="col-12 filter-actions"><button id="btnRegAplicar" type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar filtros</button><button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">Limpiar / Todo el historial</button></div>
</form>
<div class="table-toolbar mt-3"><p id="recordsSummary" class="small text-muted mb-0" role="status" aria-live="polite">Consultando movimientos…</p><label class="small rows-label" for="recordsLimit">Filas por página<select id="recordsLimit" class="form-select form-control-dark"><option>25</option><option>50</option><option>100</option></select></label></div>
<div class="table-wrap mt-3" id="tabla"><div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>Nombre</th><th>RUT</th><th>Movimiento</th><th>Entrada</th><th>Operador entrada</th><th>Salida</th><th>Operador salida</th><th>Modo</th><th>Puesto</th><th>Resultado</th><th>Motivo</th></tr></thead><tbody id="recordsBody"><tr><td colspan="11"><div class="table-loading">Cargando registros…</div></td></tr></tbody></table></div></div>
<div class="table-pagination mt-3" aria-label="Páginas del historial"><button id="recordsPrev" type="button" class="btn btn-outline-light" onclick="cargarUltimos(Tablas.numero('records')-1)" disabled>Anterior</button><label for="recordsPage" class="small">Página</label><input id="recordsPage" class="form-control form-control-dark page-number" type="number" min="1" value="1" aria-label="Ir a página del historial" disabled><button id="recordsNext" type="button" class="btn btn-outline-light" onclick="cargarUltimos(Tablas.numero('records')+1)" disabled>Siguiente</button></div>
<p class="small text-muted mt-3 mb-0">Los movimientos antiguos sin operador indican «Sin registro».</p>
</div></section></section></main></div>
<?php include __DIR__.'/partials/scripts.php'; ?>
<script src="/Control-Acceso/assets/js/registros.js?v=<?= filemtime(__DIR__.'/assets/js/registros.js') ?>"></script>
</body></html>