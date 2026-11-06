<?php
require_once __DIR__ . '/app/auth.php';
require_roles(['ADMIN','AUDITOR']);
$activePage = 'reportes';
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reportes | Control de Acceso</title>
  <link href="/Control-Acceso/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/Control-Acceso/assets/css/theme.css?v=<?= filemtime(__DIR__.'/assets/css/theme.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main class="main" id="mainContent" tabindex="-1">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <section class="content">
      <?php include __DIR__ . '/partials/page-heading.php'; ?>

      <div class="card card-dark mb-4">
        <div class="card-header card-header-dark"><h2 class="h6 mb-0">Periodo de consulta</h2><div class="text-muted small">Indicadores de entradas aprobadas y rechazadas.</div></div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-6 col-xl-3"><label class="form-label" for="f_periodo">Periodo</label><select id="f_periodo" class="form-select form-control-dark"><option value="hoy">Hoy</option><option value="ayer">Ayer</option><option value="7d" selected>Últimos 7 días</option><option value="30d">Últimos 30 días</option><option value="rango">Rango personalizado</option></select></div>
            <div class="col-12 col-sm-6 col-xl-3"><label class="form-label" for="f_desde">Desde</label><input id="f_desde" type="date" class="form-control form-control-dark"></div>
            <div class="col-12 col-sm-6 col-xl-3"><label class="form-label" for="f_hasta">Hasta</label><input id="f_hasta" type="date" class="form-control form-control-dark"></div>
            <div class="col-12 col-sm-6 col-xl-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" onclick="cargarTodo()"><i class="bi bi-funnel" aria-hidden="true"></i>Aplicar</button><button class="btn btn-outline-secondary" onclick="resetHoy()">Hoy</button></div>
          </div>
        </div>
      </div>
      <div class="kpi-grid">
        <div class="card card-dark kpi-card"><div class="card-body"><i class="bi bi-door-open kpi-icon" aria-hidden="true"></i><div class="kpi-label">Total de entradas</div><div class="kpi-number" id="k_total">0</div></div></div>
        <div class="card card-dark kpi-card"><div class="card-body"><i class="bi bi-check-circle kpi-icon success" aria-hidden="true"></i><div class="kpi-label">Aprobadas</div><div class="kpi-number" id="k_ap">0</div></div></div>
        <div class="card card-dark kpi-card"><div class="card-body"><i class="bi bi-x-circle kpi-icon danger" aria-hidden="true"></i><div class="kpi-label">Rechazadas</div><div class="kpi-number" id="k_re">0</div></div></div>
        <div class="card card-dark kpi-card"><div class="card-body"><i class="bi bi-pie-chart kpi-icon" aria-hidden="true"></i><div class="kpi-label">Tasa de aprobación</div><div class="kpi-number" id="k_pct">0%</div></div></div>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-12 col-xl-7"><div class="card card-dark h-100"><div class="card-header card-header-dark"><h2 class="h6 mb-0">Evolución de ingresos</h2><div class="text-muted small">Entradas aprobadas por día.</div></div><div class="card-body"><div class="chart-frame"><canvas id="chartDias" role="img" aria-label="Gráfico de entradas aprobadas por día">Consulta los valores en la tabla de resumen.</canvas><div id="chartDiasEmpty" class="chart-empty" hidden>Sin registros en este periodo</div></div></div></div></div>
        <div class="col-12 col-xl-5"><div class="card card-dark h-100"><div class="card-header card-header-dark"><h2 class="h6 mb-0">Actividad por hora</h2><div class="text-muted small">Entradas y salidas aprobadas del último día del periodo.</div></div><div class="card-body"><div class="chart-frame"><canvas id="chartHoras" role="img" aria-label="Gráfico de entradas y salidas aprobadas por hora"></canvas><div id="chartHorasEmpty" class="chart-empty" hidden>Sin movimientos aprobados ese día</div></div></div></div></div>
      </div>
      <div class="card card-dark"><div class="card-header card-header-dark"><h2 class="h6 mb-0">Resumen diario</h2><div class="text-muted small">Resultados de las entradas registradas en el periodo.</div></div><div class="card-body"><div class="table-responsive" id="tablaResumen"><div class="table-loading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Cargando registros…</div></div></div></div>
    </section>
  </main>
</div>
<?php include __DIR__ . '/partials/scripts.php'; ?>
<script src="/Control-Acceso/assets/vendor/chartjs/chart.umd.min.js"></script>
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

    function chartPalette(){
      const style = getComputedStyle(document.documentElement);
      return { text: style.getPropertyValue('--muted').trim(), border: style.getPropertyValue('--border').trim(), panel: style.getPropertyValue('--panel2').trim() };
    }

    function chartOptions(){
      const colors = chartPalette();
      return {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'bottom', labels: { color: colors.text, usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 20, font: { size: 12 } } },
          tooltip: { backgroundColor: colors.panel, titleColor: '#f0f4fb', bodyColor: '#f0f4fb', borderColor: colors.border, borderWidth: 1, padding: 12 }
        },
        scales: {
          x: { ticks: { color: colors.text, maxRotation: 0, maxTicksLimit: 7, font: { size: 11 } }, grid: { display: false }, border: { display: false } },
          y: { beginAtZero: true, ticks: { color: colors.text, precision: 0, font: { size: 11 } }, grid: { color: colors.border }, border: { display: false } }
        }
      };
    }

    function renderDias(items){
      let labels = items.map(x => x.fecha);
      let serie = items.map(x => Number(x.aprobados || 0));
      if (labels.length === 1) { labels = ['',labels[0],'']; serie = [null,serie[0],null]; }
      document.getElementById('chartDiasEmpty').hidden = items.length > 0;
      if (chartDias) chartDias.destroy();
      const options = chartOptions();
      options.plugins.legend.display = false;
      chartDias = new Chart(document.getElementById('chartDias'), {
        type: 'line',
        data: { labels, datasets: [{ label: 'Ingresos aprobados', data: serie, borderColor: '#8bbdff', backgroundColor: 'rgba(139,189,255,.09)', pointBackgroundColor: '#8bbdff', fill: true, tension: .3, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 }] },
        options
      });
    }

    function renderHoras(items){
      const hours = Array.from({length:24},(_,hora)=>({hora,entradas:0,salidas:0}));
      for (const row of items){
        const hour = Number(row.hora);
        if (hour>=0 && hour<=23) { hours[hour].entradas = Number(row.entradas ?? row.total ?? 0); hours[hour].salidas = Number(row.salidas || 0); }
      }
      document.getElementById('chartHorasEmpty').hidden = items.length > 0;
      if (chartHoras) chartHoras.destroy();
      chartHoras = new Chart(document.getElementById('chartHoras'), {
        type: 'bar',
        data: {
          labels: hours.map(row=>String(row.hora).padStart(2,'0')+':00'),
          datasets: [
            { label:'Entradas', data:hours.map(row=>row.entradas), backgroundColor:'#8bbdff', borderRadius:3, maxBarThickness:14 },
            { label:'Salidas', data:hours.map(row=>row.salidas), backgroundColor:'#f3c675', borderRadius:3, maxBarThickness:14 }
          ]
        },
        options: chartOptions()
      });
    }

    document.addEventListener('appearancechange', () => {
      for (const chart of [chartDias,chartHoras]){
        if (!chart) continue;
        const colors = chartPalette();
        chart.options.plugins.legend.labels.color = colors.text;
        chart.options.plugins.tooltip.backgroundColor = colors.panel;
        chart.options.plugins.tooltip.borderColor = colors.border;
        chart.options.scales.x.ticks.color = colors.text;
        chart.options.scales.y.ticks.color = colors.text;
        chart.options.scales.y.grid.color = colors.border;
        chart.update('none');
      }
    });

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

      if (items.length === 0) html += "<tr><td colspan=\"5\"><div class=\"table-empty\"><i class=\"bi bi-inbox\" aria-hidden=\"true\"></i>No hay entradas registradas en este periodo.</div></td></tr>";
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
