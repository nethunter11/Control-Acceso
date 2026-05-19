<script src="assets/vendor/chartjs/chart.umd.min.js"></script>
<?php
require_once __DIR__ . '/app/auth.php';
require_login();
require_roles(['ADMIN','AUDITOR']); 
$activePage = 'reportes';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reportes | Control Acceso</title>

  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/theme.css?v=1" rel="stylesheet">
</head>

<body>
  <div class="app">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main">
      <?php include __DIR__ . '/partials/topbar.php'; ?>

      <section class="content">
        <div class="card card-dark mb-3">
          <div class="card-header card-header-dark">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="h6 mb-0">Reportes</div>
                <div class="text-muted small">Entradas (tipo ENTRADA) - Aprobados y Rechazados</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <!-- Filtros -->
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label">Periodo</label>
                <select id="f_periodo" class="form-select form-control-dark">
                  <option value="hoy">Hoy</option>
                  <option value="ayer">Ayer</option>
                  <option value="7d" selected>Últimos 7 días</option>
                  <option value="30d">Últimos 30 días</option>
                  <option value="rango">Rango</option>
                </select>
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label">Desde</label>
                <input id="f_desde" type="date" class="form-control form-control-dark">
              </div>

              <div class="col-6 col-md-3">
                <label class="form-label">Hasta</label>
                <input id="f_hasta" type="date" class="form-control form-control-dark">
              </div>

              <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-primary w-100" onclick="cargarTodo()">
                  <i class="bi bi-funnel me-1"></i>Aplicar
                </button>
                <button class="btn btn-outline-secondary w-100" onclick="resetHoy()">
                  <i class="bi bi-x-circle me-1"></i>Hoy
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-3">
            <div class="card card-dark">
              <div class="card-body">
                <div class="text-muted small">Entradas</div>
                <div class="h3 mb-0 kpi-number" id="k_total">0</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-dark">
              <div class="card-body">
                <div class="text-muted small">Aprobados</div>
                <div class="h3 mb-0 kpi-number" id="k_ap">0</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-dark">
              <div class="card-body">
                <div class="text-muted small">Rechazados</div>
                <div class="h3 mb-0 kpi-number" id="k_re">0</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-dark">
              <div class="card-body">
                <div class="text-muted small">% Aprobación</div>
                <div class="h3 mb-0 kpi-number" id="k_pct">0%</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mb-3 kpi-row">
          <div class="col-12 col-lg-7">
            <div class="card card-dark w-100 h-100">
              <div class="card-header card-header-dark">
                <div class="h6 mb-0">Registro de Ingresos</div>
                <div class="text-muted small">Entradas por día</div>
              </div>
              <div class="card-body">
                <canvas id="chartDias" height="120"></canvas>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-5">
            <div class="card card-dark w-100 h-100">
              <div class="card-header card-header-dark">
                <div class="h6 mb-0">Horas pico</div>
                <div class="text-muted small">Entradas aprobadas por hora (día seleccionado)</div>
              </div>
              <div class="card-body">
                <canvas id="chartHoras" height="120"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabla resumen -->
        <div class="card card-dark">
          <div class="card-header card-header-dark">
            <div class="h6 mb-0">Resumen por día</div>
            <div class="text-muted small">Total / Aprobados / Rechazados</div>
          </div>
          <div class="card-body">
            <div class="table-responsive" id="tablaResumen"></div>
          </div>
        </div>

      </section>
    </main>
  </div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chartjs/chart.umd.min.js"></script>

  <script>
    let chartDias = null;
    let chartHoras = null;
    
    function escapeHtml(s){
      return String(s ?? '')
        .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
        .replaceAll('"','&quot;').replaceAll("'","&#039;");
    }

    function ymd(d){
      return d.toISOString().slice(0,10);
    }

    function setRango(periodo){
      const hoy = new Date();
      const desde = new Date(hoy);
      const hasta = new Date(hoy);

      if (periodo === 'hoy'){
        // hoy
      } else if (periodo === 'ayer'){
        desde.setDate(desde.getDate()-1);
        hasta.setDate(hasta.getDate()-1);
      } else if (periodo === '7d'){
        desde.setDate(desde.getDate()-6);
      } else if (periodo === '30d'){
        desde.setDate(desde.getDate()-29);
      } else {
        return; // rango manual
      }

      document.getElementById('f_desde').value = ymd(desde);
      document.getElementById('f_hasta').value = ymd(hasta);
    }

    function resetHoy(){
      document.getElementById('f_periodo').value = 'hoy';
      setRango('hoy');
      cargarTodo();
    }

    async function fetchJson(url){
      const r = await fetch(url, { cache:'no-store' });
      return r.json();
    }

    async function cargarTodo(){
      const desde = document.getElementById('f_desde').value;
      const hasta = document.getElementById('f_hasta').value;

      // KPI
      const k = await fetchJson(`api/reportes_kpi.php?desde=${desde}&hasta=${hasta}`);
      if (k.ok){
        document.getElementById('k_total').textContent = k.kpi.total;
        document.getElementById('k_ap').textContent = k.kpi.aprobados;
        document.getElementById('k_re').textContent = k.kpi.rechazados;
        document.getElementById('k_pct').textContent = k.kpi.pct_aprob + '%';
      }

      // Diario (gráfico + tabla)
      const d = await fetchJson(`api/reportes_diario.php?desde=${desde}&hasta=${hasta}`);
      if (d.ok){
        renderDias(d.items || []);
        renderTabla(d.items || []);
      }

      // Horas pico: usa "hasta" como día seleccionado (último día del rango)
      const h = await fetchJson(`api/reportes_horas.php?fecha=${hasta}`);
      if (h.ok){
        renderHoras(h.items || []);
      }
    }

    function renderDias(items) {
  let labels = items.map(x => x.fecha);
  let serie  = items.map(x => Number(x.aprobados || 0));

  // Si hay 1 solo día, agrega "fantasmas" para centrar horizontalmente
  if (labels.length === 1) {
    labels = ['', labels[0], ''];
    serie  = [null, serie[0], null];
  }

  const ctx = document.getElementById('chartDias');
  if (chartDias) chartDias.destroy();

  chartDias = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Ingresos aprobados',
        data: serie,
        fill: false,
        tension: 0.35,
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 2
      }]
    },
    scales: {
      x: {
        ticks: {
          color: '#fff',
          callback: (val, idx) => labels[idx] || ''   // oculta '' y muestra solo la fecha real
        },
        grid: { color: 'rgba(255,255,255,.08)' }
      },
      y: {
        beginAtZero: true,
        ticks: { color: '#fff' },
        grid: { color: 'rgba(255,255,255,.08)' }
      }
    }
  });
}

    function renderHoras(items){
      // Normaliza 0..23
      const arr = Array.from({length:24}, (_,h)=>({hora:h,total:0}));
      for (const r of items){
        const h = Number(r.hora);
        if (h>=0 && h<=23) arr[h].total = Number(r.total || 0);
      }

      const labels = arr.map(x => String(x.hora).padStart(2,'0') + ':00');
      const data = arr.map(x => x.total);

      const ctx = document.getElementById('chartHoras');
      if (chartHoras) chartHoras.destroy();

      chartHoras = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Entradas aprobadas', data }] },
        options: {
        maintainAspectRatio: false,
          spanGaps: false,
          responsive: true,
          plugins: { legend: { labels: { color: '#fff' } } },
          scales: {
            x: { ticks: { color: '#fff', maxRotation: 0 }, grid: { color: 'rgba(255,255,255,.08)' } },
            y: { ticks: { color: '#fff' }, grid: { color: 'rgba(255,255,255,.08)' } }
          }
        }
      });
    }

    function renderTabla(items){
      let html = `<table class="table table-dark table-hover align-middle mb-0">
        <thead><tr>
          <th>Fecha</th><th>Total</th><th>Aprobados</th><th>Rechazados</th><th>% Aprob.</th>
        </tr></thead><tbody>`;

      for (const r of items){
        const total = Number(r.total||0);
        const ap = Number(r.aprobados||0);
        const pct = total>0 ? ((ap/total)*100).toFixed(1) : '0.0';
        html += `<tr>
          <td>${escapeHtml(r.fecha)}</td>
          <td>${total}</td>
          <td>${ap}</td>
          <td>${Number(r.rechazados||0)}</td>
          <td>${pct}%</td>
        </tr>`;
      }

      html += `</tbody></table>`;
      document.getElementById('tablaResumen').innerHTML = html;
    }

    document.addEventListener('DOMContentLoaded', () => {
      setRango('7d');

      document.getElementById('f_periodo').addEventListener('change', (e) => {
        const p = e.target.value;
        if (p !== 'rango') setRango(p);
      });

      cargarTodo();
    });
  </script>
</body>
</html>