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
  const document = {hidden:false,events:{},getElementById:element,addEventListener(name,fn){this.events[name]=fn;}};
  const window = {events:{},addEventListener(name,fn){this.events[name]=fn;}};
  const timers = new Map();
  let timerId = 0;
  const setTimer = (fn,delay) => { timers.set(++timerId,{fn,delay}); return timerId; };
  const clearTimer = id => timers.delete(id);
  const rows = [
    {rut:'1-9',nombres:'<script>inseguro</script>',estado:'ACTIVO',ultima_hora:'2001-02-03 23:59:59'},
    {rut:'2-7',nombres:'Persona anterior',estado:'INACTIVO',ultima_hora:'2001-02-02 23:59:59'},
  ];
  const makeResponse = (status = 200) => ({status,ok:status === 200,json:async()=>status === 200 ? {ok:true,items:rows,checked_at:'2026-11-07 23:30:00'} : {ok:false,error:'Debes iniciar sesión.'}});
  let reply = () => Promise.resolve(makeResponse());
  let calls = 0;
  const fetch = (url,options) => {
    calls++;
    check(url === '/Control-Acceso/api/personal_dentro.php' && options.cache === 'no-store','La consulta impone un día o permite caché.');
    return reply(options);
  };
  new Function('window','document','fetch','setTimeout','clearTimeout','AbortController',sources.inside)(window,document,fetch,setTimer,clearTimer,AbortController);
  document.events.DOMContentLoaded();
  await window.cargarDentro();
  check(element('cntDentro').textContent === 2,'Contador incorrecto.');
  check(element('tablaDentro').innerHTML.includes('&lt;script&gt;') && !element('tablaDentro').innerHTML.includes('<script>'),'El nombre no se escapa.');
  check(element('tablaDentro').innerHTML.includes('Funcionario inactivo'),'No se advierte el estado inactivo.');
  check(element('d_fecha_text').textContent === '2026-11-07 23:30:00','Fecha de actualización incorrecta.');
  check([...timers.values()].some(timer=>timer.delay === 15000),'No se programó la actualización.');
  element('d_rut').value = '2-7';
  element('d_rut').events.input();
  check(!element('tablaDentro').innerHTML.includes('1-9') && element('cntDentro').textContent === 2,'El filtro altera el total.');
  const lastUpdated = element('d_fecha_text').textContent;
  reply = () => Promise.reject(new Error('Sin conexión'));
  await window.cargarDentro();
  check(element('d_estado').textContent.includes('no está actualizada') && element('d_fecha_text').textContent === lastUpdated,'Una caída de red oculta el estado anterior.');
  let resolve;
  reply = () => new Promise(done => { resolve = done; });
  const first = window.cargarDentro();
  const second = window.cargarDentro();
  check(first === second,'Hay consultas simultáneas de presencia.');
  resolve(makeResponse());
  await first;
  document.hidden = true;
  document.events.visibilitychange();
  check(![...timers.values()].some(timer=>timer.delay === 15000),'La pestaña oculta sigue consultando.');
  document.hidden = false;
  reply = () => Promise.resolve(makeResponse());
  document.events.visibilitychange();
  await window.cargarDentro();
  check([...timers.values()].some(timer=>timer.delay === 15000),'No se reanudó la consulta.');
  reply = () => Promise.resolve(makeResponse(401));
  await window.cargarDentro();
  check(element('cntDentro').textContent === 0 && ![...timers.values()].some(timer=>timer.delay === 15000),'Una sesión vencida conserva datos o sigue consultando.');
  check(element('d_estado').textContent.includes('iniciar sesión'),'La sesión vencida no se comunica.');

  // Compilación del resto de scripts afectados, incluidas las funciones de la tabla.
  for (const script of [sources.ui,sources.system,...[...sources.records.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1]),...[...sources.report.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match=>match[1])]) {
    new Function(script);
    checks++;
  }
  return {checks,fetches:calls};
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