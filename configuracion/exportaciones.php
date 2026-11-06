<?php
$activePage='config_exportaciones';
$exportToday=(new DateTimeImmutable('now',new DateTimeZone('America/Santiago')))->format('Y-m-d');
include __DIR__.'/../partials/config-header.php';
?>
<div class="card card-dark settings-page">
  <div class="card-header card-header-dark"><div class="settings-intro"><i class="bi bi-download" aria-hidden="true"></i><div><h2 class="h6 mb-0">Descargar registros</h2><div class="text-muted small">Selecciona el listado y el día, o descarga el periodo completo.</div></div></div></div>
  <div class="card-body">
                <form id="csvForm">
                  <div class="panel">
                    <div class="row g-3">
                      <div class="col-12 col-md-6">
                        <label class="form-label" for="csvTable">Qué quieres exportar</label>
                        <select id="csvTable" class="form-select form-control-dark">
                          <option value="registros_acceso">Registros de acceso</option>
                          <option value="visitas">Registros de visitas</option>
                          <option value="funcionarios">Funcionarios</option>
                          <option value="usuarios">Usuarios</option>
                        </select>
                      </div>
                      <div class="col-12 col-md-6" id="csvPeriodGroup">
                        <label class="form-label" for="csvPeriod">Periodo</label>
                        <select id="csvPeriod" class="form-select form-control-dark">
                          <option value="dia">Un día</option>
                          <option value="todos">Todos los registros</option>
                        </select>
                      </div>
                      <div class="col-12 col-md-6" id="csvDateGroup">
                        <label class="form-label" for="csvDate">Día a exportar</label>
                        <input id="csvDate" type="date" class="form-control form-control-dark" value="<?= htmlspecialchars($exportToday, ENT_QUOTES, 'UTF-8') ?>" required>
                      </div>
                      <div class="col-12 col-md-6" id="csvCriterionGroup" hidden>
                        <label class="form-label" for="csvCriterion">Fecha de la visita</label>
                        <select id="csvCriterion" class="form-select form-control-dark" disabled>
                          <option value="actividad">Ingresos o salidas del día</option>
                          <option value="entrada">Solo ingresos del día</option>
                          <option value="salida">Solo salidas del día</option>
                        </select>
                      </div>
                    </div>
                    <p id="csvHelp" class="small text-muted mt-3 mb-0"></p>
                  </div>
                  <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                    <button id="btnExportCsv" type="submit" class="btn btn-primary"><i class="bi bi-download me-1"></i>Descargar CSV</button>
                    <span class="small text-muted">CSV UTF-8 · Compatible con Excel</span>
                  </div>
                  <div id="csvMessage" class="settings-export-note small mt-3" role="status" aria-live="polite"></div>
                </form>
  </div>
</div>
<?php include __DIR__.'/../partials/config-footer.php'; ?>
