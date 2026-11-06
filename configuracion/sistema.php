<?php
$activePage='config_sistema';
include __DIR__.'/../partials/config-header.php';
?>
<div class="system-dashboard">
  <div class="system-toolbar">
    <div class="system-live"><span class="status-dot off" id="systemLiveDot" aria-hidden="true"></span><span id="systemLiveText">Esperando primera lectura</span><span class="system-auto-label" id="systemAutoLabel">Actualización cada 10 s</span></div>
    <div class="d-flex gap-2"><button type="button" id="systemPause" class="btn btn-sm btn-outline-light" aria-pressed="false"><i class="bi bi-pause" aria-hidden="true"></i>Pausar</button><button type="button" id="systemRefresh" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Actualizar</button></div>
  </div>
  <div class="system-message" id="systemMessage" role="status" aria-live="polite" hidden></div>

  <div class="system-metrics">
    <article class="system-metric" id="cpuCard">
      <div class="system-metric-top"><span>Procesador</span><i class="bi bi-cpu" aria-hidden="true"></i></div>
      <div class="system-value" id="cpuValue">—</div>
      <div class="system-meter" id="cpuMeter" role="progressbar" aria-label="Uso de CPU" aria-valuemin="0" aria-valuemax="100"><span></span></div>
      <div class="system-metric-detail" id="cpuDetail">Preparando lectura de CPU</div>
    </article>
    <article class="system-metric system-metric-blue" id="ramCard">
      <div class="system-metric-top"><span>Memoria RAM</span><i class="bi bi-memory" aria-hidden="true"></i></div>
      <div class="system-value" id="ramValue">—</div>
      <div class="system-meter" id="ramMeter" role="progressbar" aria-label="Uso de memoria física" aria-valuemin="0" aria-valuemax="100"><span></span></div>
      <div class="system-metric-detail" id="ramDetail">Preparando lectura de memoria</div>
    </article>
    <article class="system-metric system-metric-amber" id="temperatureCard">
      <div class="system-metric-top"><span>Temperatura</span><i class="bi bi-thermometer-half" aria-hidden="true"></i></div>
      <div class="system-value" id="temperatureValue">—</div>
      <div class="system-temperature-source" id="temperatureSource">Consultando sensor</div>
      <div class="system-metric-detail" id="temperatureDetail">Zona térmica del equipo</div>
    </article>
    <article class="system-metric system-metric-pink" id="diskCard">
      <div class="system-metric-top"><span>Almacenamiento</span><i class="bi bi-hdd" aria-hidden="true"></i></div>
      <div class="system-value" id="diskValue">—</div>
      <div class="system-meter" id="diskMeter" role="progressbar" aria-label="Uso del disco del proyecto" aria-valuemin="0" aria-valuemax="100"><span></span></div>
      <div class="system-metric-detail" id="diskDetail">Volumen que contiene el proyecto</div>
    </article>
  </div>

  <div class="system-main-grid">
    <section class="system-panel">
      <div class="system-panel-heading">
        <h2>Histórico de recursos</h2>
        <div><label class="visually-hidden" for="systemRange">Periodo del gráfico</label><select id="systemRange" class="form-select form-control-dark"><option value="15">Últimos 15 minutos</option><option value="60" selected>Última hora</option><option value="360">Últimas 6 horas</option></select></div>
      </div>
      <div class="system-chart-key"><span><i class="legend-cpu"></i>CPU</span><span><i class="legend-ram"></i>RAM</span><span class="ms-auto" id="systemSampleCount">Sin lecturas</span></div>
      <div class="system-chart-frame"><canvas id="systemChart" role="img" aria-label="Historial de uso de CPU y RAM en porcentaje">Las últimas lecturas también están disponibles en la tabla inferior.</canvas><div id="systemChartEmpty" class="system-chart-empty">El historial se completará con las lecturas reales del panel.</div></div>
      <p class="system-footnote">Lecturas del equipo que ejecuta XAMPP. El historial conserva hasta 6 horas y se registra mientras esta pantalla está activa.</p>
    </section>

    <section class="system-panel">
      <div class="system-panel-heading"><h2>Estado del servidor</h2><i class="bi bi-broadcast" aria-hidden="true"></i></div>
      <div class="system-db-line"><span>Base de datos</span><span class="system-state" id="systemDbState">Sin comprobar</span></div>
      <div class="system-db-latency"><span id="systemDbLatency">—</span><small>latencia de conexión</small></div>
      <div class="system-resource-summary">
        <div class="system-ring" id="ramRing" aria-hidden="true"><span id="ramRingValue">—</span></div>
        <div><div class="system-summary-title">RAM física utilizada</div><div class="system-summary-detail" id="ramSummary">Sin lectura</div></div>
      </div>
      <dl class="system-server-details">
        <div><dt>Tiempo encendido</dt><dd id="systemUptime">—</dd></div>
        <div><dt>Última lectura</dt><dd id="systemLastRead">—</dd></div>
        <div><dt>Sistema operativo</dt><dd id="systemOs">—</dd></div>
        <div><dt>PHP</dt><dd id="systemPhp">—</dd></div>
        <div><dt>Base de datos</dt><dd id="systemDbVersion">—</dd></div>
      </dl>
    </section>
  </div>

  <div class="system-bottom-grid">
    <section class="system-panel">
      <div class="system-panel-heading"><h2>Últimas lecturas</h2><span class="system-panel-caption">CPU / RAM / disco / sensor</span></div>
      <div class="table-responsive">
        <table class="table table-dark align-middle mb-0">
          <thead><tr><th>Hora</th><th>CPU</th><th>RAM</th><th>Disco usado</th><th>Temperatura</th></tr></thead>
          <tbody id="systemRecent"><tr><td colspan="5" class="text-muted">Esperando la primera lectura…</td></tr></tbody>
        </table>
      </div>
    </section>
    <section class="system-panel">
      <div class="system-panel-heading"><h2>Avisos de lectura</h2><i class="bi bi-info-circle" aria-hidden="true"></i></div>
      <ul class="system-notices" id="systemNotices"><li>Consultando el estado del equipo.</li></ul>
      <p class="system-footnote">Los porcentajes representan el uso del equipo completo. Los valores ausentes se muestran como no disponibles.</p>
    </section>
  </div>
</div>
<?php include __DIR__.'/../partials/config-footer.php'; ?>
