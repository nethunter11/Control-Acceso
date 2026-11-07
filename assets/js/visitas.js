let modoVisita = 'PEATONAL';
let visitsView = 'dentro';
let visitsPage = 1;
let visitsRows = new Map();
let visitsController = null;
let visitsTimer = null;
let visitsStopped = false;
const visitEscape = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
function setModoV(mode) {
  modoVisita = mode;
  for (const [id,value] of [['btnPea','PEATONAL'],['btnVeh','VEHICULO']]) {
    const button=document.getElementById(id); button.classList.toggle('active',mode===value); button.setAttribute('aria-pressed',String(mode===value));
  }
  document.getElementById('boxPatente').style.display=mode==='VEHICULO'?'block':'none';
  if(mode==='PEATONAL') document.getElementById('v_pat').value='';
}
function visitMessage(message,kind='info') {
  document.getElementById('v_resultado').innerHTML='<div class="alert alert-'+kind+' mb-0">'+visitEscape(message)+'</div>';
}
async function visitPost(path,payload) {
  const response=await fetch('/Control-Acceso/api/'+path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await response.json();
  if(!response.ok || !data.ok) throw new Error(data.error || 'No se pudo completar la operación.');
  return data;
}
async function registrarEntrada() {
  const button=document.getElementById('btnVisitaEntrada');
  if(button.disabled) return;
  button.disabled=true;
  const value=id=>document.getElementById(id).value.trim();
  try {
    const data=await visitPost('visitas_entrada.php',{nombre:value('v_nombre'),rut:value('v_rut'),telefono:value('v_tel'),patente:value('v_pat'),motivo:value('v_motivo'),modo:modoVisita});
    if(data.resultado!=='APROBADO') {visitMessage(data.motivo || 'Ingreso rechazado.','warning'); return;}
    limpiarForm(false);
    visitMessage('Entrada registrada: '+data.nombre+' · '+data.rut,'success');
    await cambiarVistaVisitas('dentro');
  } catch(error) {visitMessage(error.message,'danger');}
  finally {button.disabled=false;}
}
async function marcarSalida(id) {
  const row=visitsRows.get(Number(id));
  if(!row) return;
  if(!await Dialogos.confirmar('Registrar salida',row.nombre+' · RUT '+row.rut)) return;
  const button=document.getElementById('btnSalidaVisita_'+id);
  if(button?.disabled) return;
  if(button) button.disabled=true;
  try {
    const data=await visitPost('visitas_salida.php',{id:Number(id)});
    if(data.resultado!=='APROBADO') {visitMessage(data.motivo || 'La visita ya tiene salida.','warning'); return;}
    visitMessage('Salida registrada: '+row.nombre+' · '+row.rut,'success');
    await cargarVisitas(visitsPage);
  } catch(error) {visitMessage(error.message,'danger');}
  finally {if(button) button.disabled=false;}
}
function cambiarVistaVisitas(view) {
  visitsView=view;
  for(const mode of ['dentro','historial']) {
    const button=document.getElementById('view_'+mode);
    button.classList.toggle('active',view===mode);
    button.setAttribute('aria-pressed',String(view===mode));
  }
  for(const id of ['f_fecha','f_estado']) document.getElementById(id).disabled=view==='dentro';
  document.getElementById('visitHelp').textContent=view==='dentro'?'Todas las visitas pendientes, sin limitar la fecha de ingreso.':'La fecha filtra el día del ingreso de la visita.';
  return cargarVisitas(1);
}
async function cargarVisitas(page=1) {
  clearTimeout(visitsTimer);
  if(visitsController) visitsController.abort();
  const controller=new AbortController(); visitsController=controller;
  const timeout=setTimeout(()=>controller.abort(),10000);
  const params=new URLSearchParams({vista:visitsView,page:String(page),limit:document.getElementById('visitsLimit').value});
  const rut=document.getElementById('f_rut').value.trim();
  if(rut) params.set('q',rut);
  if(visitsView==='historial') {
    const date=document.getElementById('f_fecha').value;
    const state=document.getElementById('f_estado').value;
    if(date) params.set('fecha',date);
    if(state) params.set('estado',state);
  }
  document.getElementById('btnVisitasRefresh').disabled=true;
  try {
    const response=await fetch('/Control-Acceso/api/visitas_listar.php?'+params,{cache:'no-store',signal:controller.signal});
    const data=await response.json();
    if(!response.ok || !data.ok) throw new Error(data.error || 'No se pudo consultar.');
    if(visitsController!==controller) return;
    visitsPage=data.page; visitsRows=new Map(data.items.map(row=>[Number(row.id),row]));
    let html='<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>Nombre</th><th>RUT</th><th>Entrada</th><th>Operador entrada</th><th>Salida</th><th>Operador salida</th><th>Modo</th><th>Patente</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
    for(const row of data.items) {
      const inside=row.estado==='DENTRO';
      html+='<tr><td>'+visitEscape(row.nombre)+'</td><td class="mono">'+visitEscape(row.rut)+'</td><td>'+visitEscape(row.fecha_hora_entrada)+'</td><td>'+visitEscape(row.operador_entrada || 'Sin registro')+'</td><td>'+visitEscape(row.fecha_hora_salida || '—')+'</td><td>'+visitEscape(row.fecha_hora_salida ? row.operador_salida || 'Sin registro' : '—')+'</td><td>'+visitEscape(row.modo)+'</td><td>'+visitEscape(row.patente || '—')+'</td><td><span class="badge '+(inside?'text-bg-warning':'text-bg-secondary')+'">'+(inside?'DENTRO':'SALIÓ')+'</span></td><td>'+(inside?'<button id="btnSalidaVisita_'+Number(row.id)+'" type="button" class="btn btn-sm btn-warning" data-exit="'+Number(row.id)+'"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Salida</button>':'—')+'</td></tr>';
    }
    if(!data.items.length) html+='<tr><td colspan="10"><div class="table-empty">No hay visitas para esta consulta.</div></td></tr>';
    document.getElementById('tabla').innerHTML=html+'</tbody></table></div>';
    document.getElementById('visitSummary').textContent='Actualizado: '+data.checked_at+' · Dentro ahora: '+data.dentro+' · '+Tablas.resumen(data);
    Tablas.estado('visits',data);
  } catch(error) {
    if(visitsController===controller) document.getElementById('visitSummary').textContent='La lista no está actualizada. '+(error.name==='AbortError'?'Consulta interrumpida o sin respuesta.':error.message);
  } finally {
    clearTimeout(timeout);
    if(visitsController===controller) {
      visitsController=null;
      document.getElementById('btnVisitasRefresh').disabled=false;
      if(!document.hidden && !visitsStopped) visitsTimer=setTimeout(()=>cargarVisitas(visitsPage),15000);
    }
  }
}
function limpiarForm(refresh=true) {
  for(const id of ['v_nombre','v_rut','v_tel','v_pat','v_motivo']) document.getElementById(id).value='';
  setModoV('PEATONAL'); document.getElementById('v_resultado').textContent='';
  if(refresh) cargarVisitas(visitsPage);
}
function limpiarFiltros() {
  document.getElementById('f_fecha').value=FechasChile.hoy();
  document.getElementById('f_rut').value='';
  document.getElementById('f_estado').value='';
  cambiarVistaVisitas('dentro');
}
document.addEventListener('DOMContentLoaded',()=>{
  document.getElementById('f_fecha').value=FechasChile.hoy();
  document.getElementById('visitsLimit').addEventListener('change',()=>cargarVisitas(1));
  document.getElementById('visitsPage').addEventListener('change',()=>cargarVisitas(Tablas.numero('visits')));
  document.getElementById('visitsPage').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();cargarVisitas(Tablas.numero('visits'));}});
  document.getElementById('f_rut').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();cargarVisitas(1);}});
  document.getElementById('tabla').addEventListener('click',event=>{ const button=event.target.closest('[data-exit]'); if(button) marcarSalida(Number(button.dataset.exit)); });
  cambiarVistaVisitas('dentro');
});
document.addEventListener('visibilitychange',()=>{clearTimeout(visitsTimer); if(!document.hidden) cargarVisitas(visitsPage);});
window.addEventListener('pagehide',()=>{visitsStopped=true;clearTimeout(visitsTimer);if(visitsController)visitsController.abort();});
window.addEventListener('pageshow',event=>{if(event.persisted){visitsStopped=false;cargarVisitas(visitsPage);}});