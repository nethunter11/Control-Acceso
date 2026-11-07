    const roleLabels = {"GUARDIA":"Guardia","CONTROL_VISITA":"Control de visitas","AUDITOR":"Auditor","ADMIN":"Administrador"};

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
      const res = await fetch('/Control-Acceso/api/admin_users_list.php', { cache:'no-store' });
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
              <th>ID</th><th>Usuario</th><th>Rol</th><th>Activo</th><th>Acciones</th>
            </tr>
          </thead><tbody>`;

      for (const u of rows){
        const isActive = Number(u.activo) === 1;
        const isSelf = Number(u.id) === Number(data.current_user_id);

        html += `<tr>
          <td>${u.id}</td>
          <td class="mono">${escapeHtml(u.username)}</td>
          <td>
            <select class="form-select form-control-dark" aria-label="Rol de ${escapeHtml(u.username)}" ${isSelf ? 'disabled' : ''} onchange="updateRole(${u.id}, this.value)">
              ${['GUARDIA','CONTROL_VISITA','AUDITOR','ADMIN'].map(r =>
                `<option value="${r}" ${u.rol===r?'selected':''}>${roleLabels[r]}</option>`
              ).join('')}
            </select>
          </td>
          <td>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" ${isActive?'checked':''}
                aria-label="Cuenta activa de ${escapeHtml(u.username)}" ${isSelf ? 'disabled' : ''} onchange="updateActive(${u.id}, this.checked)">
            </div>
          </td>
          <td><div class="table-actions">
            <button class="btn btn-sm btn-outline-light" onclick="resetPassPrompt(${u.id}, this.dataset.username)" data-username="${escapeHtml(u.username)}">
              <i class="bi bi-key me-1"></i>Restablecer
            </button>

            <button class="btn btn-sm btn-outline-danger" ${isSelf ? 'disabled' : ''} onclick="deleteUser(${u.id}, this.dataset.username)" data-username="${escapeHtml(u.username)}">
              <i class="bi bi-trash me-1"></i>Eliminar
            </button></div>
          </td>
        </tr>`;
      }

      if (rows.length === 0) html += "<tr><td colspan=\"5\"><div class=\"table-empty\"><i class=\"bi bi-inbox\" aria-hidden=\"true\"></i>No se encontraron usuarios.</div></td></tr>";
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

      const data = await postJSON('/Control-Acceso/api/admin_users_create.php', { username, password, rol, activo });

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
      const data = await postJSON('/Control-Acceso/api/admin_users_update.php', { id, rol });
      if (!data.ok) alert(data.error || 'Error actualizando rol');
      await loadUsers();
    }

    async function updateActive(id, checked){
      const activo = checked ? 1 : 0;
      const data = await postJSON('/Control-Acceso/api/admin_users_update.php', { id, activo });
      if (!data.ok) alert(data.error || 'Error actualizando activo');
      await loadUsers();
    }

    async function resetPassPrompt(id, username){
      const newPass = prompt(`Nueva contraseña para ${username} (mín. 8 caracteres):`);
      if (!newPass) return;

      const data = await postJSON('/Control-Acceso/api/admin_users_reset_password.php', { id, password: newPass });
      if (!data.ok) {
        alert(data.error || 'Error reseteando password');
        return;
      }
      alert('Contraseña actualizada.');
    }

    async function deleteUser(id, username){
      const ok = confirm(`¿Seguro que deseas eliminar el usuario "${username}"?\nEsta acción NO se puede deshacer.`);
      if (!ok) return;

      const data = await postJSON('/Control-Acceso/api/admin_users_delete.php', { id });

      if (!data.ok){
        alert(data.error || 'Error eliminando usuario');
        return;
      }

      await loadUsers();
    }


document.addEventListener("DOMContentLoaded", loadUsers);
