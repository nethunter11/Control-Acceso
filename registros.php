<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN','GUARDIA','AUDITOR']);
$activePage = 'registros';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registros | Control de Acceso</title>
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

      <div class="card card-dark">
        <div class="card-header card-header-dark"><div class="d-flex align-items-center justify-content-between"><div><h2 class="h6 mb-0">Historial de movimientos</h2><div class="text-muted small">Incluye entradas y salidas del día. Los rechazos se muestran por separado.</div></div><button id="btnRegRefresh" class="btn btn-sm btn-outline-light" onclick="cargarUltimos()"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Actualizar</button></div></div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-6 col-xl-3"><label class="form-label" for="f_fecha">Día del movimiento</label><input id="f_fecha" type="date" class="form-control form-control-dark"></div>
            <div class="col-12 col-sm-6 col-xl-3"><label class="form-label" for="f_rut">RUT</label><input id="f_rut" class="form-control form-control-dark" placeholder="12345678-5" autocomplete="off"></div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="form-label" id="resultFilterLabel">Resultado</div>
              <div class="dropdown">
                <button class="btn form-control-dark w-100 d-flex justify-content-between align-items-center" type="button" id="btnResultado" data-bs-toggle="dropdown" aria-expanded="false" aria-labelledby="resultFilterLabel resultadoText"><span id="resultadoText">Todos</span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-dark w-100" aria-labelledby="btnResultado"><li><a class="dropdown-item" href="#" data-val="">Todos</a></li><li><a class="dropdown-item" href="#" data-val="APROBADO">Aprobado</a></li><li><a class="dropdown-item" href="#" data-val="RECHAZADO">Rechazado</a></li></ul>
                <input type="hidden" id="f_resultado" value="">
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3 d-flex gap-2"><button id="btnRegAplicar" class="btn btn-primary flex-grow-1" onclick="cargarUltimos()"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar</button><button class="btn btn-outline-secondary" onclick="limpiarFiltros()">Limpiar</button></div>
          </div>
          <div class="mt-4 table-wrap" id="tabla"><div class="table-loading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando registros…</div></div>
        </div>
      </div>
    </section>
  </main>
</div>
<?php include __DIR__ . '/partials/scripts.php'; ?>
  <script>
  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function setButtonLoading(id, loading, label = 'Cargando...') {
    const btn = document.getElementById(id);
    if (!btn) return;

    if (loading) {
      if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${label}`;
      return;
    }

    btn.disabled = false;
    if (btn.dataset.originalHtml) {
      btn.innerHTML = btn.dataset.originalHtml;
      delete btn.dataset.originalHtml;
    }
  }

  async function cargarUltimos() {
    setButtonLoading('btnRegRefresh', true, 'Cargando...');
    setButtonLoading('btnRegAplicar', true, 'Cargando...');

    const fecha = document.getElementById('f_fecha')?.value || '';
    const rut = document.getElementById('f_rut')?.value.trim() || '';
    const resultado = document.getElementById('f_resultado')?.value || '';

    const params = new URLSearchParams();
    if (fecha) params.set('fecha', fecha);
    if (rut) params.set('rut', rut);
    if (resultado) params.set('resultado', resultado);
    params.set('limit', '50');

    try {
      const res = await fetch('api/registros_resumen.php?' + params.toString());
      const data = await res.json();

      if (!data.ok) {
        document.getElementById('tabla').innerHTML = `<div class="alert alert-danger mb-0">Error cargando registros.</div>`;
        return;
      }

      const rows = data.items || [];
      let html = `<div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>RUT</th><th>Movimiento</th><th>Entrada</th><th>Operador entrada</th><th>Salida</th><th>Operador salida</th><th>Modo</th><th>Puesto</th><th>Resultado</th><th>Motivo</th>
          </tr>
        </thead><tbody>`;

      for (const r of rows) {
        const modoLabel = (r.modo === 'VEHICULO') ? 'Vehicular' : 'Peatonal';

        const badge = (r.resultado === 'APROBADO')
          ? `<span class="badge text-bg-success">APROBADO</span>`
          : `<span class="badge text-bg-danger">RECHAZADO</span>`;

        html += `<tr>
        <td>${escapeHtml(r.rut)}</td>
        <td>${r.entrada && r.salida ? 'Entrada / salida' : escapeHtml(r.tipo_movimiento === 'SALIDA' ? 'Salida' : 'Entrada')}</td>
        <td>${escapeHtml(r.entrada || '—')}</td>
        <td>${escapeHtml(r.entrada ? (r.operador_entrada || 'Sin registro') : '—')}</td>
        <td>${escapeHtml(r.salida || '—')}</td>
        <td>${escapeHtml(r.salida ? (r.operador_salida || 'Sin registro') : '—')}</td>
        <td>${escapeHtml(modoLabel)}</td>
        <td>${escapeHtml(r.puesto || '')}</td>
        <td>${badge}</td>
        <td class="text-muted">${escapeHtml(r.motivo || r.incidencia || '')}</td>
        </tr>`;

      }

      if (rows.length === 0) html += "<tr><td colspan=\"10\"><div class=\"table-empty\"><i class=\"bi bi-inbox\" aria-hidden=\"true\"></i>No hay movimientos que coincidan con los filtros.</div></td></tr>";
      html += `</tbody></table></div>`;
      document.getElementById('tabla').innerHTML = html;
    } catch (err) {
      console.error(err);
      document.getElementById('tabla').innerHTML = `<div class="alert alert-danger mb-0">Error cargando registros.</div>`;
    } finally {
      setButtonLoading('btnRegRefresh', false);
      setButtonLoading('btnRegAplicar', false);
    }
  }

  function limpiarFiltros() {
    document.getElementById('f_fecha').value = new Date().toISOString().slice(0,10);
    document.getElementById('f_rut').value = '';
    document.getElementById('f_resultado').value = '';
    cargarUltimos();
  }

  // Quick search en topbar (Enter) + auto-carga por fecha
  document.addEventListener('DOMContentLoaded', () => {

    // 1) Fecha por defecto = hoy
    const f = document.getElementById('f_fecha');
    if (f && !f.value) {
      f.value = new Date().toISOString().slice(0,10);
    }

    // 2) Cargar registros al iniciar
    cargarUltimos();

    // 3) Auto-cargar al cambiar la fecha
    f?.addEventListener('change', () => cargarUltimos());

    // 4) Quick search (Enter) -> rellena RUT y recarga
    const q = document.getElementById('quickSearch');
    q?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('f_rut').value = q.value.trim();
        cargarUltimos();
      }
    });

  });

  // Dropdown Resultado (oscuro) -> setea hidden y recarga
  document.querySelectorAll('.dropdown-item[data-val]').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const val = a.getAttribute('data-val') || '';

      document.getElementById('f_resultado').value = val;
      document.getElementById('resultadoText').textContent = (val === '') ? 'Todos' : val;

      cargarUltimos();
    });
  });


</script>
</body>
</html>
