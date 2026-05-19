<?php
$activePage = $activePage ?? '';

// Rol del usuario (desde sesión)
$rol = strtoupper($_SESSION['user']['rol'] ?? '');
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <img src="img/logo.png" alt="Logo institucional">
    <div class="brand-text">
      <div class="brand-title">CONTROL ACCESO FACH</div>
      <div class="brand-sub">Base / Unidad</div>
    </div>
  </div>

  <nav class="nav flex-column px-2">
    <?php if (in_array($rol, ['ADMIN','GUARDIA'], true)): ?>
    <a class="nav-link <?= ($activePage==='validacion' ? 'active' : '') ?>" href="index.php">
      <i class="bi bi-shield-check me-2"></i>Validación
    </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['ADMIN','CONTROL_VISITA'], true)): ?>
    <a class="nav-link <?= ($activePage==='visitas' ? 'active' : '') ?>" href="visitas.php">
      <i class="bi bi-people me-2"></i>Visitas
    </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['ADMIN','GUARDIA','AUDITOR'], true)): ?>
    <a class="nav-link <?= ($activePage==='registros' ? 'active' : '') ?>" href="registros.php">
      <i class="bi bi-journal-text me-2"></i>Registros
    </a>
    <?php endif; ?>

    <?php if (in_array($rol, ['ADMIN','AUDITOR'], true)): ?>
    <a class="nav-link <?= (($activePage ?? '')==='reportes' ? 'active' : '') ?>" href="/Control-Acceso/reportes.php">
      <i class="bi bi-clipboard-data me-2"></i><span>Reportes</span>
    </a>
    <?php endif; ?>

    <?php if ($rol === 'ADMIN'): ?>
    <a class="nav-link <?= ($activePage==='config' ? 'active' : '') ?>" href="configuraciones.php">
      <i class="bi bi-gear me-2"></i>Configuración
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <span id="svcDot" class="status-dot"></span>
      <div>
        <div class="small text-muted">Estado</div>
        <div id="svcText" class="small">Servicio activo</div>
      </div>
    </div>
  </div>
</aside>

