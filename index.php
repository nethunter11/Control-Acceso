<?php $activePage = 'validacion'; ?>
<?php
require_once __DIR__ . '/app/auth.php';
require_login();
require_roles(['ADMIN','GUARDIA']);   // ✅ Solo ADMIN y GUARDIA
$activePage = 'validacion';
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Control de Acceso</title>
  
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">

  <?php $THEME_VER = @filemtime($_SERVER['DOCUMENT_ROOT'].'/Control-Acceso/assets/css/theme.css') ?: time(); ?>
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= $THEME_VER ?>" rel="stylesheet">
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
          <div class="col-12">
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
                  <button id="btnConsultar" class="btn btn-outline-light" onclick="soloConsultar()">
                    <i class="bi bi-search me-1"></i>Consultar
                  </button>
                  <button id="btnEntrada" class="btn btn-success" onclick="registrar('ENTRADA')">
                    <i class="bi-box-arrow-in-right"></i>Entrada
                  </button>
                  <button id="btnSalida" class="btn btn-warning" onclick="registrar('SALIDA')">
                    <i class="bi bi-box-arrow-right me-1"></i>Salida
                  </button>
                  <button class="btn btn-outline-secondary" onclick="limpiar()">
                    <i class="bi bi-eraser me-1"></i>Limpiar
                  </button>

                  </button>
                </div>

                <div class="mt-3" id="resultado"></div>
              </div>
            </div>
          </div>
                    <!-- CARD: Personal dentro -->
          <div class="col-12">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <div class="h6 mb-0">Personal dentro</div>
                      <span class="badge badge-soft" id="cntDentro">0</span>
                    </div>
                    <div class="text-muted small">Últimas 24 Hrs</div>
                  </div>
                  <button class="btn btn-sm btn-outline-light" onclick="cargarDentro()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refrescar
                  </button>
                </div>
              </div>

              <div class="card-body">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-6">
                    <label class="form-label">Fecha</label>
                    <div class="form-control form-control-dark d-flex align-items-center" style="pointer-events:none;">
                      <i class="bi bi-calendar3 me-2"></i>
                      <span id="d_fecha_text">Hoy</span>
                    </div>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">RUT (opcional)</label>
                    <input id="d_rut" class="form-control form-control-dark" placeholder="12345678-5">
                  </div>
                </div>

                <div class="mt-3 table-wrap" id="tablaDentro"></div>
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

  let resultadoAutoClearTimer = null;

  function cancelarAutoClearResultado() {
    if (resultadoAutoClearTimer) {
      clearTimeout(resultadoAutoClearTimer);
      resultadoAutoClearTimer = null;
    }
  }

  function autoClearResultado(ms = 6000) {
    cancelarAutoClearResultado();
    resultadoAutoClearTimer = setTimeout(() => {
      setResultado('');
      resultadoAutoClearTimer = null;
    }, ms);
  }

  function setButtonsDisabled(ids, disabled) {
    for (const id of ids) {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = disabled;
    }
  }

  function setButtonLoading(id, loading, label = 'Procesando...') {
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
    setButtonLoading('btnConsultar', true, 'Consultando...');
    setButtonsDisabled(['btnEntrada', 'btnSalida'], true);

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
    } finally {
      setButtonLoading('btnConsultar', false);
      setButtonsDisabled(['btnEntrada', 'btnSalida'], false);
    }
  }



  async function registrar(tipo) {
    const rut = rutValue();
    const puesto = puestoValue();
    if (!rut) return setResultado(`<div class="alert alert-danger mb-0">Ingrese un RUT.</div>`);

    if (tipo === 'SALIDA' && !confirm(`¿Registrar SALIDA para el RUT ${rut}?`)) {
      return;
    }

    const btnId = (tipo === 'ENTRADA') ? 'btnEntrada' : 'btnSalida';
    setButtonLoading(btnId, true, 'Registrando...');
    setButtonsDisabled(['btnConsultar', tipo === 'ENTRADA' ? 'btnSalida' : 'btnEntrada'], true);

    try {
      const data = await postJSON('api/registrar_evento.php', { rut, tipo, puesto, modo: modoIngreso });
      if (!data.ok) return setResultado(`<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error')}</div>`);

      const ok = (data.resultado === 'APROBADO');
      const cls = ok ? 'ok' : 'bad';
      const icon = ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
      const badgeText = ok ? 'APROBADO' : 'RECHAZADO';

      // Fallbacks (evitan undefined)
      const rutShow = data.rut || rut;
      const puestoShow = data.puesto || puesto || 'Portón 1';
      const modoShow = (data.modo === 'VEHICULO') ? 'Vehicular' : 'Peatonal';
      const nombreShow = data.funcionario?.nombres || '(sin nombre)';
      const horaShow = (data.fecha_hora || '').slice(11, 19) || '—';

      setResultado(`
        <div class="event-card ${cls}">
          <div class="event-head">
            <div class="event-left">
              <div class="event-icon"><i class="bi ${icon}"></i></div>
              <div>
                <div class="event-title">${escapeHtml(data.tipo)} ${ok ? 'APROBADA' : 'RECHAZADA'}</div>
                <div class="event-subtitle">${escapeHtml(puestoShow)} · ${escapeHtml(modoShow)} · ${escapeHtml(horaShow)}</div>
              </div>
            </div>
            <div class="event-badge ${cls}">${badgeText}</div>
          </div>

          <div class="event-grid">
            <div class="event-item">
              <div class="event-label">RUT</div>
              <div class="event-value mono">${escapeHtml(rutShow)}</div>
            </div>

            <div class="event-item">
              <div class="event-label">Nombre</div>
              <div class="event-value">${escapeHtml(nombreShow)}</div>
            </div>

            <div class="event-item">
              <div class="event-label">Puesto</div>
              <div class="event-value">${escapeHtml(puestoShow)}</div>
            </div>

            <div class="event-item">
              <div class="event-label">Modo</div>
              <div class="event-value">${escapeHtml(modoShow)}</div>
            </div>
          </div>

          ${data.motivo ? `
            <div class="event-motivo">
              <span class="k"><i class="bi bi-exclamation-triangle me-1"></i>Motivo:</span>
              <b>${escapeHtml(data.motivo)}</b>
            </div>
          ` : ``}
        </div>
      `);

      // Refresca "Personal dentro" inmediatamente (ENTRADA agrega, SALIDA quita)
      if (typeof cargarDentro === 'function') await cargarDentro();
      autoClearResultado();
    } catch (err) {
      console.error(err);
      setResultado(`<div class="alert alert-danger mb-0">Error al registrar (ver consola F12).</div>`);
    } finally {
      setButtonLoading(btnId, false);
      setButtonsDisabled(['btnConsultar', tipo === 'ENTRADA' ? 'btnSalida' : 'btnEntrada'], false);
    }
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

  function limpiar() {
    cancelarAutoClearResultado();
    document.getElementById('rut').value = '';
    setResultado('');
  }
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

    const f = document.getElementById('d_fecha');
    if (f && !f.value) f.value = new Date().toISOString().slice(0,10);
    cargarDentro();

    // Enter en el input RUT -> registrar ENTRADA
    const rutInput = document.getElementById('rut');
    rutInput?.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        await registrar('ENTRADA');
      }
    });
    
    const q = document.getElementById('quickSearch');
    q?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        document.getElementById('rut').value = q.value.trim();
        soloConsultar();
      }
    });
  });

  async function cargarDentro(){
    document.getElementById('d_fecha_text').textContent = new Date().toISOString().slice(0,10);
    const fecha = document.getElementById('d_fecha')?.value || new Date().toISOString().slice(0,10);
    const rut = document.getElementById('d_rut')?.value.trim() || '';

    const params = new URLSearchParams();
    params.set('fecha', fecha);
    // el filtro rut lo hacemos client-side (más simple)
    const res = await fetch('api/personal_dentro.php?' + params.toString(), { cache:'no-store' });
    const data = await res.json();

    if (!data.ok){
      document.getElementById('tablaDentro').innerHTML =
        `<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error cargando')}</div>`;
      return;
    }

    let rows = data.items || [];
    if (rut) rows = rows.filter(x => (x.rut || '').includes(rut));

    const badge = document.getElementById('cntDentro');
    if (badge) badge.textContent = rows.length;    

    let html = `<div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>RUT</th>
            <th>Unidad</th>
            <th>Grado</th>
            <th>Modo</th>
            <th>Puesto</th>
            <th>Último</th>
          </tr>
        </thead><tbody>`;

    for (const r of rows){
      html += `<tr>
        <td>${escapeHtml(r.nombres || '')}</td>
        <td class="mono">${escapeHtml(r.rut || '')}</td>
        <td>${escapeHtml(r.unidad || '')}</td>
        <td>${escapeHtml(r.grado || '')}</td>
        <td>${escapeHtml(r.modo || '')}</td>
        <td>${escapeHtml(r.puesto || '')}</td>
        <td>${escapeHtml(r.ultima_hora || '')}</td>
      </tr>`;
    }

    html += `</tbody></table></div>`;
    document.getElementById('tablaDentro').innerHTML = html;
  }

  function limpiarDentro(){
    document.getElementById('d_fecha').value = new Date().toISOString().slice(0,10);
    document.getElementById('d_rut').value = '';
    cargarDentro();
  }

  </script>
    <script src="/Control-Acceso/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/Control-Acceso/assets/js/ui.js"></script>

</body>
</html>
