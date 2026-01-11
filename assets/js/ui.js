document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('btnToggle');
  const sidebar = document.getElementById('sidebar');

  btn?.addEventListener('click', () => {
    sidebar?.classList.toggle('collapsed');
  });
});

async function checkDbHealth(){
  const dot = document.getElementById('svcDot');
  const txt = document.getElementById('svcText');
  if (!dot || !txt) return;

  try {
    const r = await fetch('/Control-Acceso/api/health.php', { cache: 'no-store' });
    const j = await r.json();

    const ok = r.ok && j.ok === true;

    if (ok) {
      dot.classList.remove('off');
      txt.textContent = 'Servicio activo';
      txt.classList.remove('text-danger');
      txt.classList.add('text-success');
    } else {
      dot.classList.add('off');
      txt.textContent = 'Servicio inactivo';
      txt.classList.remove('text-success');
      txt.classList.add('text-danger');
    }
  } catch (e) {
    dot.classList.add('off');
    txt.textContent = 'Servicio inactivo';
    txt.classList.remove('text-success');
    txt.classList.add('text-danger');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  checkDbHealth();
  setInterval(checkDbHealth, 15000); // cada 15s
});
