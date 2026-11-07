(() => {
  'use strict';
  let items = [];
  let inFlight = null;
  let timer = null;
  let stopped = false;
  const escape = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  const status = message => { document.getElementById('d_estado').textContent = message; };
  function render() {
    const filter = document.getElementById('d_rut').value.trim();
    const rows = items.filter(row => !filter || row.rut.includes(filter));
    document.getElementById('cntDentro').textContent = items.length;
    let html = '<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>Nombre</th><th>RUT</th><th>Unidad</th><th>Grado</th><th>Modo</th><th>Puesto</th><th>Ingreso registrado</th></tr></thead><tbody>';
    for (const row of rows) {
      const inactive = row.estado === 'INACTIVO' ? '<div class="small text-warning">Funcionario inactivo</div>' : '';
      html += '<tr><td>'+escape(row.nombres)+inactive+'</td><td class="mono">'+escape(row.rut)+'</td><td>'+escape(row.unidad)+'</td><td>'+escape(row.grado)+'</td><td>'+escape(row.modo)+'</td><td>'+escape(row.puesto)+'</td><td>'+escape(row.ultima_hora)+'</td></tr>';
    }
    if (!rows.length) html += '<tr><td colspan="7"><div class="table-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No se encontró personal para esta consulta.</div></td></tr>';
    html += '</tbody></table></div>';
    document.getElementById('tablaDentro').innerHTML = html;
  }
  function schedule() {
    clearTimeout(timer);
    if (!document.hidden && !stopped) timer = setTimeout(() => window.cargarDentro(),15000);
  }
  window.cargarDentro = function () {
    if (inFlight) return inFlight;
    clearTimeout(timer);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(),10000);
    inFlight = (async () => {
      try {
        const response = await fetch('/Control-Acceso/api/personal_dentro.php',{cache:'no-store',signal:controller.signal});
        const data = await response.json();
        if (response.status === 401 || response.status === 403) {
          stopped = true;
          items = [];
          render();
          document.getElementById('d_fecha_text').textContent = '—';
          throw new Error(data.error || 'Vuelve a iniciar sesión para consultar el personal.');
        }
        if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo actualizar.');
        stopped = false;
        items = data.items || [];
        render();
        document.getElementById('d_fecha_text').textContent = data.checked_at;
        status('Actualización automática cada 15 segundos. Total dentro: '+items.length+'.');
        // El total siempre cuenta todas las personas, aunque se filtre la tabla.
        if (document.getElementById('d_rut').value.trim()) status('Actualizado. Mostrando coincidencias del RUT; total dentro: '+items.length+'.');
      } catch (error) {
        status((error.name === 'AbortError' ? 'Se agotó el tiempo de consulta.' : error.message)+' La lista no está actualizada.');
      } finally {
        clearTimeout(timeout);
        inFlight = null;
        schedule();
      }
    })();
    return inFlight;
  };
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('d_rut').addEventListener('input',render);
    window.cargarDentro();
  });
  document.addEventListener('visibilitychange', () => {
    clearTimeout(timer);
    if (!document.hidden && !stopped) window.cargarDentro();
  });
  window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
  window.addEventListener('pageshow', event => { if (event.persisted) { stopped = false; window.cargarDentro(); } });
})();