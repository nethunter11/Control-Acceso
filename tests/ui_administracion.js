async function comprobarAdministracion(sources,runtime) {
  let checks=0;
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
    const calls=[],confirmations=[];
    let rows=[{id:1,rut:'12345678-5',nombre:'<img>Persona',estado:'DENTRO',fecha_hora_entrada:'2001-02-03 23:00:00',operador_entrada:null,operador_salida:null}];
    const fetch=async(url,init={})=>{
      calls.push({url,init});
      if(init.method==='POST'){rows=[];return{ok:true,json:async()=>({ok:true,resultado:'APROBADO'})};}
      return{ok:true,json:async()=>({ok:true,items:rows,total:rows.length,page:1,pages:1,dentro:rows.length,checked_at:'2026-11-07 23:00:00'})};
    };
    element('f_fecha').value='2026-11-07';
    const api=new Function('window','document','fetch','URLSearchParams','AbortController','setTimeout','clearTimeout','Dialogos','FechasChile',sources.visits+';return {view:cambiarVistaVisitas,load:cargarVisitas,exit:marcarSalida};')(
      window,document,fetch,runtime.URLSearchParams,runtime.AbortController,(fn,delay)=>{timers.set(++timerId,{fn,delay});return timerId;},id=>timers.delete(id),
      {confirmar:async(title,text)=>{confirmations.push(text);return true;}},{hoy:()=>'2026-11-07'});
    await api.view('dentro');
    check(!calls.at(-1).url.includes('fecha=') && element('f_fecha').disabled,'Dentro ahora filtra por el día.');
    check(element('tabla').innerHTML.includes('2001-02-03') && element('tabla').innerHTML.includes('&lt;img&gt;') && !element('tabla').innerHTML.includes('<img>'),'Fecha antigua o escape HTML incorrecto.');
    check(element('tabla').innerHTML.includes('Sin registro'),'Se inventa un operador antiguo.');
    await api.view('historial');
    check(calls.at(-1).url.includes('fecha=2026-11-07') && !element('f_fecha').disabled,'El historial no filtra por fecha.');
    await api.exit(1);
    check(confirmations[0].includes('12345678-5') && confirmations[0].includes('Persona'),'La salida no identifica a la visita.');
    check(calls.some(call=>call.init.method==='POST' && JSON.parse(call.init.body).id===1),'No se registra la salida.');
    window.events.pagehide();
    check(![...timers.values()].some(timer=>timer.delay===15000),'La pantalla cerrada sigue actualizando visitas.');
  }
  {
    const {document,element}=dom();
    const calls=[],confirmations=[];
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
    new Function('document','fetch','URLSearchParams','Dialogos','TextDecoder',sources.staff)(document,fetch,runtime.URLSearchParams,{confirmar:async(title,text)=>{confirmations.push(text);return true;}},runtime.TextDecoder);
    document.events.DOMContentLoaded();await flush();
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
    const files={session:'assets/js/sesion.js',visits:'assets/js/visitas.js',staff:'assets/js/funcionarios.js',dialogs:'assets/js/dialogos.js',users:'assets/js/config-gestion.js'};
    const sources=Object.fromEntries(Object.entries(files).map(([key,file])=>[key,fs.readFileSync(path.join(root,file),'utf8')]));
    comprobarAdministracion(sources,{URL,Headers,URLSearchParams,AbortController,TextDecoder}).then(result=>console.log('OK:',result.checks,'comprobaciones de administración')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}