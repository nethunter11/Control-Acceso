<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN','CONTROL_VISITA']);
$activePage = 'visitas';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Visitas | Control de Acceso</title>
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="main" id="mainContent" tabindex="-1">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <section class="content">
      <?php include __DIR__ . '/partials/page-heading.php'; ?>

      <div class="visits-layout">
        <div class="card card-dark">
          <div class="card-header card-header-dark"><div class="card-section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><h2 class="h6 mb-0">Nueva visita</h2></div><div class="text-muted small">Completa los datos para registrar el ingreso.</div></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12"><label class="form-label" for="v_nombre">Nombre completo</label><input id="v_nombre" class="form-control form-control-dark" placeholder="Nombre y apellido" autocomplete="off"></div>
              <div class="col-12"><label class="form-label" for="v_rut">RUT</label><input id="v_rut" class="form-control form-control-dark" placeholder="12345678-5" autocomplete="off" spellcheck="false"></div>
              <div class="col-12"><label class="form-label" for="v_tel">Teléfono celular</label><input id="v_tel" type="tel" class="form-control form-control-dark" placeholder="+56 9 1234 5678" autocomplete="off"></div>
              <div class="col-12">
                <div class="form-label" id="visitModeLabel">Modo de acceso</div>
                <div class="btn-group segmented-control w-100" role="group" aria-labelledby="visitModeLabel">
                  <button type="button" class="btn btn-outline-light active" id="btnPea" aria-pressed="true" onclick="setModoV('PEATONAL')"><i class="bi bi-person-walking" aria-hidden="true"></i>Peatonal</button>
                  <button type="button" class="btn btn-outline-light" id="btnVeh" aria-pressed="false" onclick="setModoV('VEHICULO')"><i class="bi bi-car-front" aria-hidden="true"></i>Vehicular</button>
                </div>
              </div>
              <div class="col-12" id="boxPatente" style="display:none;"><label class="form-label" for="v_pat">Patente</label><input id="v_pat" class="form-control form-control-dark mono" placeholder="ABCD12" autocomplete="off" spellcheck="false"></div>
              <div class="col-12"><label class="form-label" for="v_motivo">Motivo <span class="text-muted fw-normal">(opcional)</span></label><input id="v_motivo" class="form-control form-control-dark" placeholder="Reunión, entrega, visita técnica…"></div>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-4">
              <button id="btnVisitaEntrada" class="btn btn-success flex-grow-1" onclick="registrarEntrada()"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Registrar entrada</button>
              <button class="btn btn-outline-secondary" onclick="limpiarForm()"><i class="bi bi-eraser" aria-hidden="true"></i>Limpiar</button>
            </div>
            <div class="mt-3" id="v_resultado" role="status" aria-live="polite" aria-atomic="true"></div>
          </div>
        </div>
        <div class="card card-dark">
          <div class="card-header card-header-dark"><div class="d-flex justify-content-between align-items-center"><div><h2 class="h6 mb-0">Listado de visitas</h2><div class="text-muted small">Consulta ingresos y registra las salidas pendientes.</div></div><button id="btnVisitasRefresh" class="btn btn-sm btn-outline-light" onclick="cargarVisitas()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Actualizar</button></div></div>
          <div class="card-body">
            <div class="btn-group mb-3" role="group" aria-label="Vista de visitas"><button id="view_dentro" type="button" class="btn btn-outline-light active" aria-pressed="true" onclick="cambiarVistaVisitas('dentro')">Dentro ahora</button><button id="view_historial" type="button" class="btn btn-outline-light" aria-pressed="false" onclick="cambiarVistaVisitas('historial')">Historial</button></div><p id="visitHelp" class="small text-muted"></p>
            <div class="row g-3">
              <div class="col-12 col-md-4"><label class="form-label" for="f_fecha">Fecha de ingreso</label><input id="f_fecha" type="date" class="form-control form-control-dark"></div>
              <div class="col-12 col-md-4"><label class="form-label" for="f_rut">RUT</label><input id="f_rut" class="form-control form-control-dark" placeholder="12345678-5" autocomplete="off"></div>
              <div class="col-12 col-md-4"><label class="form-label" for="f_estado">Estado</label><select id="f_estado" class="form-select form-control-dark"><option value="">Todos</option><option value="DENTRO">Dentro</option><option value="SALIO">Salió</option></select></div>
            </div>
            <div class="filter-actions"><button class="btn btn-primary" onclick="cargarVisitas()"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar filtros</button><button class="btn btn-outline-secondary" onclick="limpiarFiltros()">Limpiar</button></div>
            <p id="visitSummary" class="small text-muted mt-3" role="status" aria-live="polite">Consultando visitas…</p><div class="d-flex gap-2"><button id="visitsPrev" type="button" class="btn btn-sm btn-outline-light" onclick="cargarVisitas(visitsPage-1)" disabled>Anterior</button><button id="visitsNext" type="button" class="btn btn-sm btn-outline-light" onclick="cargarVisitas(visitsPage+1)" disabled>Siguiente</button></div><div class="mt-4 table-wrap" id="tabla"><div class="table-loading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando registros…</div></div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>
<?php include __DIR__ . '/partials/scripts.php'; ?>
<script src="/Control-Acceso/assets/js/visitas.js?v=<?= filemtime(__DIR__ . '/assets/js/visitas.js') ?>"></script>
</body>
</html>
