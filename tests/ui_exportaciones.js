async function comprobarExportaciones(source,runtime){
  let checks=0;
  const check=(yes,message)=>{if(!yes)throw new Error(message);checks++;};
  const fields=new Map(),calls=[],downloads=[],timers=[];
  const el=id=>{
    if(!fields.has(id))fields.set(id,{value:'',disabled:false,required:false,hidden:false,textContent:'',innerHTML:'Descargar CSV',min:'',max:'',validationMessage:'',events:{},
      classList:{add(){},remove(){}},setAttribute(){},setCustomValidity(message){this.validationMessage=message;},addEventListener(name,fn){this.events[name]=fn;}});
    return fields.get(id);
  };
  const document={events:{},getElementById:el,addEventListener(name,fn){this.events[name]=fn;},body:{appendChild(){}},createElement(){return{click(){downloads.push(this.download);},remove(){}};}};
  el('csvForm').reportValidity=()=>['csvDate','csvFrom','csvTo'].every(id=>{const input=el(id);return input.disabled || (!input.validationMessage && (!input.required || input.value));});
  const makeResponse=(count=3,type='text/csv; charset=utf-8',ok=true)=>({ok,headers:{get(name){return{'Content-Type':type,'Content-Disposition':'attachment; filename="exportacion.csv"','X-Export-Row-Count':String(count)}[name] ?? null;}},json:async()=>({error:'Rango inválido.'}),blob:async()=>({})});
  let reply=()=>Promise.resolve(makeResponse());
  const fetch=(url)=>{calls.push({url,disabled:el('btnExportCsv').disabled,controls:['csvTable','csvPeriod','csvDate','csvFrom','csvTo','csvCriterion'].every(id=>el(id).disabled)});return reply();};
  const api=new Function('document','fetch','URLSearchParams','URL','setTimeout',source+';return {sync:syncExportControls,download:exportCsv,validate:validateExportRange};')(document,fetch,runtime.URLSearchParams,{createObjectURL:()=>'blob:exportacion',revokeObjectURL(){}},fn=>timers.push(fn));
  const dates=()=>{el('csvDate').value='2026-11-07';el('csvFrom').value='2026-11-03';el('csvTo').value='2026-11-07';el('csvCriterion').value='entrada';};
  for(const table of ['registros_acceso','visitas']){
    for(const period of ['dia','rango']){
      el('csvTable').value=table;el('csvPeriod').value=period;dates();api.sync();
      check(el('csvDateGroup').hidden===(period!=='dia') && el('csvFromGroup').hidden===(period!=='rango') && el('csvToGroup').hidden===(period!=='rango'),'Calendarios incorrectos en '+table+'/'+period);
      check(el('csvDate').required===(period==='dia') && el('csvFrom').required===(period==='rango') && el('csvTo').required===(period==='rango'),'Fechas requeridas incorrectas.');
      check(el('csvCriterion').disabled===(table!=='visitas'),'El criterio de visitas falta en un periodo.');
      await api.download();
      const call=calls.at(-1);
      check(period==='dia'?call.url.includes('fecha=2026-11-07')&&!call.url.includes('desde='):call.url.includes('desde=2026-11-03')&&call.url.includes('hasta=2026-11-07')&&!call.url.includes('fecha='),'La URL no corresponde al periodo.');
      check(table==='visitas'?call.url.includes('criterio=entrada'):!call.url.includes('criterio='),'Criterio enviado al listado incorrecto.');
      check(call.disabled && call.controls && !el('btnExportCsv').disabled && !el('csvTable').disabled,'Los controles no se bloquean y restauran al descargar.');
    }
  }
  for(const table of ['funcionarios','usuarios']){
    el('csvTable').value=table;el('csvPeriod').value='rango';api.sync();await api.download();
    check(el('csvPeriodGroup').hidden && el('csvPeriod').disabled && !el('csvFrom').required && !el('csvTo').required,'Se solicitan fechas en un listado completo.');
    check(!calls.at(-1).url.includes('desde=') && !calls.at(-1).url.includes('fecha=') && !calls.at(-1).url.includes('criterio='),'El listado completo recibe filtros ajenos.');
  }
  el('csvTable').value='registros_acceso';el('csvPeriod').value='rango';dates();el('csvFrom').value='2026-11-08';api.sync();
  let previous=calls.length;await api.download();
  check(calls.length===previous && el('csvTo').validationMessage!=='','Un rango invertido se envía al servidor.');
  dates();el('csvFrom').value='';api.sync();await api.download();check(calls.length===previous,'Un rango incompleto se envía.');
  dates();el('csvFrom').value=el('csvTo').value;api.sync();await api.download();check(calls.length===previous+1,'Un rango de un día no se permite.');
  el('csvPeriod').value='todos';previous=calls.length;await api.download();check(calls.length===previous && el('csvMessage').textContent.includes('Selecciona'),'Un periodo inválido descarga todos los registros.');
  el('csvPeriod').value='rango';dates();api.sync();reply=()=>Promise.resolve(makeResponse(0));await api.download();
  check(el('csvMessage').textContent.includes('con encabezados'),'No se informa el CSV vacío.');
  previous=downloads.length;reply=()=>Promise.resolve(makeResponse(0,'text/html'));await api.download();
  check(downloads.length===previous && el('csvMessage').textContent.includes('No se recibió un archivo CSV'),'Una página HTML se descarga como CSV.');
  reply=()=>Promise.resolve(makeResponse(0,'application/json',false));await api.download();
  check(el('csvMessage').textContent==='Rango inválido.' && !el('btnExportCsv').disabled && !el('csvFrom').disabled,'Un error no se informa o deja controles bloqueados.');
  let resolve;reply=()=>new Promise(done=>{resolve=done;});previous=calls.length;
  const pending=api.download();await api.download();check(calls.length===previous+1,'Un doble clic duplica la descarga.');
  resolve(makeResponse());await pending;
  return{checks};
}
if(typeof module!=='undefined' && module.exports){
  module.exports=comprobarExportaciones;
  if(require.main===module){const fs=require('node:fs'),path=require('node:path');const source=fs.readFileSync(path.join(__dirname,'../assets/js/config-exportaciones.js'),'utf8');
    comprobarExportaciones(source,{URLSearchParams}).then(result=>console.log('OK:',result.checks,'comprobaciones de exportación')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}