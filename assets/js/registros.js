(() => {
  'use strict';
  const el=id=>document.getElementById(id);
  const escape=value=>String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let page=1,controller=null;
  window.cargarUltimos=async function(next=page){
    if(controller)controller.abort();const request=new AbortController();controller=request;
    const timeout=setTimeout(()=>request.abort(),10000);
    const params=new URLSearchParams({q:el('f_rut').value.trim(),desde:el('f_fecha').value,hasta:el('f_hasta').value,puesto:el('f_puesto').value,resultado:el('f_resultado').value,limit:el('recordsLimit').value,page:String(next)});
    el('recordsSummary').textContent='Actualizando historial…';el('tabla').setAttribute('aria-busy','true');
    for(const id of ['btnRegRefresh','recordsPrev','recordsNext','recordsPage'])el(id).disabled=true;
    try{
      const response=await fetch('/Control-Acceso/api/registros_resumen.php?'+params,{cache:'no-store',signal:request.signal});const data=await response.json();
      if(!response.ok || !data.ok)throw new Error(data.error || 'No se pudo consultar.');
      if(controller!==request)return;page=data.page;
      const selected=el('f_puesto').value;
      el('f_puesto').innerHTML='<option value="">Todos</option>'+(data.puestos || []).map(value=>'<option value="'+escape(value)+'">'+escape(value)+'</option>').join('');el('f_puesto').value=selected;
      let html='';
      for(const row of data.items){
        const paired=Boolean(row.entrada && row.salida),approved=row.resultado==='APROBADO';
        const movement=paired?'<span class="badge text-bg-success">Entrada</span> <span class="badge text-bg-warning">Salida</span>':'<span class="badge '+(row.tipo_movimiento==='SALIDA'?'text-bg-warning':'text-bg-success')+'">'+(row.tipo_movimiento==='SALIDA'?'Salida':'Entrada')+'</span>';
        const posts=paired && row.puesto_salida && row.puesto_salida!==row.puesto?'Entrada: '+(row.puesto || 'Sin registro')+' · Salida: '+row.puesto_salida:row.puesto || '—';
        html+='<tr><td>'+escape(row.nombres)+'</td><td class="mono">'+escape(row.rut)+'</td><td>'+movement+'</td><td class="date-cell">'+escape(FechasChile.fechaHora(row.entrada))+'</td><td>'+escape(row.entrada?row.operador_entrada || 'Sin registro':'—')+'</td><td class="date-cell">'+escape(FechasChile.fechaHora(row.salida))+'</td><td>'+escape(row.salida?row.operador_salida || 'Sin registro':'—')+'</td><td>'+escape(row.modo==='VEHICULO'?'Vehicular':'Peatonal')+'</td><td>'+escape(posts)+'</td><td><span class="badge '+(approved?'text-bg-success':'text-bg-danger')+'">'+(approved?'APROBADO':'RECHAZADO')+'</span></td><td>'+escape(row.motivo || row.incidencia || '—')+'</td></tr>';
      }
      if(!data.items.length)html='<tr><td colspan="11"><div class="table-empty">No hay movimientos que coincidan con los filtros.</div></td></tr>';
      el('recordsBody').innerHTML=html;el('recordsSummary').textContent=Tablas.resumen(data);Tablas.estado('records',data);
    }catch(error){if(controller===request)el('recordsSummary').textContent='El historial no está actualizado. '+(error.name==='AbortError'?'Consulta interrumpida o sin respuesta.':error.message);}
    finally{clearTimeout(timeout);if(controller===request){controller=null;el('btnRegRefresh').disabled=false;el('tabla').setAttribute('aria-busy','false');}}
  };
  window.limpiarFiltros=function(){for(const id of ['f_rut','f_fecha','f_hasta','f_puesto','f_resultado'])el(id).value='';window.cargarUltimos(1);};
  document.addEventListener('DOMContentLoaded',()=>{
    el('f_fecha').value=FechasChile.hoy();el('f_hasta').value=FechasChile.hoy();
    el('recordsFilters').addEventListener('submit',event=>{event.preventDefault();window.cargarUltimos(1);});
    for(const id of ['recordsLimit','f_puesto','f_resultado'])el(id).addEventListener('change',()=>window.cargarUltimos(1));
    el('recordsPage').addEventListener('change',()=>window.cargarUltimos(Tablas.numero('records')));
    el('recordsPage').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();window.cargarUltimos(Tablas.numero('records'));}});
    document.getElementById('quickSearch')?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();el('f_rut').value=event.target.value.trim();window.cargarUltimos(1);}});
    window.cargarUltimos(1);
  });
  window.addEventListener('pagehide',()=>{if(controller)controller.abort();});
  window.addEventListener('pageshow',event=>{if(event.persisted)window.cargarUltimos();});
})();