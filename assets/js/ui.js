(() => {
  'use strict';
  const root = document.documentElement;
  const contrastKey = 'control-acceso-contraste';
  const menuKey = 'control-acceso-menu';
  const readPreference = key => { try { return localStorage.getItem(key); } catch { return null; } };
  const savePreference = (key, value) => { try { localStorage.setItem(key, value); } catch { /* El diseño funciona sin almacenamiento local. */ } };
  function applyContrast(high, save = false) {
    root.dataset.contrast = high ? 'high' : 'normal';
    const button = document.getElementById('btnContrast');
    if (button) {
      button.setAttribute('aria-pressed', String(high));
      const label = high ? 'Desactivar contraste reforzado' : 'Activar contraste reforzado';
      button.setAttribute('aria-label', label);
      button.title = label;
    }
    if (save) savePreference(contrastKey, high ? 'high' : 'normal');
    document.dispatchEvent(new CustomEvent('appearancechange'));
  }
  applyContrast(readPreference(contrastKey) === 'high');

  async function checkDbHealth() {
    const dot = document.getElementById('svcDot');
    const text = document.getElementById('svcText');
    if (!dot || !text) return;
    let ok = false;
    try {
      const response = await fetch('/Control-Acceso/api/health.php', { cache: 'no-store' });
      const data = await response.json();
      ok = response.ok && data.ok === true;
    } catch { /* La señal y el texto indican que no se pudo comprobar el servicio. */ }
    dot.classList.toggle('off', !ok);
    text.textContent = ok ? 'Servicio activo' : 'Servicio no disponible';
    text.classList.toggle('text-success', ok);
    text.classList.toggle('text-danger', !ok);
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyContrast(root.dataset.contrast === 'high');
    document.getElementById('btnContrast')?.addEventListener('click', () => applyContrast(root.dataset.contrast !== 'high', true));

    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('btnToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar && toggle && backdrop) {
      const mobile = window.matchMedia('(max-width: 991px)');
      let collapsed = readPreference(menuKey) === 'collapsed';
      let open = false;
      const configButton = document.getElementById('configToggle');
      const configSubmenu = document.getElementById('configSubmenu');
      let configOpen = configSubmenu ? !configSubmenu.hidden : false;
      function syncMenu() {
        sidebar.classList.toggle('collapsed', !mobile.matches && collapsed);
        document.body.classList.toggle('nav-open', mobile.matches && open);
        backdrop.hidden = !(mobile.matches && open);
        sidebar.inert = mobile.matches && !open;
        const expanded = mobile.matches ? open : !collapsed;
        toggle.setAttribute('aria-expanded', String(expanded));
        const label = mobile.matches ? (open ? 'Cerrar menú' : 'Abrir menú') : (collapsed ? 'Expandir menú' : 'Contraer menú');
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        if (configSubmenu && configButton) {
          const showConfig = configOpen && (mobile.matches || !collapsed);
          configSubmenu.hidden = !showConfig;
          configButton.setAttribute('aria-expanded', String(showConfig));
        }
      }
      function closeMenu(restoreFocus = true) {
        open = false;
        syncMenu();
        if (restoreFocus) toggle.focus();
      }
      toggle.addEventListener('click', () => {
        if (mobile.matches) {
          open = !open;
          syncMenu();
          if (open) (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('a[href]'))?.focus();
        } else {
          collapsed = !collapsed;
          savePreference(menuKey, collapsed ? 'collapsed' : 'expanded');
          syncMenu();
        }
      });
      backdrop.addEventListener('click', () => closeMenu());
      document.getElementById('sidebarClose')?.addEventListener('click', () => closeMenu());
      configButton?.addEventListener('click', () => {
        if (!mobile.matches && collapsed) {
          collapsed = false;
          configOpen = true;
          savePreference(menuKey, 'expanded');
        } else {
          configOpen = !configOpen;
        }
        syncMenu();
      });
      sidebar.querySelectorAll('a.nav-link, .subnav-link').forEach(link => link.addEventListener('click', () => { if (mobile.matches) closeMenu(false); }));
      document.addEventListener('keydown', event => {
        if (!mobile.matches || !open) return;
        if (event.key === 'Escape') { event.preventDefault(); closeMenu(); }
        if (event.key === 'Tab') {
          const targets = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter(element => !element.closest('[hidden]'));
          const first = targets[0], last = targets[targets.length - 1];
          if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
          else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
      });
      mobile.addEventListener('change', () => {
        const focusWasInside = sidebar.contains(document.activeElement);
        open = false;
        syncMenu();
        if (mobile.matches && focusWasInside) toggle.focus();
      });
      syncMenu();
    }

    const clock = document.getElementById('topbarDate');
    if (clock) {

      const updateClock = () => { const now = new Date(); clock.dateTime = now.toISOString(); clock.textContent = FechasChile.fechaHora(now); };
      updateClock();
      setInterval(updateClock, 30000);
    }
    if (document.getElementById('svcDot')) {
      checkDbHealth();
      setInterval(checkDbHealth, 15000);
    }
  });
})();
