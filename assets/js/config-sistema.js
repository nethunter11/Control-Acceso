(() => {
  'use strict';
  const dashboard = document.querySelector('.system-dashboard');
  if (!dashboard) return;
  const byId = id => document.getElementById(id);
  const number = value => typeof value === 'number' && Number.isFinite(value);
  const format = new Intl.NumberFormat('es-CL',{maximumFractionDigits:1});
  const percent = value => number(value) ? format.format(value)+' %' : '—';
  const gib = value => number(value) ? format.format(value/1073741824) : '—';
  const clock = new Intl.DateTimeFormat('es-CL',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:FechasChile.zona});
  const chartClock = new Intl.DateTimeFormat('es-CL',{hour:'2-digit',minute:'2-digit',hour12:false,timeZone:FechasChile.zona});
  const fullDate = new Intl.DateTimeFormat('es-CL',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:FechasChile.zona});
  let last = null, chart = null, timer = null, inFlight = false, paused = false, stopped = false, authFailed = false, controller = null;

  function text(id,value){ byId(id).textContent = value; }
  function meter(id,value){
    const element=byId(id);
    element.querySelector('span').style.width=(number(value)?Math.max(0,Math.min(100,value)):0)+'%';
    if(number(value)) { element.setAttribute('aria-valuenow',String(value)); element.setAttribute('aria-valuetext',percent(value)); }
    else { element.removeAttribute('aria-valuenow'); element.setAttribute('aria-valuetext','No disponible'); }
  }
  function metric(type,value,detail){
    const valueElement=byId(type+'Value');
    valueElement.textContent=number(value)?percent(value):'No disponible';
    valueElement.classList.toggle('is-unavailable',!number(value));
    const card=byId(type+'Card');
    card.classList.toggle('is-warning',number(value)&&value>=75&&value<90);
    card.classList.toggle('is-critical',number(value)&&value>=90);
    text(type+'Detail',detail);
    meter(type+'Meter',value);
  }
  function uptime(seconds){
    if(!number(seconds))return 'No disponible';
    const days=Math.floor(seconds/86400),hours=Math.floor(seconds%86400/3600),minutes=Math.floor(seconds%3600/60);
    return (days?days+' d ':'')+hours+' h '+minutes+' min';
  }
  function notice(title,description,level=''){
    const item=document.createElement('li');
    item.className=level;
    const heading=document.createElement('strong');heading.textContent=title;
    const body=document.createElement('span');body.textContent=description;
    item.append(heading,body);
    return item;
  }
  function renderNotices(metrics,database){
    const list=byId('systemNotices'),items=[];
    for(const [key,label] of [['cpu_percent','CPU'],['ram_percent','RAM'],['disk_percent','Almacenamiento']]){
      if(!number(metrics[key]))items.push(notice(label+' sin lectura','No se pudo obtener este dato de Windows.','warning'));
      else if(metrics[key]>=90)items.push(notice(label+': uso alto',percent(metrics[key])+' utilizado. Revisa los recursos del equipo.','danger'));
      else if(metrics[key]>=75)items.push(notice(label+': uso elevado',percent(metrics[key])+' utilizado.','warning'));
    }
    if(!database.ok)items.push(notice('Base de datos sin conexión','No se pudo completar la consulta de diagnóstico.','danger'));
    if(!items.length)items.push(notice('Lecturas disponibles','No hay avisos de uso elevado en esta lectura.'));
    list.replaceChildren(...items);
  }
  function historyRows(){
    if(!last)return [];
    const end=last.sample.timestamp;
    const minutes=Number(byId('systemRange').value)||60;
    return last.history.filter(row=>number(row.timestamp)&&row.timestamp>=end-minutes*60&&row.timestamp<=end).sort((a,b)=>a.timestamp-b.timestamp);
  }
  function palette(){
    const styles=getComputedStyle(dashboard);
    const value=(key,fallback)=>styles.getPropertyValue(key).trim()||fallback;
    return {cpu:value('--sys-cyan','#34f0de'),ram:value('--sys-blue','#72b6ff'),text:value('--sys-muted','#a7c3d0'),grid:value('--sys-grid','rgba(77,155,171,.2)'),bg:value('--sys-bg','#0b141f')};
  }
  function renderHistory(){
    if(!last)return;
    const rows=historyRows(),colors=palette();
    text('systemSampleCount',rows.length+' lectura'+(rows.length===1?'':'s'));
    const hasData=rows.some(row=>number(row.cpu_percent)||number(row.ram_percent));
    byId('systemChartEmpty').hidden=rows.length>=2&&hasData;
    text('systemChartEmpty',!hasData?'No hay lecturas de CPU o RAM disponibles.':rows.length?'Primera lectura registrada. El gráfico crecerá con las siguientes.':'No hay lecturas en este periodo.');
    const series=key=>{
      const points=[];let previous=null;
      for(const row of rows){
        if(previous!==null&&row.timestamp-previous>30)points.push({x:(previous+10)*1000,y:null});
        points.push({x:row.timestamp*1000,y:number(row[key])?row[key]:null});
        previous=row.timestamp;
      }
      return points;
    };
    const end=last.sample.timestamp*1000;
    const begin=rows.length>1?rows[0].timestamp*1000:end-60000;
    if(typeof Chart!=='function'){
      byId('systemChartEmpty').hidden=false;
      text('systemChartEmpty','No se pudo cargar el gráfico. Consulta las lecturas en la tabla.');
    }else{
      const options={
        responsive:true,maintainAspectRatio:false,animation:false,parsing:false,normalized:true,
        interaction:{mode:'nearest',intersect:false},
        plugins:{legend:{display:false},tooltip:{backgroundColor:colors.bg,borderColor:colors.grid,borderWidth:1,titleColor:'#effbff',bodyColor:'#effbff',padding:12,callbacks:{
          title:items=>items.length?fullDate.format(new Date(items[0].parsed.x)):'',
          label:context=>context.dataset.label+': '+percent(context.parsed.y)
        }}},
        scales:{
          x:{type:'linear',min:begin,max:end,ticks:{color:colors.text,maxTicksLimit:6,maxRotation:0,font:{size:11},callback:value=>chartClock.format(new Date(value))},grid:{color:colors.grid},border:{display:false}},
          y:{min:0,max:100,ticks:{color:colors.text,stepSize:25,font:{size:11},callback:value=>value+'%'},grid:{color:colors.grid},border:{display:false}}
        }
      };
      const datasets=[
        {label:'CPU',data:series('cpu_percent'),borderColor:colors.cpu,backgroundColor:colors.cpu,pointRadius:rows.length<3?3:0,pointHoverRadius:4,borderWidth:2,tension:.2,spanGaps:false},
        {label:'RAM',data:series('ram_percent'),borderColor:colors.ram,backgroundColor:colors.ram,pointRadius:rows.length<3?3:0,pointHoverRadius:4,borderWidth:2,tension:.2,spanGaps:false}
      ];
      if(chart){chart.data.datasets=datasets;chart.options=options;chart.update('none');}
      else chart=new Chart(byId('systemChart'),{type:'line',data:{datasets},options});
    }
    byId('systemRecent').innerHTML=rows.slice(-8).reverse().map(row=>{
      const when=new Date(row.timestamp*1000);
      return '<tr><td><time datetime="'+when.toISOString()+'" title="'+fullDate.format(when)+'">'+clock.format(when)+'</time></td><td>'+percent(row.cpu_percent)+'</td><td>'+percent(row.ram_percent)+'</td><td>'+percent(row.disk_percent)+'</td><td>'+(number(row.temperature_c)?format.format(row.temperature_c)+' °C':'—')+'</td></tr>';
    }).join('')||'<tr><td colspan="5">No hay lecturas en este periodo.</td></tr>';
  }
  function render(data){
    last=data;
    const metrics=data.sample.metrics,db=data.sample.database;
    metric('cpu',metrics.cpu_percent,number(metrics.cpu_percent)?'Uso total del procesador':'No se pudo obtener el contador de CPU');
    metric('ram',metrics.ram_percent,number(metrics.ram_percent)?gib(metrics.ram_used_bytes)+' / '+gib(metrics.ram_total_bytes)+' GiB utilizados':'Memoria física no disponible');
    metric('disk',metrics.disk_percent,number(metrics.disk_percent)?gib(metrics.disk_free_bytes)+' GiB libres de '+gib(metrics.disk_total_bytes)+' GiB':'Lectura del volumen no disponible');
    text('ramRingValue',percent(metrics.ram_percent));
    byId('ramRing').style.setProperty('--level',number(metrics.ram_percent)?metrics.ram_percent:0);
    text('ramSummary',number(metrics.ram_percent)?gib(metrics.ram_used_bytes)+' de '+gib(metrics.ram_total_bytes)+' GiB en uso':'No disponible');
    text('systemDbState',db.ok?'Operativa':'Sin conexión');
    byId('systemDbState').classList.toggle('is-ok',db.ok);
    byId('systemDbState').classList.toggle('is-bad',!db.ok);
    text('systemDbLatency',db.ok&&number(db.latency_ms)?format.format(db.latency_ms)+' ms':'—');
    text('systemUptime',uptime(metrics.uptime_seconds));
    text('systemLastRead',clock.format(new Date(data.sample.timestamp*1000)));
    text('systemOs',data.runtime.os);
    text('systemPhp',data.runtime.php);
    text('systemDbVersion',db.version||'No disponible');
    const partial=!number(metrics.cpu_percent)||!number(metrics.ram_percent);
    dashboard.classList.toggle('is-stale',data.stale);
    byId('systemLiveDot').classList.toggle('off',data.stale||partial);
    text('systemLiveText',data.stale?'Lectura pendiente de actualizar':partial?'Lectura parcial':'Lecturas actualizadas');
    renderNotices(metrics,db);
    renderHistory();
  }
  function schedule(){
    clearTimeout(timer);
    if(!paused&&!stopped&&!authFailed&&!document.hidden)timer=setTimeout(refresh,10000);
  }
  async function refresh(){
    if(inFlight||stopped)return;
    inFlight=true;clearTimeout(timer);
    const button=byId('systemRefresh');button.disabled=true;
    controller=new AbortController();
    const timeout=setTimeout(()=>controller.abort(),12000);
    try{
      const response=await fetch('/Control-Acceso/api/admin_system_status.php',{cache:'no-store',signal:controller.signal});
      if(response.status===401||response.status===403){authFailed=true;text('systemAutoLabel','Actualización detenida');throw new Error('Tu sesión no permite consultar Sistema. Inicia sesión como administrador.');}
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.error||'No se pudo actualizar el diagnóstico.');
      render(data);
      text('systemAutoLabel',paused?'Actualización en pausa':'Actualización cada 10 s');
      byId('systemMessage').hidden=!data.stale;
      text('systemMessage',data.stale?'Se muestra la última lectura disponible. El panel intentará actualizarla nuevamente.':'');
    }catch(error){
      if(!(error.name==='AbortError'&&document.hidden)){
        dashboard.classList.add('is-stale');byId('systemLiveDot').classList.add('off');
        text('systemLiveText',authFailed?'Sesión sin acceso':'Sin actualizar');
        byId('systemMessage').hidden=false;
        text('systemMessage',authFailed?error.message:'No se pudo actualizar el diagnóstico. '+(last?'Los valores visibles corresponden a la última lectura.':'Se intentará nuevamente.'));
      }
    }finally{
      clearTimeout(timeout);inFlight=false;button.disabled=false;controller=null;schedule();
    }
  }
  byId('systemRefresh').addEventListener('click',()=>{authFailed=false;refresh();});
  byId('systemPause').addEventListener('click',()=>{
    paused=!paused;
    byId('systemPause').setAttribute('aria-pressed',String(paused));
    byId('systemPause').innerHTML=paused?'<i class="bi bi-play" aria-hidden="true"></i>Reanudar':'<i class="bi bi-pause" aria-hidden="true"></i>Pausar';
    text('systemAutoLabel',paused?'Actualización en pausa':'Actualización cada 10 s');
    if(paused)clearTimeout(timer);else refresh();
  });
  byId('systemRange').addEventListener('change',renderHistory);
  document.addEventListener('appearancechange',renderHistory);
  document.addEventListener('visibilitychange',()=>{
    if(document.hidden){clearTimeout(timer);controller?.abort();}
    else if(!paused)refresh();
  });
  window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);controller?.abort();chart?.destroy();chart=null;});
  window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;if(paused)renderHistory();else refresh();}});
  refresh();
})();
