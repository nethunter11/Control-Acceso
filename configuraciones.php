<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN']); 
$activePage = 'config';
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
</head>

<body>
  <div class="app">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main">
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <section class="content">
        <div class="row g-3">

          <!-- Usuarios -->
          <div class="col-12 col-xl-8">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="h6 mb-0">GESTIÓN DE USUARIOS</div>
                    <div class="text-muted small">Crear, roles, activar/desactivar, reset password</div>
                  </div>
                  <button class="btn btn-sm btn-outline-light" onclick="loadUsers()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refrescar
                  </button>
                </div>
              </div>

              <div class="card-body">
                <!-- Crear usuario -->
                <div class="panel mb-3">
                  <div class="row g-2">
                    <div class="col-12 col-md-3">
                      <label class="form-label">Username</label>
                      <input id="nu_username" class="form-control form-control-dark" placeholder="ej: guardia2">
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label">Password</label>
                      <input id="nu_password" type="password" class="form-control form-control-dark" placeholder="mín. 8">
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label">Rol</label>
                      <select id="nu_rol" class="form-select form-control-dark">
                        <option value="GUARDIA">GUARDIA</option>
                        <option value="CONTROL_VISITA">CONTROL_VISITA</option>
                        <option value="AUDITOR">AUDITOR</option>
                        <option value="ADMIN">ADMIN</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-3">
                      <label class="form-label">Activo</label>
                      <select id="nu_activo" class="form-select form-control-dark">
                        <option value="1" selected>SI</option>
                        <option value="0">NO</option>
                      </select>
                    </div>
                  </div>

                  <div class="d-flex gap-2 flex-wrap mt-2">
                    <button class="btn btn-primary" onclick="createUser()">
                      <i class="bi bi-person-plus me-1"></i>Crear usuario
                    </button>
                    <div id="nu_msg" class="small text-muted"></div>
                  </div>
                </div>

                <!-- Tabla usuarios -->
                <div class="table-wrap" id="usersTable"></div>
              </div>
            </div>
          </div>

          <!-- Sistema / Diagnóstico -->
          <div class="col-12 col-xl-4">
            <div class="card card-dark">
              <div class="card-header card-header-dark">
                <div class="h6 mb-0">SISTEMA / DIAGNÓSTICO</div>
                <div class="text-muted small">Estado DB, versión, export CSV</div>
              </div>

              <div class="card-body">
                <div class="panel mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <div class="small text-muted">Estado de Base de Datos</div>
                      <div class="h6 mb-0" id="dbStatusText">—</div>
                      <div class="small text-muted" id="dbMeta">—</div>
                    </div>
                    <span id="dbStatusDot" class="status-dot"></span>
                  </div>
                  <div class="mt-2">
                    <button class="btn btn-sm btn-outline-light" onclick="checkDb()">
                      <i class="bi bi-activity me-1"></i>Probar conexión
                    </button>
                  </div>
                </div>

                <div class="panel mb-3">
                  <div class="small text-muted">Versión del sistema</div>
                  <div class="h6 mb-0" id="sysVersion">v1.0.0</div>
                </div>

                <div class="panel">
                  <div class="small text-muted mb-2">Exportar CSV</div>

                  <select id="csvTable" class="form-select form-control-dark mb-2">
                    <option value="usuarios">usuarios</option>
                    <option value="funcionarios">funcionarios</option>
                    <option value="registros_acceso">registros_acceso</option>
                    <option value="visitas">visitas</option>
                  </select>

                  <button class="btn btn-outline-light w-100" onclick="exportCsv()">
                    <i class="bi bi-download me-1"></i>Descargar CSV
                  </button>
                </div>

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
      const res = await fetch('api/admin_db_status.php', { cache:'no-store' });
      const data = await res.json();

      const dot = document.getElementById('dbStatusDot');
      const txt = document.getElementById('dbStatusText');
      const meta = document.getElementById('dbMeta');

      if (data.ok){
        txt.textContent = 'Operativa';
        dot.classList.remove('off');
        if (meta) meta.textContent = `Latencia: ${data.latency_ms} ms · Último check: ${data.checked_at}`;
      } else {
        txt.textContent = 'Inactiva';
        dot.classList.add('off');
        if (meta) meta.textContent = `${data.error || 'Error'} · Último check: ${data.checked_at || ''}`;
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

    function exportCsv(){
      const t = document.getElementById('csvTable').value;
      // descarga directa
      window.location.href = `api/admin_export_csv.php?table=${encodeURIComponent(t)}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadUsers();
      checkDb();
    });
  </script>
</body>
</html>