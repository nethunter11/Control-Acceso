<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN','GUARDIA']);
require_once __DIR__ . '/app/puestos.php';
$activePage = 'validacion';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Validación | Control de Acceso</title>
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

      <div class="validation-layout">
        <div class="card card-dark validation-form">
          <div class="card-header card-header-dark">
            <div class="card-section-title"><i class="bi bi-person-vcard" aria-hidden="true"></i><h2 class="h6 mb-0">Validar funcionario</h2></div>
            <div class="text-muted small">Ingresa el RUT y selecciona el movimiento.</div>
          </div>
          <div class="card-body">
            <div class="validation-fields">
              <div><label class="form-label" for="rut">RUT del funcionario</label><input class="form-control form-control-dark mono" id="rut" placeholder="12345678-5" autocomplete="off" spellcheck="false" aria-describedby="rutHint"><div class="input-hint" id="rutHint">Ingresa el RUT con su dígito verificador.</div></div>
              <div>
                <label class="form-label" for="puesto">Puesto</label>
                <select class="form-select form-control-dark" id="puesto">
                  <?php foreach (PUESTOS_ACCESO as $puestoAcceso): ?>
                    <option value="<?= htmlspecialchars($puestoAcceso, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($puestoAcceso, ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <div id="accessModeLabel" class="form-label">Modo de acceso</div>
                <div class="btn-group segmented-control w-100" role="group" aria-labelledby="accessModeLabel">
                  <button type="button" id="btnModoPeatonal" class="btn btn-outline-light active" aria-pressed="true" onclick="setModo('PEATONAL')"><i class="bi bi-person-walking" aria-hidden="true"></i>Peatonal</button>
                  <button type="button" id="btnModoVehiculo" class="btn btn-outline-light" aria-pressed="false" onclick="setModo('VEHICULO')"><i class="bi bi-car-front" aria-hidden="true"></i>Vehicular</button>
                </div>
              </div>
            </div>
            <div class="access-actions">
              <button id="btnEntrada" class="btn btn-success" onclick="registrar('ENTRADA')"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Registrar entrada</button>
              <button id="btnSalida" class="btn btn-warning" onclick="registrar('SALIDA')"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Registrar salida</button>
              <button id="btnConsultar" class="btn btn-outline-light" onclick="soloConsultar()"><i class="bi bi-search" aria-hidden="true"></i>Solo consultar</button>
              <button id="btnLimpiar" class="btn btn-outline-secondary" onclick="limpiar()"><i class="bi bi-eraser" aria-hidden="true"></i>Limpiar</button>
            </div>
            <div class="input-hint mt-3"><kbd>Enter</kbd> consulta el RUT. Usa los botones para confirmar una entrada o salida.</div>
            <div class="mt-3" id="consultaMensaje" role="status" aria-live="polite"></div><div class="mt-3" id="resultado" role="status" aria-live="polite" aria-atomic="true"></div>
          </div>
        </div>
        <div class="card card-dark">
          <div class="card-header card-header-dark">
            <div class="d-flex align-items-center justify-content-between">
              <div><div class="d-flex align-items-center gap-2"><h2 class="h6 mb-0">Personal dentro</h2><span class="badge badge-soft" id="cntDentro">—</span></div><div class="text-muted small">Incluye ingresos de días anteriores que aún no tienen salida.</div></div>
              <button class="btn btn-sm btn-outline-light" onclick="cargarDentro()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Actualizar</button>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-4"><div class="form-label" id="insideDateLabel">Última actualización</div><div class="form-control form-control-dark d-flex align-items-center" aria-labelledby="insideDateLabel"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i><span id="d_fecha_text">Consultando…</span></div></div>
              <div class="col-12 col-md-4"><label class="form-label" for="d_rut">Nombre, RUT o unidad <span class="text-muted fw-normal">(opcional)</span></label><input id="d_rut" class="form-control form-control-dark" maxlength="120" placeholder="Nombre o RUT" autocomplete="off"></div><div class="col-12 col-md-4"><label class="form-label" for="d_puesto">Puesto de ingreso</label><select id="d_puesto" class="form-select form-control-dark"><option value="">Todos</option></select></div>
            </div>
            <label class="small rows-label mt-3" for="insideLimit">Filas por página<select id="insideLimit" class="form-select form-control-dark"><option>25</option><option>50</option><option>100</option></select></label><p class="small text-muted mt-3 mb-0" id="d_estado" role="status" aria-live="polite">Consultando presencia actual…</p><div class="mt-4 table-wrap" id="tablaDentro"><div class="table-loading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando registros…</div></div><div class="table-pagination mt-3" aria-label="Páginas de personal dentro"><button id="insidePrev" type="button" class="btn btn-outline-light" onclick="cargarDentro(Tablas.numero('inside')-1)" disabled>Anterior</button><label for="insidePage" class="small">Página</label><input id="insidePage" class="form-control form-control-dark page-number" type="number" min="1" value="1" disabled><button id="insideNext" type="button" class="btn btn-outline-light" onclick="cargarDentro(Tablas.numero('inside')+1)" disabled>Siguiente</button></div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>
<dialog id="consultaFuncionario" class="consulta-dialog" aria-labelledby="consultaTitulo" aria-describedby="consultaDescripcion">
  <form method="dialog">
    <header class="consulta-dialog-header">
      <div class="consulta-dialog-heading">
        <span class="consulta-dialog-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
        <div><h2 id="consultaTitulo">Consulta de funcionario</h2><p id="consultaDescripcion">Identidad y estado de acceso</p></div>
      </div>
      <button type="submit" class="btn-icon" value="cerrar" aria-label="Cerrar consulta"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </header>
    <div id="fichaFuncionario" class="consulta-dialog-body"></div>
    <footer class="consulta-dialog-footer">
      <button type="submit" id="btnCerrarConsulta" class="btn btn-primary" value="cerrar" autofocus>Cerrar consulta</button>
    </footer>
  </form>
</dialog>
<?php include __DIR__ . '/partials/scripts.php'; ?>
<script src="/Control-Acceso/assets/js/registro-popup.js?v=<?= filemtime(__DIR__ . '/assets/js/registro-popup.js') ?>"></script>
<script src="/Control-Acceso/assets/js/personal-dentro.js?v=<?= filemtime(__DIR__ . '/assets/js/personal-dentro.js') ?>"></script>
<script src="/Control-Acceso/assets/js/validacion.js?v=<?= filemtime(__DIR__ . '/assets/js/validacion.js') ?>"></script>

</body>
</html>
