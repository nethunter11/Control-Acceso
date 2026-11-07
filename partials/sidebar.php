<?php
$activePage = $activePage ?? '';
$rol = strtoupper($_SESSION['user']['rol'] ?? '');
$navigation = [
  ['validacion', 'index.php', 'shield-check', 'Validación', ['ADMIN','GUARDIA']],
  ['visitas', 'visitas.php', 'people', 'Visitas', ['ADMIN','CONTROL_VISITA']],
  ['funcionarios', 'funcionarios.php', 'person-badge', 'Funcionarios', ['ADMIN']],
  ['registros', 'registros.php', 'journal-text', 'Registros', ['ADMIN','GUARDIA','AUDITOR']],
  ['reportes', 'reportes.php', 'bar-chart-line', 'Reportes', ['ADMIN','AUDITOR']],
];
$isConfig = str_starts_with($activePage, 'config');
?>
<a class="skip-link" href="#mainContent">Ir al contenido</a>
<aside class="sidebar" id="sidebar" aria-label="Menú principal">
  <div class="brand">
    <img src="/Control-Acceso/img/logo.png" alt="Escudo institucional FACH">
    <div class="brand-text">
      <div class="brand-title">Control de Acceso</div>
      <div class="brand-sub">Fuerza Aérea de Chile</div>
    </div>
    <button id="sidebarClose" type="button" class="sidebar-close btn-icon" aria-label="Cerrar menú"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
  </div>
  <div class="sidebar-group-label">Panel operativo</div>
  <nav class="nav flex-column" aria-label="Secciones">
    <?php foreach ($navigation as [$key, $path, $icon, $label, $roles]): ?>
      <?php if (in_array($rol, $roles, true)): ?>
        <a class="nav-link <?= $activePage === $key ? 'active' : '' ?>" href="/Control-Acceso/<?= $path ?>" title="<?= $label ?>" <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
          <i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><span class="nav-label"><?= $label ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($rol === 'ADMIN'): ?>
      <div class="nav-group">
        <button id="configToggle" type="button" class="nav-link config-toggle <?= $isConfig ? 'active' : '' ?>" aria-controls="configSubmenu" aria-expanded="<?= $isConfig ? 'true' : 'false' ?>" title="Configuración">
          <i class="bi bi-gear" aria-hidden="true"></i><span class="nav-label">Configuración</span><i class="bi bi-chevron-down config-caret" aria-hidden="true"></i>
        </button>
        <div id="configSubmenu" class="config-submenu" <?= $isConfig ? '' : 'hidden' ?>>
          <?php foreach ([['gestion','people','Gestión'],['sistema','activity','Sistema'],['exportaciones','download','Exportaciones']] as [$section,$icon,$label]): ?>
            <a class="subnav-link <?= $activePage === 'config_'.$section ? 'active' : '' ?>" href="/Control-Acceso/configuracion/<?= $section ?>.php" <?= $activePage === 'config_'.$section ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="service-state" role="status" aria-live="polite">
      <span id="svcDot" class="status-dot off" aria-hidden="true"></span>
      <div class="service-copy"><div class="service-label">Estado del sistema</div><div id="svcText">Comprobando servicio…</div></div>
    </div>
    <div class="sidebar-caption">Registro y control institucional</div>
  </div>
</aside>
<button id="sidebarBackdrop" class="sidebar-backdrop" type="button" tabindex="-1" aria-label="Cerrar menú" hidden></button>
