<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN']); 
$activePage = 'config';
$exportToday = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Configuración | Control Acceso</title>

  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <?php $THEME_VER = @filemtime($_SERVER['DOCUMENT_ROOT'].'/Control-Acceso/assets/css/theme.css') ?: time(); ?>
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= $THEME_VER ?>" rel="stylesheet">
  <?php $SETTINGS_VER = @filemtime(__DIR__.'/assets/css/configuracion.css') ?: time(); ?>
  <link href="/Control-Acceso/assets/css/configuracion.css?v=<?= $SETTINGS_VER ?>" rel="stylesheet">
</head>

<body>
  <div class="app">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main">
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <section class="content">
        <div class="settings-heading">
          <h1>Configuración</h1>
          <p class="text-muted mb-0">Administra usuarios, revisa el sistema y descarga tus registros.</p>
        </div>

        <div class="accordion settings-accordion" id="settingsAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header" id="usersHeading">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#settingsUsers" aria-expanded="true" aria-controls="settingsUsers">
                <span class="settings-section-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <span><span class="settings-section-title">Gestión de usuarios</span><span class="settings-section-description">Crear cuentas, asignar roles y administrar accesos</span></span>
              </button>
            </h2>
            <div id="settingsUsers" class="accordion-collapse collapse show" aria-labelledby="usersHeading" data-bs-parent="#settingsAccordion">
              <div class="accordion-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <span class="text-muted small">Usuarios y permisos del sistema</span>
                  <button id="btnUsersRefresh" type="button" class="btn btn-sm btn-outline-light" onclick="loadUsers()"><i class="bi bi-arrow-clockwise me-1"></i>Refrescar usuarios</button>
                </div>
                <div class="panel mb-3">
                  <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label" for="nu_username">Nombre de usuario</label>
                      <input id="nu_username" class="form-control form-control-dark" placeholder="ej: guardia2" autocomplete="off">
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label" for="nu_password">Contraseña</label>
                      <input id="nu_password" type="password" class="form-control form-control-dark" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label" for="nu_rol">Rol</label>
                      <select id="nu_rol" class="form-select form-control-dark">
                        <option value="GUARDIA">GUARDIA</option>
                        <option value="CONTROL_VISITA">CONTROL_VISITA</option>
                        <option value="AUDITOR">AUDITOR</option>
                        <option value="ADMIN">ADMIN</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                      <label class="form-label" for="nu_activo">Activo</label>
                      <select id="nu_activo" class="form-select form-control-dark">
                        <option value="1" selected>Sí</option>
                        <option value="0">No</option>
                      </select>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn btn-primary" onclick="createUser()"><i class="bi bi-person-plus me-1"></i>Crear usuario</button>
                    <div id="nu_msg" class="small text-muted" role="status" aria-live="polite"></div>
                  </div>
                </div>
                <div class="table-wrap" id="usersTable"><div class="text-muted small">Cargando usuarios...</div></div>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="systemHeading">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#settingsSystem" aria-expanded="false" aria-controls="settingsSystem">
                <span class="settings-section-icon"><i class="bi bi-activity" aria-hidden="true"></i></span>
                <span><span class="settings-section-title">Sistema / diagnóstico</span><span class="settings-section-description">Estado de la base de datos y versión del sistema</span></span>
              </button>
            </h2>
            <div id="settingsSystem" class="accordion-collapse collapse" aria-labelledby="systemHeading" data-bs-parent="#settingsAccordion">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-12 col-lg-8">
                    <div class="panel h-100">
                      <div class="d-flex justify-content-between align-items-center gap-3">
                        <div>
                          <div class="small text-muted">Estado de la base de datos</div>
                          <div class="h6 mb-1 mt-1" id="dbStatusText">Sin comprobar</div>
                          <div class="small text-muted" id="dbMeta">Abre esta sección o prueba la conexión.</div>
                        </div>
                        <span id="dbStatusDot" class="status-dot off" aria-hidden="true"></span>
                      </div>
                      <button id="btnDbCheck" type="button" class="btn btn-outline-light mt-3" onclick="checkDb()"><i class="bi bi-activity me-1"></i>Probar conexión</button>
                    </div>
                  </div>
                  <div class="col-12 col-lg-4">
                    <div class="panel h-100">
                      <div class="small text-muted">Versión del sistema</div>
                      <div class="h5 mt-2 mb-0" id="sysVersion">v1.0.0</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="exportsHeading">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#settingsExports" aria-expanded="false" aria-controls="settingsExports">
                <span class="settings-section-icon"><i class="bi bi-download" aria-hidden="true"></i></span>
                <span><span class="settings-section-title">Exportaciones</span><span class="settings-section-description">Descargar CSV completos o filtrar los registros por día</span></span>
              </button>
            </h2>
            <div id="settingsExports" class="accordion-collapse collapse" aria-labelledby="exportsHeading" data-bs-parent="#settingsAccordion">
              <div class="accordion-body">
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
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    function escapeHtml(s){
      return String(s ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;");
    }

    async function postJSON(url, payload){
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify(payload)
      });
      return res.json();
    }

    async function loadUsers(){
      const button = document.getElementById('btnUsersRefresh');
      button.disabled = true;
      try {
        await fetchUsersTable();
      } catch (error) {
        document.getElementById('usersTable').innerHTML = '<div class="alert alert-danger mb-0">No se pudieron cargar los usuarios. Inténtalo nuevamente.</div>';
      } finally {
        button.disabled = false;
      }
    }

    async function fetchUsersTable(){
      const res = await fetch('api/admin_users_list.php', { cache:'no-store' });
      const data = await res.json();

      if (!data.ok){
        document.getElementById('usersTable').innerHTML =
          `<div class="alert alert-danger mb-0">${escapeHtml(data.error || 'Error')}</div>`;
        return;
      }

      const rows = data.items || [];
      let html = `<div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th><th>Username</th><th>Rol</th><th>Activo</th><th>Acciones</th>
            </tr>
          </thead><tbody>`;

      for (const u of rows){
        const isActive = Number(u.activo) === 1;

        html += `<tr>
          <td>${u.id}</td>
          <td class="mono">${escapeHtml(u.username)}</td>
          <td>
            <select class="form-select form-control-dark" onchange="updateRole(${u.id}, this.value)">
              ${['GUARDIA','CONTROL_VISITA','AUDITOR','ADMIN'].map(r => 
                `<option value="${r}" ${u.rol===r?'selected':''}>${r}</option>`
              ).join('')}
            </select>
          </td>
          <td>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" ${isActive?'checked':''}
                onchange="updateActive(${u.id}, this.checked)">
            </div>
          </td>
          <td class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light" onclick="resetPassPrompt(${u.id}, '${escapeHtml(u.username)}')">
              <i class="bi bi-key me-1"></i>Reset pass
            </button>

            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')">
              <i class="bi bi-trash me-1"></i>Eliminar
            </button> 
          </td>
        </tr>`;
      }

      html += `</tbody></table></div>`;
      document.getElementById('usersTable').innerHTML = html;
    }

    async function createUser(){
      const msg = document.getElementById('nu_msg');
      msg.textContent = '';

      const username = document.getElementById('nu_username').value.trim();
      const password = document.getElementById('nu_password').value;
      const rol = document.getElementById('nu_rol').value;
      const activo = Number(document.getElementById('nu_activo').value);

      const data = await postJSON('api/admin_users_create.php', { username, password, rol, activo });

      if (!data.ok){
        msg.textContent = data.error || 'Error';
        return;
      }

      msg.textContent = 'Usuario creado.';
      document.getElementById('nu_username').value = '';
      document.getElementById('nu_password').value = '';
      await loadUsers();
    }

    async function updateRole(id, rol){
      const data = await postJSON('api/admin_users_update.php', { id, rol });
      if (!data.ok) alert(data.error || 'Error actualizando rol');
    }

    async function updateActive(id, checked){
      const activo = checked ? 1 : 0;
      const data = await postJSON('api/admin_users_update.php', { id, activo });
      if (!data.ok) alert(data.error || 'Error actualizando activo');
    }

    async function resetPassPrompt(id, username){
      const newPass = prompt(`Nueva contraseña para ${username} (mín. 8 caracteres):`);
      if (!newPass) return;

      const data = await postJSON('api/admin_users_reset_password.php', { id, password: newPass });
      if (!data.ok) {
        alert(data.error || 'Error reseteando password');
        return;
      }
      alert('Contraseña actualizada.');
    }

    async function checkDb(){
      const button = document.getElementById('btnDbCheck');
      const dot = document.getElementById('dbStatusDot');
      const txt = document.getElementById('dbStatusText');
      const meta = document.getElementById('dbMeta');
      button.disabled = true;
      txt.textContent = 'Comprobando...';
      meta.textContent = '';
      try {
        const res = await fetch('api/admin_db_status.php', { cache:'no-store' });
        if (!res.ok) throw new Error('No se pudo comprobar la conexión.');
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'No se pudo comprobar la conexión.');
        txt.textContent = 'Operativa';
        dot.classList.remove('off');
        meta.textContent = `Latencia: ${data.latency_ms} ms · Última comprobación: ${data.checked_at}`;
      } catch (error) {
        txt.textContent = 'No disponible';
        dot.classList.add('off');
        meta.textContent = error.message || 'No se pudo comprobar la conexión.';
      } finally {
        button.disabled = false;
      }
    }

    async function deleteUser(id, username){
      const ok = confirm(`¿Seguro que deseas eliminar el usuario "${username}"?\nEsta acción NO se puede deshacer.`);
      if (!ok) return;

      const data = await postJSON('api/admin_users_delete.php', { id });

      if (!data.ok){
        alert(data.error || 'Error eliminando usuario');
        return;
      }

      await loadUsers();
    }

    function syncExportControls(){
      const table = document.getElementById('csvTable').value;
      const period = document.getElementById('csvPeriod');
      const supportsDate = ['registros_acceso','visitas'].includes(table);
      const daily = supportsDate && period.value === 'dia';
      document.getElementById('csvPeriodGroup').hidden = !supportsDate;
      period.disabled = !supportsDate;
      document.getElementById('csvDateGroup').hidden = !daily;
      document.getElementById('csvDate').disabled = !daily;
      document.getElementById('csvDate').required = daily;
      document.getElementById('csvCriterionGroup').hidden = !(daily && table === 'visitas');
      document.getElementById('csvCriterion').disabled = !(daily && table === 'visitas');
      const descriptions = {
        registros_acceso: daily ? 'Incluye los movimientos registrados durante el día seleccionado.' : 'Incluye todos los movimientos de acceso.',
        visitas: daily ? 'Elige si quieres las visitas que ingresaron, salieron o tuvieron cualquiera de esas actividades ese día.' : 'Incluye todas las visitas, con sus fechas de ingreso y salida.',
        funcionarios: 'Descarga el listado completo de funcionarios.',
        usuarios: 'Descarga los usuarios, roles y estado de sus cuentas. No incluye contraseñas.'
      };
      document.getElementById('csvHelp').textContent = descriptions[table] || '';
      document.getElementById('csvMessage').textContent = '';
    }

    async function exportCsv(){
      const form = document.getElementById('csvForm');
      const button = document.getElementById('btnExportCsv');
      const message = document.getElementById('csvMessage');
      if (button.disabled || !form.reportValidity()) return;
      const table = document.getElementById('csvTable').value;
      const params = new URLSearchParams({ table });
      if (['registros_acceso','visitas'].includes(table) && document.getElementById('csvPeriod').value === 'dia') {
        params.set('fecha', document.getElementById('csvDate').value);
        if (table === 'visitas') params.set('criterio', document.getElementById('csvCriterion').value);
      }
      const previousLabel = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando CSV...';
      message.textContent = '';
      message.classList.remove('text-danger','text-success');
      try {
        const response = await fetch('api/admin_export_csv.php?' + params.toString(), { cache:'no-store' });
        if (!response.ok) {
          const error = await response.json().catch(() => ({}));
          throw new Error(error.error || 'No se pudo descargar el CSV.');
        }
        if (!(response.headers.get('Content-Type') || '').toLowerCase().startsWith('text/csv')) {
          throw new Error('No se recibió un archivo CSV. Vuelve a iniciar sesión e inténtalo nuevamente.');
        }
        const blob = await response.blob();
        const filename = /filename="([^"]+)"/.exec(response.headers.get('Content-Disposition') || '')?.[1] || table + '.csv';
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.hidden = true;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        const count = Number(response.headers.get('X-Export-Row-Count') || 0);
        message.textContent = count === 0
          ? 'No se encontraron registros para el filtro elegido. Se descargó un CSV con encabezados.'
          : `CSV descargado: ${count.toLocaleString('es-CL')} registros.`;
        message.classList.add('text-success');
      } catch (error) {
        message.textContent = error.message || 'No se pudo descargar el CSV.';
        message.classList.add('text-danger');
      } finally {
        button.disabled = false;
        button.innerHTML = previousLabel;
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadUsers();
      syncExportControls();
      document.getElementById('settingsSystem').addEventListener('shown.bs.collapse', checkDb);
      document.getElementById('csvTable').addEventListener('change', syncExportControls);
      document.getElementById('csvPeriod').addEventListener('change', syncExportControls);
      document.getElementById('csvForm').addEventListener('submit', event => {
        event.preventDefault();
        exportCsv();
      });
    });
  </script>
</body>
</html>
