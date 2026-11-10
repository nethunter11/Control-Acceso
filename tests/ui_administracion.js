async function comprobarAdministracion(sources,runtime) {
  let checks=0;
  const calendar={};new Function('window',sources.dates)(calendar);const dates=calendar.FechasChile;
  const tablesFor=document=>{const window={};new Function('window','document',sources.tables)(window,document);return window.Tablas;};
  const check=(condition,message)=>{if(!condition)throw new Error(message);checks++;};
  const flush=async()=>{for(let i=0;i<12;i++)await Promise.resolve();};
  function dom() {
    const fields=new Map();
    const element=id=>{
      if(!fields.has(id))fields.set(id,{id,value:'',textContent:'',innerHTML:'',disabled:false,hidden:false,readOnly:false,files:[],dataset:{},style:{},events:{},
        classList:{toggle(){}},setAttribute(){},focus(){},scrollIntoView(){},reset(){},addEventListener(name,fn){this.events[name]=fn;}});
      return fields.get(id);
    };
    return {fields,element,document:{hidden:false,events:{},getElementById:element,addEventListener(name,fn){this.events[name]=fn;}}};
  }
  {
    const {document,element}=dom();
    element('appSession').textContent=JSON.stringify({csrf:'test-token',expires_at:100});
    const calls=[],redirects=[],intervals=[];
    let nextStatus=200;
    const window={location:{origin:'http://localhost:8081',href:'http://localhost:8081/Control-Acceso/index.php',assign:url=>redirects.push(url)},addEventListener(){},
      fetch:async(input,init={})=>{calls.push({input,init});return{status:nextStatus,ok:nextStatus===200,json:async()=>({expires_at:200})};}};
    new Function('window','document','URL','Headers','setInterval',sources.session)(window,document,runtime.URL,runtime.Headers,fn=>intervals.push(fn));
    await intervals[0]();
    check(calls.at(-1).init.method==='GET','Una consulta automática renueva la sesión.');
    document.events.keydown({isTrusted:false});await intervals[0]();
    check(calls.at(-1).init.method==='GET','Actividad simulada renueva la sesión.');
    document.events.pointerdown({isTrusted:true});await intervals[0]();
    check(calls.at(-1).init.method==='POST' && calls.at(-1).init.headers.get('X-CSRF-Token')==='test-token','La actividad real no envía CSRF.');
    await intervals[0]();check(calls.at(-1).init.method==='GET','La actividad se repite sin intervención.');
    await window.fetch('https://example.com/data',{method:'POST'});
    check(!calls.at(-1).init.headers,'El token sale hacia otro origen.');
    nextStatus=401;await window.fetch('/Control-Acceso/api/personal_dentro.php');
    check(redirects.length===1 && redirects[0].includes('login.php'),'La sesión vencida no vuelve al acceso.');
  }
  {
    const {document,element}=dom(); const timers=new Map();let timerId=0;
    const window={events:{},addEventListener(name,fn){this.events[name]=fn;}};
    const calls=[],confirmations=[],popups=[];
    let allowExit=false,rejectExit=false,popupOpen=false;
    let rows=[{id:1,rut:'12345678-5',nombre:'<img>Persona',telefono:'+56 9 1234 5678',modo:'VEHICULO',patente:'BB1234',estado:'DENTRO',fecha_hora_entrada:'2001-02-03 23:00:00',operador_entrada:null,operador_salida:null}];
    const fetch=async(url,init={})=>{
      calls.push({url,init});
      if(init.method==='POST'){
        if(url.endsWith('visitas_entrada.php')){
          const saved=JSON.parse(init.body);rows=[];
          return{ok:true,json:async()=>({ok:true,resultado:'APROBADO',nombre:saved.nombre,rut:'12345678-5',telefono:saved.telefono,modo:saved.modo,patente:saved.patente,fecha_hora_entrada:'2026-11-09 12:34:56'})};
        }
        if(rejectExit)return{ok:true,json:async()=>({ok:true,resultado:'RECHAZADO',motivo:'La visita ya tiene salida registrada.',guardado:false})};
        rows=[];return{ok:true,json:async()=>({ok:true,resultado:'APROBADO',fecha_hora_salida:'2026-11-09 13:45:56'})};
      }
      return{ok:true,json:async()=>({ok:true,items:rows,total:rows.length,page:1,pages:1,dentro:rows.length,checked_at:'2026-11-07 23:00:00'})};
    };
    let scroller=null,tableHtml='';
    element('tabla').querySelector=()=>scroller;
    Object.defineProperty(element('tabla'),'innerHTML',{get:()=>tableHtml,set:value=>{tableHtml=value;scroller={scrollLeft:0,focus(){document.activeElement=this;}};}});
    element('f_fecha').value='2026-11-07';
    const api=new Function('window','document','fetch','URLSearchParams','AbortController','setTimeout','clearTimeout','Dialogos','FechasChile','Tablas','RegistroPopup',sources.visits+';return {view:cambiarVistaVisitas,load:cargarVisitas,exit:marcarSalida,entry:registrarEntrada,mode:setModoV,expand:ampliarTablaVisitas};')(
      window,document,fetch,runtime.URLSearchParams,runtime.AbortController,(fn,delay)=>{timers.set(++timerId,{fn,delay});return timerId;},id=>timers.delete(id),
      {confirmar:async(title,text)=>{confirmations.push(text);return allowExit;}},{...dates,hoy:()=>'2026-11-07'},tablesFor(document),{abierta:()=>popupOpen,mostrar:data=>{popups.push(data);return Promise.resolve();}});
    await api.view('dentro');
    check(!calls.at(-1).url.includes('fecha=') && element('f_fecha').disabled,'Dentro ahora filtra por el día.');
    check(element('tabla').innerHTML.includes('03-02-2001 23:00') && element('tabla').innerHTML.includes('&lt;img&gt;') && !element('tabla').innerHTML.includes('<img>'),'Fecha antigua o escape HTML incorrecto.');
    check(element('tabla').innerHTML.includes('Sin registro'),'Se inventa un operador antiguo.');
    scroller.scrollLeft=180;document.activeElement=scroller;await api.load();
    check(scroller.scrollLeft===180 && document.activeElement===scroller,'Actualizar pierde el desplazamiento horizontal o el foco de la tabla.');
    element('v_nombre').value='Nombre en edición';api.expand();
    check(element('visitFormPanel').hidden && element('visitsExpandLabel').textContent==='Mostrar formulario','Ampliar no libera el espacio del formulario.');
    api.expand();
    check(!element('visitFormPanel').hidden && element('v_nombre').value==='Nombre en edición','Restaurar el formulario pierde los datos ingresados.');
    await api.view('historial');
    check(calls.at(-1).url.includes('fecha=2026-11-07') && !element('f_fecha').disabled,'El historial no filtra por fecha.');
    await api.exit(1);
    check(!calls.some(call=>call.init.method==='POST') && popups.length===0 && !element('btnSalidaVisita_1').disabled,'Cancelar una salida guarda un movimiento, abre un resultado o deja bloqueado el botón.');
    popupOpen=true;const before=calls.length;await api.exit(1);
    check(calls.length===before && confirmations.length===1,'Se inicia otra salida mientras hay un aviso abierto.');
    popupOpen=false;allowExit=true;rejectExit=true;await api.exit(1);
    check(popups.at(-1).tono==='rechazo' && !popups.at(-1).fecha && !element('btnSalidaVisita_1').disabled,'Una salida rechazada parece aprobada, inventa una hora o bloquea el botón.');
    popups.length=0;rejectExit=false;
    await Promise.all([api.exit(1),api.exit(1)]);
    check(calls.filter(call=>call.url.endsWith('visitas_salida.php')).length===2,'La doble pulsación duplica la salida.');
    check(popups.length===1 && popups[0].tono==='salida' && popups[0].estado==='Fuera del recinto' && popups[0].fecha==='2026-11-09 13:45:56','La salida de visita no muestra el popup con el estado y la hora guardada.');
    check(popups[0].nombre==='<img>Persona' && popups[0].rut==='12345678-5' && popups[0].campos.some(([label,value])=>label==='Patente' && value==='BB1234'),'La salida pierde la identidad o los datos del vehículo.');
    popups.length=0;
    check(confirmations[0].includes('12345678-5') && confirmations[0].includes('Persona'),'La salida no identifica a la visita.');
    check(calls.some(call=>call.init.method==='POST' && JSON.parse(call.init.body).id===1),'No se registra la salida.');
    element('v_nombre').value='<img>Nueva visita';element('v_rut').value='12.345.678-5';element('v_tel').value='+56 9 1234 5678';
    api.mode('VEHICULO');element('v_pat').value='AA1234';
    await Promise.all([api.entry(),api.entry()]);
    check(calls.filter(call=>call.url.endsWith('visitas_entrada.php')).length===1,'La doble pulsación duplica el ingreso.');
    check(popups.length===1 && popups[0].tono==='entrada' && popups[0].nombre==='<img>Nueva visita' && popups[0].rut==='12345678-5','El registro de visita no muestra el aviso con la identidad guardada.');
    check(popups[0].fecha==='2026-11-09 12:34:56' && popups[0].campos.some(([label,value])=>label==='Patente' && value==='AA1234'),'La visita pierde la hora guardada o los datos del vehículo.');
    check(!element('btnVisitaEntrada').disabled && element('v_nombre').value==='','La visita aprobada deja el formulario bloqueado o sin limpiar.');
    window.events.pagehide();
    check(![...timers.values()].some(timer=>timer.delay===15000),'La pantalla cerrada sigue actualizando visitas.');
  }
  {
    const {document,element}=dom();
    const calls=[],confirmations=[],popups=[];
    let previewErrors=false;
    const row={rut:'12345678-5',nombres:'<script>Persona</script>',unidad:'Unidad',grado:'Cabo',estado:'ACTIVO',revision:'revision-segura'};
    const fetch=async(url,init={})=>{
      const payload=init.body?JSON.parse(init.body):null;calls.push({url,payload});
      let data={ok:true,items:[row],total:1,page:1,pages:1};
      if(payload?.accion==='preview')data={ok:true,id:previewErrors?null:'preview-del-servidor',preview:{crear:1,actualizar:0,errores:previewErrors?1:0,rows:[{fila:2,data:row,cambios:[],accion:'Crear',error:previewErrors?'RUT repetido':null}]}};
      if(payload?.accion==='importar')data={ok:true,creados:1,actualizados:0};
      if(payload?.accion==='guardar')data={ok:true};
      return{ok:true,json:async()=>data};
    };
    new Function('document','fetch','URLSearchParams','Dialogos','TextDecoder','Tablas',sources.staff)(document,fetch,runtime.URLSearchParams,{confirmar:async(title,text)=>{confirmations.push(text);return true;}},runtime.TextDecoder,tablesFor(document));
    document.events.DOMContentLoaded();await flush();
    element('staffLimit').value='50';await element('staffLimit').events.change();
    check(calls.at(-1).url.includes('limit=50'),'Funcionarios no aplica filas por página.');
    check(element('staffTable').innerHTML.includes('&lt;script&gt;') && !element('staffTable').innerHTML.includes('<script>'),'Los nombres de funcionarios no se escapan.');
    element('staffTable').events.click({target:{closest:()=>({dataset:{edit:row.rut}})}});
    check(element('sf_rut').readOnly && element('sf_nombres').value===row.nombres,'Editar permite cambiar el RUT.');
    element('sf_estado').value='INACTIVO';
    await element('staffForm').events.submit({preventDefault(){}});
    const saved=calls.find(call=>call.payload?.accion==='guardar').payload;
    check(saved.revision==='revision-segura' && saved.estado==='INACTIVO' && confirmations[0].includes(row.rut),'La edición pierde la revisión o confirma sin identidad.');
    element('staffCsv').files=[{size:20,arrayBuffer:async()=>new Uint8Array([114,117,116]).buffer}];
    element('staffImportMode').value='crear';
    await element('staffImportForm').events.submit({preventDefault(){}});
    check(!element('staffImportApply').disabled && !element('staffPreviewPanel').hidden,'La vista previa válida no permite confirmar.');
    element('staffCsv').events.change();
    check(element('staffImportApply').disabled && element('staffPreviewPanel').hidden,'Cambiar el archivo conserva una vista previa anterior.');
    await element('staffImportForm').events.submit({preventDefault(){}});
    await element('staffImportApply').events.click();
    const applied=calls.find(call=>call.payload?.accion==='importar').payload;
    check(applied.id==='preview-del-servidor' && !('csv' in applied),'La confirmación vuelve a enviar filas modificables.');
    check(element('staffImportMessage').textContent.includes('Importación completa'),'No se informa el resultado de importar.');
    previewErrors=true;
    await element('staffImportForm').events.submit({preventDefault(){}});
    check(element('staffImportApply').disabled && element('staffPreviewTable').innerHTML.includes('RUT repetido'),'Se permite importar una vista con errores.');
  }
  {
    const parts={h2:{},p:{},label:{},input:{value:'',focus(){}}};
    const dialog={open:false,returnValue:'',style:{},setAttribute(){},querySelector:key=>parts[key],showModal(){this.open=true;}};
    const window={},document={body:{append(){}},createElement:()=>dialog};
    new Function('window','document',sources.dialogs)(window,document);
    const password=window.Dialogos.password('Restablecer','Usuario <script>');
    check(dialog.innerHTML.includes('type="password"') && !parts.input.hidden && parts.p.textContent==='Usuario <script>','El diálogo expone la contraseña o interpreta HTML.');
    parts.input.value='Temporal-123';dialog.returnValue='save';dialog.open=false;dialog.onclose();
    check(await password==='Temporal-123' && parts.input.value==='','La contraseña no se retira del diálogo.');
    const confirm=window.Dialogos.confirmar('Salida','Visita 12345678-5');
    check(parts.input.hidden && parts.input.disabled,'La confirmación incluye una contraseña.');
    dialog.returnValue='cancel';dialog.open=false;dialog.onclose();
    check(await confirm===null,'Cancelar confirma una acción.');
  }
  for(const source of Object.values(sources)){new Function(source);checks++;}
  return {checks};
}
if(typeof module!=='undefined' && module.exports) {
  module.exports=comprobarAdministracion;
  if(require.main===module) {
    const fs=require('node:fs'),path=require('node:path'),root=path.resolve(__dirname,'..');
    const files={dates:'assets/js/fechas.js',session:'assets/js/sesion.js',visits:'assets/js/visitas.js',staff:'assets/js/funcionarios.js',dialogs:'assets/js/dialogos.js',users:'assets/js/config-gestion.js',tables:'assets/js/tablas.js'};
    const sources=Object.fromEntries(Object.entries(files).map(([key,file])=>[key,fs.readFileSync(path.join(root,file),'utf8')]));
    comprobarAdministracion(sources,{URL,Headers,URLSearchParams,AbortController,TextDecoder}).then(result=>console.log('OK:',result.checks,'comprobaciones de administración')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}