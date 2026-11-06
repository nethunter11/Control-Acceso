<?php
require_once __DIR__ . '/../app/auth.php';
$u = auth_user();
$roleLabel = ['ADMIN'=>'Administrador','GUARDIA'=>'Guardia','CONTROL_VISITA'=>'Control de visitas','AUDITOR'=>'Auditor'][$u['rol'] ?? ''] ?? 'Usuario';
?>
<header class="topbar">
  <button class="btn-icon" id="btnToggle" type="button" aria-label="Contraer menú" title="Contraer menú" aria-controls="sidebar" aria-expanded="true"><i class="bi bi-list" aria-hidden="true"></i></button>
  <div class="topbar-context"><span>Control de acceso</span><time id="topbarDate"></time></div>
  <?php if (($activePage ?? '') === 'registros'): ?>
    <div class="topbar-search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input id="quickSearch" aria-label="Buscar RUT en registros" placeholder="Buscar RUT y pulsar Enter" autocomplete="off" spellcheck="false">
    </div>
  <?php endif; ?>
  <div class="topbar-right">
    <button id="btnContrast" type="button" class="btn-icon contrast-toggle" aria-label="Activar contraste reforzado" aria-pressed="false" title="Activar contraste reforzado"><i class="bi bi-circle-half" aria-hidden="true"></i><span>Contraste</span></button>
    <div class="userchip"><span class="user-avatar"><i class="bi bi-person" aria-hidden="true"></i></span><span class="user-details"><span class="user-name"><?= htmlspecialchars($u['username'] ?? 'usuario', ENT_QUOTES, 'UTF-8') ?></span><span class="user-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span></span></div>
    <a href="/Control-Acceso/auth/logout.php" class="logout-btn" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Salir</span></a>
  </div>
</header>
