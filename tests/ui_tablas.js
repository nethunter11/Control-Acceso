async function comprobarTablas(sources,runtime){
  const calendar={};new Function('window',sources.dates)(calendar);const dates=calendar.FechasChile;
  let checks=0;const check=(yes,text)=>{if(!yes)throw new Error(text);checks++;};
  const flush=async()=>{for(let i=0;i<16;i++)await Promise.resolve();};
  function dom(){
    const fields=new Map(),timers=new Map();let timerId=0;
    const el=id=>{if(!fields.has(id))fields.set(id,{value:'',textContent:'',innerHTML:'',disabled:false,dataset:{},events:{},attributes:{},classList:{toggle(){}},setAttribute(name,value){this.attributes[name]=value;},addEventListener(name,fn){this.events[name]=fn;}});return fields.get(id);};
    const document={hidden:false,events:{},getElementById:el,addEventListener(name,fn){this.events[name]=fn;}};
    const window={events:{},addEventListener(name,fn){this.events[name]=fn;}};
    new Function('window','document',sources.tables)(window,document);
    return{el,document,window,table:window.Tablas,timers,setTimer(fn,delay){timers.set(++timerId,{fn,delay});return timerId;},clearTimer(id){timers.delete(id);}};
  }
  {
    const d=dom();
    check(d.table.resumen({total:61,page:3,pages:3,limit:25,from:51,to:61})==='51–61 de 61 · Página 3 de 3','Resumen de página incorrecto.');
    check(d.table.resumen({total:0,page:1,pages:1,limit:25})==='0–0 de 0 · Página 1 de 1','Resumen vacío engañoso.');
    d.table.estado('x',{page:3,pages:3});check(d.el('xNext').disabled && !d.el('xPrev').disabled,'Botones incorrectos en última página.');
    d.el('xPage').value='999';check(d.table.numero('x')===3,'Ir a página excede el máximo.');
  }
  {
    const d=dom(),calls=[];let deferred=null,defer=false;
    d.el('recordsLimit').value='25';d.el('f_puesto').value='';
    const response=(url,label='Persona')=>{
      const requested=Number(/[?&]page=(\d+)/.exec(url)?.[1] || 1),limit=Number(/[?&]limit=(\d+)/.exec(url)?.[1] || 25);
      const total=url.includes('NO_EXISTE')?0:61,pages=Math.max(1,Math.ceil(total/limit)),page=Math.min(requested,pages);
      return{ok:true,json:async()=>({ok:true,total,pages,page,limit,from:total?(page-1)*limit+1:0,to:Math.min(total,page*limit),puestos:['Pórtico Norte'],items:total?[{nombres:label,rut:'12345678-5',tipo_movimiento:'ENTRADA',entrada:'2001-02-03 23:00:00',salida:'2001-02-04 01:00:00',puesto:'Pórtico Norte',puesto_salida:'Pórtico Sur',resultado:'APROBADO',operador_entrada:'ADMIN',operador_salida:'GUARDIA'}]:[]})};
    };
    const fetch=async(url)=>{calls.push(url);if(defer){defer=false;return new Promise(resolve=>{deferred=()=>resolve(response(url,'Anterior'));});}return response(url,'<script>Nueva</script>');};
    new Function('window','document','fetch','URLSearchParams','AbortController','setTimeout','clearTimeout','Tablas','FechasChile',sources.records)(d.window,d.document,fetch,runtime.URLSearchParams,runtime.AbortController,d.setTimer,d.clearTimer,d.table,{...dates,hoy:()=>'2026-11-07'});
    d.document.events.DOMContentLoaded();await flush();
    check(calls[0].includes('desde=2026-11-07') && calls[0].includes('hasta=2026-11-07'),'No se usa el día chileno por defecto.');
    check(d.el('recordsBody').innerHTML.includes('&lt;script&gt;') && d.el('recordsBody').innerHTML.includes('Salida: Pórtico Sur'),'La tabla pierde escape o puesto de salida.');
    check(d.el('recordsBody').innerHTML.includes('03-02-2001 23:00') && d.el('recordsBody').innerHTML.includes('04-02-2001 01:00'),'El historial no formatea las dos fechas de una permanencia nocturna.');
    await d.window.cargarUltimos(3);check(d.el('recordsSummary').textContent.includes('51–61 de 61') && d.el('recordsNext').disabled,'No se navega hasta la última página.');
    d.el('f_rut').value='NO_EXISTE';await d.window.cargarUltimos(1);check(d.el('recordsSummary').textContent.startsWith('0–0 de 0') && d.el('recordsBody').innerHTML.includes('No hay movimientos'),'No se comunica una búsqueda vacía.');
    d.el('f_rut').value='vieja';defer=true;const old=d.window.cargarUltimos(1);
    d.el('f_rut').value='nueva';await d.window.cargarUltimos(1);deferred();await old;
    check(!d.el('recordsBody').innerHTML.includes('Anterior'),'Una respuesta vieja reemplaza la búsqueda nueva.');
    d.window.limpiarFiltros();await flush();check(d.el('f_fecha').value==='' && d.el('f_hasta').value==='','Limpiar deja fechas ocultas activas.');
  }
  {
    const d=dom(),calls=[];let fail=false,status=200;
    d.el('insideLimit').value='25';d.el('d_puesto').value='';
    const fetch=async(url)=>{
      calls.push(url);if(fail)throw new Error('Sin conexión');
      const page=Number(/[?&]page=(\d+)/.exec(url)?.[1] || 1),filtered=url.includes('q=Persona');
      return{ok:status===200,status,json:async()=>status===200?{ok:true,page,pages:3,limit:25,total:filtered?1:61,total_dentro:72,from:1,to:filtered?1:25,puestos:[],checked_at:'2026-11-07 22:00:00',items:[{nombres:'<b>Persona</b>',rut:'12345678-5',estado:'INACTIVO',ultima_hora:'2001-02-03 23:00:00'}]}:{ok:false,error:'Debes iniciar sesión.'}};
    };
    new Function('window','document','fetch','URLSearchParams','AbortController','setTimeout','clearTimeout','Tablas','FechasChile',sources.inside)(d.window,d.document,fetch,runtime.URLSearchParams,runtime.AbortController,d.setTimer,d.clearTimer,d.table,dates);
    d.document.events.DOMContentLoaded();await flush();
    check(d.el('cntDentro').textContent===72 && d.el('tablaDentro').innerHTML.includes('Funcionario inactivo'),'El contador o estado administrativo no se conserva.');
    check(d.el('tablaDentro').innerHTML.includes('&lt;b&gt;') && calls[0].includes('page=1'),'La presencia no se pagina o interpreta HTML.');
    d.el('d_rut').value='Persona';d.el('d_rut').events.input();
    const debounce=[...d.timers.values()].find(timer=>timer.delay===300);debounce.fn();await flush();
    check(calls.at(-1).includes('q=Persona') && d.el('cntDentro').textContent===72,'La búsqueda modifica el total físico dentro.');
    check(d.el('d_fecha_text').textContent==='07-11-2026 22:00' && d.el('tablaDentro').innerHTML.includes('03-02-2001 23:00'),'La presencia no muestra las fechas locales.');
    const updated=d.el('d_fecha_text').textContent;fail=true;await d.window.cargarDentro();
    check(d.el('cntDentro').textContent==='—' && d.el('d_estado').textContent.includes('no está actualizada') && d.el('d_fecha_text').textContent===updated,'Una consulta fallida muestra un contador fiable.');
    fail=false;status=401;await d.window.cargarDentro();
    check(d.el('tablaDentro').innerHTML==='' && ![...d.timers.values()].some(timer=>timer.delay===15000),'Una sesión vencida conserva filas o sigue consultando.');
  }
  {
    const d=dom(),calls=[];let allow=false,role='GUARDIA';
    d.el('usersLimit').value='25';
    const fetch=async(url,options={})=>{
      calls.push({url,options});
      if(options.method==='POST'){role=JSON.parse(options.body).rol || role;return{ok:true,json:async()=>({ok:true})};}
      const page=Number(/[?&]page=(\d+)/.exec(url)?.[1] || 1);
      return{ok:true,json:async()=>({ok:true,items:[{id:10,username:'<b>Guardia</b>',rol:role,activo:1}],current_user_id:1,total:47,page,pages:2,limit:25,from:page===1?1:26,to:page===1?25:47})};
    };
    const api=new Function('window','document','fetch','URLSearchParams','Dialogos','Tablas',sources.users+';return {load:loadUsers,role:updateRole};')(d.window,d.document,fetch,runtime.URLSearchParams,{confirmar:async()=>allow},d.table);
    d.document.events.DOMContentLoaded();await flush();
    check(d.el('usersTable').innerHTML.includes('&lt;b&gt;'),'Los nombres de usuarios interpretan HTML.');
    d.el('usersSearch').value='guardia';d.el('usersRole').value='GUARDIA';
    d.el('usersFilters').events.submit({preventDefault(){}});await flush();
    check(calls.at(-1).url.includes('q=guardia') && calls.at(-1).url.includes('rol=GUARDIA'),'No se aplican los filtros de usuarios.');
    d.el('usersPage').value='2';await d.el('usersPage').events.change();
    check(d.el('usersSummary').textContent==='26–47 de 47 · Página 2 de 2','Usuarios no permite saltar de página.');
    await api.role(10,'AUDITOR');check(!calls.some(call=>call.options.method==='POST'),'Cancelar cambia el rol.');
    allow=true;await api.role(10,'AUDITOR');
    check(calls.some(call=>call.options.method==='POST' && JSON.parse(call.options.body).rol==='AUDITOR') && d.el('nu_msg').textContent.includes('Permisos actualizados'),'La confirmación no guarda o no comunica el cambio.');
  }
  for(const source of Object.values(sources)){new Function(source);checks++;}
  return{checks};
}
if(typeof module!=='undefined' && module.exports){
  module.exports=comprobarTablas;
  if(require.main===module){const fs=require('node:fs'),path=require('node:path'),root=path.resolve(__dirname,'..');
    const files={dates:'assets/js/fechas.js',tables:'assets/js/tablas.js',records:'assets/js/registros.js',inside:'assets/js/personal-dentro.js',users:'assets/js/config-gestion.js'};
    const sources=Object.fromEntries(Object.entries(files).map(([key,file])=>[key,fs.readFileSync(path.join(root,file),'utf8')]));
    comprobarTablas(sources,{URLSearchParams,AbortController}).then(result=>console.log('OK:',result.checks,'comprobaciones de tablas')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}