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
                    <button id="nu_create" type="button" class="btn btn-primary" onclick="createUser()"><i class="bi bi-person-plus me-1"></i>Crear usuario</button>
                    <div id="nu_msg" class="small text-muted" role="status" aria-live="polite"></div>
                  </div>
                </div>
<form id="usersFilters" class="row g-3 align-items-end mb-3"><div class="col-12 col-md-5"><label class="form-label" for="usersSearch">Buscar usuario</label><input id="usersSearch" class="form-control form-control-dark" maxlength="60" autocomplete="off"></div><div class="col-6 col-md-3"><label class="form-label" for="usersRole">Rol</label><select id="usersRole" class="form-select form-control-dark"><option value="">Todos</option><option value="ADMIN">Administrador</option><option value="GUARDIA">Guardia</option><option value="CONTROL_VISITA">Control de visitas</option><option value="AUDITOR">Auditor</option></select></div><div class="col-6 col-md-2"><label class="form-label" for="usersActive">Estado</label><select id="usersActive" class="form-select form-control-dark"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div><div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100">Buscar</button></div></form><div class="table-toolbar mb-3"><p id="usersSummary" class="small text-muted mb-0" role="status" aria-live="polite">Consultando usuarios…</p><label class="small rows-label" for="usersLimit">Filas por página<select id="usersLimit" class="form-select form-control-dark"><option>25</option><option>50</option><option>100</option></select></label></div>
                <div class="table-wrap" id="usersTable"><div class="text-muted small">Cargando usuarios...</div></div><div class="table-pagination mt-3" aria-label="Páginas de usuarios"><button id="usersPrev" type="button" class="btn btn-outline-light" onclick="loadUsers(Tablas.numero('users')-1)" disabled>Anterior</button><label for="usersPage" class="small">Página</label><input id="usersPage" class="form-control form-control-dark page-number" type="number" min="1" value="1" disabled><button id="usersNext" type="button" class="btn btn-outline-light" onclick="loadUsers(Tablas.numero('users')+1)" disabled>Siguiente</button></div>
  </div>
</div>
<?php include __DIR__.'/../partials/config-footer.php'; ?>
