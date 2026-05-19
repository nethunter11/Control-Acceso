<?php
require_once __DIR__ . '/app/auth.php';
require_login();
require_roles(['ADMIN','CONTROL_VISITA']); 
$activePage = 'visitas';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Visitas | Control Acceso</title>

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>

<body>
  <div class="app">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main">
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <section class="content">
        <div class="row g-3">
          <!-- FORM -->
          <div class="col-12 col-xl-5">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="h6 mb-0">INGRESO DE VISITA</div>
                <div class="text-muted small">Registrar entrada y salida</div>
              </div>

              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label">Nombre</label>
                    <input id="v_nombre" class="form-control form-control-dark" placeholder="Nombre y apellido">
                  </div>

                  <div class="col-12">
                    <label class="form-label">RUT</label>
                    <input id="v_rut" class="form-control form-control-dark" placeholder="12345678-5">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Teléfono celular</label>
                    <input id="v_tel" class="form-control form-control-dark" placeholder="+56 9 1234 5678">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Modo</label>
                    <div class="btn-group w-100" role="group">
                      <button type="button" class="btn btn-outline-light active" id="btnPea" onclick="setModoV('PEATONAL')">Peatonal</button>
                      <button type="button" class="btn btn-outline-light" id="btnVeh" onclick="setModoV('VEHICULO')">Vehículo</button>
                    </div>
                  </div>

                  <div class="col-12" id="boxPatente" style="display:none;">
                    <label class="form-label">Patente</label>
                    <input id="v_pat" class="form-control form-control-dark" placeholder="ABCD12">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Puesto</label>
                    <input id="v_puesto" class="form-control form-control-dark" value="Portón 1">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Motivo (opcional)</label>
                    <input id="v_motivo" class="form-control form-control-dark" placeholder="Reunión / Entrega / Visita técnica...">
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-3">
                  <button class="btn btn-success" onclick="registrarEntrada()">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Registrar entrada
                  </button>
                  <button class="btn btn-outline-secondary" onclick="limpiarForm()">
                    <i class="bi bi-x-circle me-1"></i> Limpiar
                  </button>
                </div>

                <div class="mt-3" id="v_resultado"></div>
              </div>
            </div>
          </div>

          <!-- TABLA -->
          <div class="col-12 col-xl-7">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="h6 mb-0">LISTADO DE VISITAS</div>
                    <div class="text-muted small">Filtrar por fecha/RUT/estado</div>
                  </div>
                  <button class="btn btn-sm btn-outline-light" onclick="cargarVisitas()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refrescar
                  </button>
                </div>
              </div>

              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12 col-md-4">
                    <label class="form-label">Fecha</label>
                    <input id="f_fecha" type="date" class="form-control form-control-dark">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">RUT</label>
                    <input id="f_rut" class="form-control form-control-dark" placeholder="12345678-5">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Estado</label>
                    <select id="f_estado" class="form-select form-control-dark">
                      <option value="">Todos</option>
                      <option value="DENTRO">DENTRO</option>
                      <option value="SALIO">SALIO</option>
                    </select>
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-2">
                  <button class="btn btn-primary" onclick="cargarVisitas()">
                    <i class="bi bi-funnel me-1"></i> Aplicar
                  </button>
                  <button class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                    <i class="bi bi-x-circle me-1"></i> Limpiar
                  </button>
                </div>

                <div class="mt-3 table-wrap" id="tabla"></div>
              </div>
            </div>
          </div>

        </div>
      </section>
    </main>
  </div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    let modoVisita = 'PEATONAL';

    function escapeHtml(s){
      return String(s ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;");
    }

    async function postJSON(url, payload){
      const res = await fetch(url, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      return res.json();
    }

    function setModoV(m){
      modoVisita = m;
      document.getElementById('btnPea').classList.toggle('active', m==='PEATONAL');
      document.getElementById('btnVeh').classList.toggle('active', m==='VEHICULO');
      document.getElementById('boxPatente').style.display = (m==='VEHICULO') ? 'block' : 'none';
      if (m==='PEATONAL') document.getElementById('v_pat').value = '';
    }

    function setResultado(html){
      document.getElementById('v_resultado').innerHTML = html;
    }

    async function registrarEntrada(){
      const nombre = document.getElementById('v_nombre').value.trim();
      const rut = document.getElementById('v_rut').value.trim();
      const telefono = document.getElementById('v_tel').value.trim();
      const patente = document.getElementById('v_pat').value.trim();
      const puesto = document.getElementById('v_puesto').value.trim() || 'Portón 1';
      const motivo = document.getElementById('v_motivo').value.trim();

      const data = await postJSON('api/visitas_entrada.php', { nombre, rut, telefono, modo: modoVisita, patente, puesto, motivo });

      if (!data.ok) {
        setResultado(`<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error')}</div>`);
        return;
      }

      const ok = (data.resultado || '') === 'APROBADO';
      if (!ok) {
        setResultado(`<div class="alert alert-warning mb-0"><b>RECHAZADO</b><div class="small">${escapeHtml(data.motivo || '')}</div></div>`);
        return;
      }

      setResultado(`<div class="alert alert-success mb-0"><b>APROBADO</b> - Entrada registrada</div>`);
      limpiarForm(false);
      await cargarVisitas();
    }

    async function marcarSalida(id){
      const data = await postJSON('api/visitas_salida.php', { id });

      if (!data.ok) {
        alert(data.error || 'Error');
        return;
      }
      if ((data.resultado||'') !== 'APROBADO') {
        alert(data.motivo || 'RECHAZADO');
        return;
      }
      await cargarVisitas();
    }

    async function cargarVisitas(){
      const fecha = document.getElementById('f_fecha').value;
      const rut = document.getElementById('f_rut').value.trim();
      const estado = document.getElementById('f_estado').value;

      const params = new URLSearchParams();
      if (fecha) params.set('fecha', fecha);
      if (rut) params.set('rut', rut);
      if (estado) params.set('estado', estado);
      params.set('limit','80');

      const res = await fetch('api/visitas_listar.php?' + params.toString());
      const data = await res.json();

      if (!data.ok) {
        document.getElementById('tabla').innerHTML = `<div class="alert alert-danger mb-0">Error cargando visitas</div>`;
        return;
      }

      const rows = data.items || [];
      let html = `<div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>RUT</th>
              <th>Entrada</th>
              <th>Salida</th>
              <th>Modo</th>
              <th>Patente</th>
              <th>Puesto</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead><tbody>`;

      for (const r of rows){
        const badge = (r.estado === 'DENTRO')
          ? `<span class="badge text-bg-warning">DENTRO</span>`
          : `<span class="badge text-bg-secondary">SALIO</span>`;

        const btn = (r.estado === 'DENTRO')
          ? `<button class="btn btn-sm btn-success" onclick="marcarSalida(${r.id})"><i class="bi bi-box-arrow-right"></i></button>`
          : ``;

        html += `<tr>
          <td>${escapeHtml(r.nombre)}</td>
          <td class="mono">${escapeHtml(r.rut)}</td>
          <td>${escapeHtml(r.fecha_hora_entrada)}</td>
          <td>${escapeHtml(r.fecha_hora_salida || '')}</td>
          <td>${escapeHtml(r.modo)}</td>
          <td>${escapeHtml(r.patente || '')}</td>
          <td>${escapeHtml(r.puesto || '')}</td>
          <td>${badge}</td>
          <td>${btn}</td>
        </tr>`;
      }

      html += `</tbody></table></div>`;
      document.getElementById('tabla').innerHTML = html;
    }

    function limpiarForm(cargar=true){
      document.getElementById('v_nombre').value = '';
      document.getElementById('v_rut').value = '';
      document.getElementById('v_tel').value = '';
      document.getElementById('v_pat').value = '';
      document.getElementById('v_motivo').value = '';
      setModoV('PEATONAL');
      setResultado('');
      if (cargar) cargarVisitas();
    }

    function limpiarFiltros(){
      document.getElementById('f_fecha').value = new Date().toISOString().slice(0,10);
      document.getElementById('f_rut').value = '';
      document.getElementById('f_estado').value = '';
      cargarVisitas();
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('f_fecha').value = new Date().toISOString().slice(0,10);
      cargarVisitas();
    });
  </script>
</body>
</html>