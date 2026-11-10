async function comprobarRegistroPopup(source,dateSource) {
  let checks=0,now=0,nextId=0;
  const check=(yes,message)=>{if(!yes)throw new Error(message);checks++;};
  const timers=new Map(),parts=new Map(),events={};
  const part=selector=>{if(!parts.has(selector))parts.set(selector,{innerHTML:'',textContent:'',focus(){document.activeElement=this;}});return parts.get(selector);};
  const dialog={open:false,dataset:{},events:{},setAttribute(){},querySelector:part,
    addEventListener(name,fn){this.events[name]=fn;},showModal(){this.open=true;},close(){this.open=false;this.events.close();}};
  let created=0;
  const document={body:{append(){}},createElement(){created++;return dialog;}};
  const window={addEventListener(name,fn){events[name]=fn;}};
  new Function('window',dateSource)(window);
  const timeout=(fn,delay)=>{timers.set(++nextId,{fn,at:now+delay});return nextId;};
  const advance=ms=>{now+=ms;for(const [id,timer] of [...timers])if(timer.at<=now){timers.delete(id);timer.fn();}};
  new Function('window','document','setTimeout','clearTimeout','FechasChile',source)(window,document,timeout,id=>timers.delete(id),window.FechasChile);
  const show=(tono='entrada')=>window.RegistroPopup.mostrar({tono,titulo:'Registro',nombre:'<img>Persona',rut:'12345678-5',activo:true,campos:[['Unidad','<script>Unidad'],['Grado','Cabo']],estado:'Dentro del recinto',detalle:'Detalle <b>',fecha:'2026-11-09 12:34:56'});
  let closed=false;const first=show();first.then(()=>{closed=true;});
  check(dialog.open && window.RegistroPopup.abierta() && created===1,'El resultado no abre una ventana modal.');
  check(part('[data-popup-body]').innerHTML.includes('Registrado: 09-11-2026 12:34'),'La fecha del popup no usa el formato local.');
  check(part('[data-popup-body]').innerHTML.includes('&lt;img&gt;') && part('[data-popup-body]').innerHTML.includes('&lt;script&gt;') && !part('[data-popup-body]').innerHTML.includes('<img>'),'La ventana interpreta datos personales como HTML.');
  check(dialog.innerHTML.includes('4 segundos') && document.activeElement===part('[data-popup-close]'),'Falta el aviso de cierre automático o el foco de cierre.');
  advance(3999);await Promise.resolve();
  check(dialog.open && !closed,'La ventana se cierra antes de los cuatro segundos.');
  advance(1);await first;
  check(!dialog.open && closed && timers.size===0,'La ventana no se cierra al cumplir cuatro segundos.');
  check(part('[data-popup-body]').textContent==='','El cierre conserva datos personales visibles.');
  const second=show('salida');advance(1000);dialog.close();await second;
  check(timers.size===0 && !window.RegistroPopup.abierta(),'Cerrar manualmente conserva el temporizador.');
  const third=show('rechazo');advance(2999);
  check(dialog.open && dialog.dataset.tone==='rechazo' && part('[data-popup-body]').innerHTML.includes('RECHAZADO'),'Un temporizador anterior cierra el siguiente aviso o un rechazo parece aprobado.');
  advance(1001);await third;
  let replaced=false;const fourth=show();fourth.then(()=>{replaced=true;});advance(2000);
  const fifth=show('salida');await fourth;
  check(replaced && created===1 && dialog.open,'Reemplazar un aviso duplica ventanas o deja una promesa pendiente.');
  advance(2000);check(dialog.open,'Reemplazar no reinicia los cuatro segundos.');
  advance(2000);await fifth;check(!dialog.open,'El resultado reemplazado no se cierra.');
  const last=show();events.pagehide();await last;
  check(!dialog.open && timers.size===0,'Salir de la página deja una ventana o temporizador activo.');
  return{checks};
}
if(typeof module!=='undefined' && module.exports){
  module.exports=comprobarRegistroPopup;
  if(require.main===module){const fs=require('node:fs'),path=require('node:path');
    comprobarRegistroPopup(fs.readFileSync(path.resolve(__dirname,'../assets/js/registro-popup.js'),'utf8'),fs.readFileSync(path.resolve(__dirname,'../assets/js/fechas.js'),'utf8'))
      .then(result=>console.log('OK:',result.checks,'comprobaciones de ventanas temporales')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}
