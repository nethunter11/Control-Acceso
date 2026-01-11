<?php
require_once __DIR__ . '/../app/auth.php';
$u = auth_user();
?>

<header class="topbar">
  <button class="btn btn-sm btn-outline-light" id="btnToggle">
    <i class="bi bi-list"></i>
  </button>

  <?php if (($activePage ?? '') === 'registros'): ?>
    <div class="topbar-search">
      <i class="bi bi-search"></i>
      <input id="quickSearch" placeholder="Buscar por RUT en registros..." />
    </div>
  <?php else: ?>
    <div class="topbar-search" style="visibility:hidden;">
      <i class="bi bi-search"></i>
      <input placeholder="" />
    </div>
  <?php endif; ?>

  <div class="topbar-right">
    <a href="/Control-Acceso/auth/logout.php" class="logout-btn">
      <span class="logout-ico"><i class="bi bi-box-arrow-right"></i></span>
      <span class="logout-text">LOGOUT</span>
    </a>

    <div class="userchip">
      <i class="bi bi-person-circle"></i>
      <span><?= htmlspecialchars($u['username'] ?? 'usuario') ?></span>
    </div>
  </div>
</header>

