(() => {
  'use strict';
  const el=id=>document.getElementById(id);
  const escape=value=>String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let page=1,controller=null,timer=null,searchTimer=null,stopped=false;
  window.cargarDentro=async function(next=page){
    clearTimeout(timer);if(controller)controller.abort();const request=new AbortController();controller=request;
    const timeout=setTimeout(()=>request.abort(),10000);
    const params=new URLSearchParams({q:el('d_rut').value.trim(),puesto:el('d_puesto').value,limit:el('insideLimit').value,page:String(next)});
    el('d_estado').textContent='Actualizando presencia…';el('tablaDentro').setAttribute('aria-busy','true');
    for(const id of ['insidePrev','insideNext','insidePage'])el(id).disabled=true;
    try{
      const response=await fetch('/Control-Acceso/api/personal_dentro.php?'+params,{cache:'no-store',signal:request.signal});const data=await response.json();
      if(controller!==request)return;
      if(response.status===401 || response.status===403){stopped=true;el('tablaDentro').innerHTML='';throw new Error(data.error || 'Vuelve a iniciar sesión.');}
      if(!response.ok || !data.ok)throw new Error(data.error || 'No se pudo actualizar.');
      page=data.page;
      const selected=el('d_puesto').value;el('d_puesto').innerHTML='<option value="">Todos</option>'+(data.puestos || []).map(value=>'<option value="'+escape(value)+'">'+escape(value)+'</option>').join('');el('d_puesto').value=selected;
      let html='<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>Nombre</th><th>RUT</th><th>Unidad</th><th>Grado</th><th>Modo</th><th>Puesto</th><th>Ingreso registrado</th></tr></thead><tbody>';
      for(const row of data.items)html+='<tr><td>'+escape(row.nombres)+(row.estado==='INACTIVO'?'<div class="small text-warning">Funcionario inactivo</div>':'')+'</td><td class="mono">'+escape(row.rut)+'</td><td>'+escape(row.unidad)+'</td><td>'+escape(row.grado)+'</td><td>'+escape(row.modo)+'</td><td>'+escape(row.puesto)+'</td><td class="date-cell">'+escape(row.ultima_hora)+'</td></tr>';
      if(!data.items.length)html+='<tr><td colspan="7"><div class="table-empty">No se encontró personal para esta consulta.</div></td></tr>';
      el('tablaDentro').innerHTML=html+'</tbody></table></div>';
      el('cntDentro').textContent=data.total_dentro;el('d_fecha_text').textContent=data.checked_at;
      el('d_estado').textContent=Tablas.resumen(data)+' · Total dentro: '+data.total_dentro+'. Actualización automática cada 15 segundos.';Tablas.estado('inside',data);
    }catch(error){if(controller===request && !stopped){el('cntDentro').textContent='—';el('d_estado').textContent='La lista no está actualizada. '+(error.name==='AbortError'?'Consulta interrumpida o sin respuesta.':error.message);}else if(stopped){el('cntDentro').textContent='—';el('d_estado').textContent=error.message;}}
    finally{clearTimeout(timeout);if(controller===request){controller=null;el('tablaDentro').setAttribute('aria-busy','false');if(!document.hidden && !stopped)timer=setTimeout(()=>window.cargarDentro(),15000);}}
  };
  document.addEventListener('DOMContentLoaded',()=>{
    el('d_rut').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>window.cargarDentro(1),300);});
    el('d_rut').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();clearTimeout(searchTimer);window.cargarDentro(1);}});
    for(const id of ['d_puesto','insideLimit'])el(id).addEventListener('change',()=>window.cargarDentro(1));
    el('insidePage').addEventListener('change',()=>window.cargarDentro(Tablas.numero('inside')));
    el('insidePage').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();window.cargarDentro(Tablas.numero('inside'));}});
    window.cargarDentro(1);
  });
  document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(!document.hidden && !stopped)window.cargarDentro();});
  window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);clearTimeout(searchTimer);if(controller)controller.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;window.cargarDentro();}});
})();