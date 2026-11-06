<?php
$activePage='config_gestion';
include __DIR__.'/../partials/config-header.php';
?>
<div class="card card-dark settings-page">
  <div class="card-header card-header-dark"><div class="settings-intro"><i class="bi bi-people" aria-hidden="true"></i><div><h2 class="h6 mb-0">Cuentas y permisos</h2><div class="text-muted small">Crea usuarios y administra sus accesos al sistema.</div></div></div></div>
  <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                  <span class="text-muted small">Usuarios y permisos del sistema</span>
                  <button id="btnUsersRefresh" type="button" class="btn btn-sm btn-outline-light" onclick="loadUsers()"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar usuarios</button>
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
                        <option value="GUARDIA">Guardia</option>
                        <option value="CONTROL_VISITA">Control de visitas</option>
                        <option value="AUDITOR">Auditor</option>
                        <option value="ADMIN">Administrador</option>
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
<?php include __DIR__.'/../partials/config-footer.php'; ?>
