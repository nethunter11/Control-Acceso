// Pruebas de lógica de interfaz, sin navegador ni acceso a datos.
// Recibe el contenido de los archivos y devuelve las comprobaciones realizadas.
async function comprobarInterfazAccesos(sources) {
  let checks = 0;
  const check = (condition,message) => { if (!condition) throw new Error(message); checks++; };
  const calendarWindow = {};
  new Function('window',sources.dates)(calendarWindow);
  const dates = calendarWindow.FechasChile;
  for (const [instant,expected] of [
    ['2026-11-08T02:30:00Z','2026-11-07'],
    ['2026-07-08T03:30:00Z','2026-07-07'],
    ['2026-11-08T03:30:00Z','2026-11-08'],
    ['2026-09-06T04:30:00Z','2026-09-06'],
  ]) check(dates.hoy(new Date(instant)) === expected,'Día chileno incorrecto en '+instant);
  check(dates.desplazar('2026-09-06',-1) === '2026-09-05','Cambio horario alteró el día anterior.');
  check(dates.desplazar('2024-03-01',-1) === '2024-02-29','Año bisiesto incorrecto.');
  check(dates.desplazar('2026-01-01',-1) === '2025-12-31','Cambio de año incorrecto.');
  let invalid = false;
  try { dates.desplazar('2026-02-30',1); } catch { invalid = true; }
  check(invalid,'Se aceptó una fecha imposible.');

  const fields = new Map();
  const element = id => {
    if (!fields.has(id)) fields.set(id,{value:'',textContent:'',innerHTML:'',events:{},addEventListener(name,fn){ this.events[name]=fn; }});
    return fields.get(id);
  };
  const reportCode = sources.report.match(/function setRango\(periodo\)\{[\s\S]*?(?=    function resetHoy)/)[0];
  const setRange = new Function('FechasChile','document',reportCode+';return setRango;')(
    {...dates,hoy:()=>'2026-11-07'},
    {getElementById:element}
  );
  for (const [period,from,to] of [
    ['hoy','2026-11-07','2026-11-07'],['ayer','2026-11-06','2026-11-06'],
    ['7d','2026-11-01','2026-11-07'],['30d','2026-10-09','2026-11-07'],
  ]) {
    setRange(period);
    check(element('f_desde').value === from && element('f_hasta').value === to,'Rango incorrecto: '+period);
  }
  // La presencia paginada y sus fallos se verifican en tests/ui_tablas.js.

  // Compilación del resto de scripts afectados, incluidas las funciones de la tabla.
  for (const script of [sources.ui,sources.system,...[...sources.records.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1]),...[...sources.report.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1])]) {
    new Function(script);
    checks++;
  }
  return {checks};
}
if (typeof module !== 'undefined' && module.exports) {
  module.exports = comprobarInterfazAccesos;
  if (require.main === module) {
    const fs = require('node:fs');
    const path = require('node:path');
    const root = path.resolve(__dirname,'..');
    const files = {
      dates:'assets/js/fechas.js',inside:'assets/js/personal-dentro.js',
      report:'reportes.php',records:'registros.php',ui:'assets/js/ui.js',system:'assets/js/config-sistema.js'
    };
    const sources = Object.fromEntries(Object.entries(files).map(([key,file])=>[key,fs.readFileSync(path.join(root,file),'utf8')]));
    comprobarInterfazAccesos(sources).then(result=>console.log('OK:',result.checks,'comprobaciones de interfaz')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}