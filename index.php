<?php $activePage = 'validacion'; ?>
<?php
require_once __DIR__ . '/app/auth.php';
require_login();
$activePage = 'validacion';
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Control de Acceso</title>

  <!-- Bootstrap local. -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Tu theme -->
  <link href="assets/css/theme.css" rel="stylesheet">
</head>

<body>
  <div class="app">
    <!-- SIDEBAR -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- MAIN -->
    <main class="main">
      <!-- TOPBAR -->
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <!-- CONTENT -->
      <section class="content">
        <div class="row g-3">
          <!-- CARD: Validar -->
          <div class="col-12 col-xl-8 col-xxl-7">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <div class="h6 mb-0">VALIDACIÓN DE ACCESO</div>
                    <div class="text-muted small">RUT y estado funcionario</div>
                  </div>

                  <div class="btn-group btn-group-sm" role="group" aria-label="Modo de ingreso">
                    <button type="button" id="btnModoPeatonal" class="btn btn-outline-light active" onclick="setModo('PEATONAL')">
                      <i class="bi bi-person-walking me-1"></i>Peatonal
                    </button>
                    <button type="button" id="btnModoVehiculo" class="btn btn-outline-light" onclick="setModo('VEHICULO')">
                      <i class="bi bi-car-front me-1"></i>Vehícular
                    </button>
                  </div>
                </div>
              </div>

              <div class="card-body">
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label">RUT</label>
                    <input class="form-control form-control-dark" id="rut" placeholder="12345678-5" />
                  </div>

                  <div class="col-12">
                    <label class="form-label">Puesto</label>
                    <input class="form-control form-control-dark" id="puesto" value="Portón 1" />
                  </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-3">
                  <button class="btn btn-outline-light" onclick="soloConsultar()">
                    <i class="bi bi-search me-1"></i>Consultar
                  </button>
                  <button class="btn btn-success" onclick="registrar('ENTRADA')">
                    <i class="bi-box-arrow-in-right"></i>Entrada
                  </button>
                  <button class="btn btn-warning" onclick="registrar('SALIDA')">
                    <i class="bi bi-box-arrow-right me-1"></i>Salida
                  </button>
                  <button class="btn btn-outline-secondary" onclick="limpiarCampos()">
                    <i class="bi bi-eraser me-1"></i>Limpiar
                  </button>

                  </button>
                </div>

                <div class="mt-3" id="resultado"></div>
              </div>
            </div>
          </div>

          <!-- CARD: Filtros + tabla -->
        </div>
      </section>
    </main>
  </div>

  <script src="assets/js/ui.js"></script>

  <script>
  async function postJSON(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return res.json();
  }

  function setResultado(html) {
    document.getElementById('resultado').innerHTML = html;
  }
  function rutValue() { return document.getElementById('rut').value.trim(); }
  function puestoValue() { return document.getElementById('puesto').value.trim() || 'Portón 1'; }

  let modoIngreso = 'PEATONAL';

function setModo(modo){
  modoIngreso = modo;

  const b1 = document.getElementById('btnModoPeatonal');
  const b2 = document.getElementById('btnModoVehiculo');

  if (modo === 'PEATONAL') {
    b1.classList.add('active');
    b2.classList.remove('active');
  } else {
    b2.classList.add('active');
    b1.classList.remove('active');
  }
}

  async function soloConsultar() {
    try {
      const rut = rutValue();
      if (!rut) {
        setResultado(`<div class="alert alert-danger mb-0">Ingrese un RUT.</div>`);
        return;
      }

      const data = await postJSON('api/validar_rut.php', { rut });

      if (!data.ok) {
        setResultado(`<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error')}</div>`);
        return;
      }

      if (!data.existe) {
        setResultado(`<div class="alert alert-warning mb-0">NO REGISTRADO: <b>${escapeHtml(data.rut)}</b></div>`);
        return;
      }

      const f = data.funcionario;

      const badge = data.activo
        ? `<span class="func-badge ok">ACTIVO</span>`
        : `<span class="func-badge bad">INACTIVO</span>`;

      setResultado(`
        <div class="panel func-card ${data.activo ? 'is-ok' : 'is-bad'}">
          <div class="func-head">
            <div class="func-left">
              <img class="func-avatar" src="img/perfil.png" alt="Escudo">
              <div>
                <div class="func-title">${escapeHtml(f.nombres)}</div>
                <div class="func-subtitle">Información de funcionario</div>
              </div>
            </div>
            ${badge}
          </div>

          <div class="func-grid">

            <div class="func-item">
              <div class="func-top">
                <i class="bi bi-person-vcard"></i>
                <div class="func-label">RUT</div>
              </div>
              <div class="func-value mono">${escapeHtml(f.rut)}</div>
            </div>

            <div class="func-item">
              <div class="func-top">
                <i class="bi bi-building"></i>
                <div class="func-label">Unidad</div>
              </div>
              <div class="func-value">${escapeHtml(f.unidad || '-')}</div>
            </div>

            <div class="func-item">
              <div class="func-top">
                <i class="bi bi-award"></i>
                <div class="func-label">Grado</div>
              </div>
              <div class="func-value">${escapeHtml(f.grado || '-')}</div>
            </div>

            <div class="func-item">
              <div class="func-top">
                <i class="bi bi-geo-alt"></i>
                <div class="func-label">Puesto</div>
              </div>
              <div class="func-value">${escapeHtml(document.getElementById('puesto')?.value || 'Portón 1')}</div>
            </div>
          </div>
      `);


    } catch (err) {
      console.error(err);
      setResultado(`<div class="alert alert-danger mb-0">Error al consultar (ver consola F12).</div>`);
    }
  }



  async function registrar(tipo) {
    const rut = rutValue();
    const puesto = puestoValue();
    if (!rut) return setResultado(`<div class="alert alert-danger mb-0">Ingrese un RUT.</div>`);

    const data = await postJSON('api/registrar_evento.php', { rut, tipo, puesto, modo: modoIngreso });
    if (!data.ok) return setResultado(`<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error')}</div>`);

    const ok = data.resultado === 'APROBADO';
    const alert = ok ? 'alert-success' : 'alert-danger';
    const motivo = data.motivo ? `<div class="small mt-1">Motivo: <b>${escapeHtml(data.motivo)}</b></div>` : '';

    setResultado(`
      <div class="alert ${alert} mb-0">
        <div class="d-flex justify-content-between">
          <div><b>${escapeHtml(data.tipo)}</b> | Puesto: <b>${escapeHtml(data.puesto)}</b></div>
          <div><b>${escapeHtml(data.resultado)}</b></div>
        </div>
        <div class="small">RUT: <b>${escapeHtml(data.rut)}</b></div>
        ${data.funcionario ? `<div class="small">Nombre: <b>${escapeHtml(data.funcionario.nombres)}</b></div>` : ''}
        ${motivo}
      </div>
    `);

    cargarUltimos();
  }

  async function cargarUltimos() {
    const desde = document.getElementById('f_desde')?.value || '';
    const hasta = document.getElementById('f_hasta')?.value || '';
    const rut = document.getElementById('f_rut')?.value.trim() || '';
    const resultado = document.getElementById('f_resultado')?.value || '';

    const params = new URLSearchParams();
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);
    if (rut) params.set('rut', rut);
    if (resultado) params.set('resultado', resultado);
    params.set('limit', '50');

    const res = await fetch('api/ultimos.php?' + params.toString());
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
            <th>Fecha/Hora</th><th>RUT</th><th>Tipo</th><th>Modo</th><th>Puesto</th><th>Resultado</th><th>Motivo</th>
          </tr>
        </thead><tbody>`;

    for (const r of rows) {
      const badge = (r.resultado === 'APROBADO')
        ? `<span class="badge text-bg-success">APROBADO</span>`
        : `<span class="badge text-bg-danger">RECHAZADO</span>`;

      html += `<tr>
        <td>${escapeHtml(r.fecha_hora)}</td>
        <td>${escapeHtml(r.rut)}</td>
        <td>${escapeHtml(r.tipo)}</td>
        <td>${escapeHtml(r.modo || '')}</td>
        <td>${escapeHtml(r.puesto || '')}</td>
        <td>${badge}</td>
        <td class="text-muted">${escapeHtml(r.motivo || '')}</td>
      </tr>`;
    }
    html += `</tbody></table></div>`;

    document.getElementById('tabla').innerHTML = html;
  }

  function limpiar() { document.getElementById('rut').value = ''; setResultado(''); }
  function limpiarFiltros() {
    document.getElementById('f_desde').value = '';
    document.getElementById('f_hasta').value = '';
    document.getElementById('f_rut').value = '';
    document.getElementById('f_resultado').value = '';
    cargarUltimos();
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // Quick search (topbar) -> rellena filtro rut y aplica
  document.addEventListener('DOMContentLoaded', () => {
    cargarUltimos();
    const q = document.getElementById('quickSearch');
    q?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('f_rut').value = q.value.trim();
        cargarUltimos();
      }
    });
  });
  </script>
    <script src="/Control-Acceso/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/Control-Acceso/assets/js/ui.js"></script>

</body>
</html>