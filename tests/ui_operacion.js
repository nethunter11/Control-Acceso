async function comprobarOperacion(sources){
  let checks=0;const check=(yes,text)=>{if(!yes)throw new Error(text);checks++;};
  const calendar={};new Function('window',sources.dates)(calendar);const dates=calendar.FechasChile;
  const fields=new Map();
  const el=id=>{
    if(!fields.has(id))fields.set(id,{
      value:'',innerHTML:'',disabled:false,open:false,shown:0,dataset:{},events:{},
      get textContent(){return this.innerHTML;},set textContent(value){this.innerHTML=value;},
      setAttribute(){},focus(){document.activeElement=this;},classList:{toggle(){}},
      addEventListener(name,fn){this.events[name]=fn;},
      showModal(){if(this.open)throw new Error('La ventana ya está abierta.');this.open=true;this.shown++;},
      close(){this.open=false;this.events.close?.();}
    });
    return fields.get(id);
  };
  const document={getElementById:el,events:{},addEventListener(name,fn){this.events[name]=fn;}};
  let presence='DENTRO',allow=false,refreshes=0,failMutation=false,rejectMutation=false,lookup='found';
  const calls=[],confirmations=[],popups=[];
  let popupOpen=false,popupResolve=null;
  const closePopup=async()=>{popupOpen=false;const resolve=popupResolve;popupResolve=null;resolve?.();for(let i=0;i<8;i++)await Promise.resolve();};
  const person=()=>({ok:true,existe:true,activo:true,rut:'12345678-5',funcionario:{rut:'12345678-5',nombres:'<script>Persona</script>',unidad:'Unidad',grado:'Cabo'},presencia:presence,ultimo_movimiento:{tipo:presence==='DENTRO'?'ENTRADA':'SALIDA',fecha_hora:'2001-02-03 23:00:00',puesto:'Pórtico Norte',operador:'GUARDIA'},checked_at:'2026-11-07 22:00:00'});
  const window={cargarDentro:async()=>{refreshes++;}};
  const fetch=async(url,options)=>{
    const payload=JSON.parse(options.body);calls.push({url,payload});
    if(url.endsWith('registrar_evento.php')){
      if(failMutation)throw new Error('Sin conexión');
      if(rejectMutation)return{ok:true,json:async()=>({ok:true,resultado:'RECHAZADO',guardado:false,motivo:'Movimiento duplicado',rut:payload.rut,funcionario:person().funcionario})};
      presence=payload.tipo==='ENTRADA'?'DENTRO':'FUERA';
      return{ok:true,json:async()=>({ok:true,resultado:'APROBADO',guardado:true,rut:payload.rut,funcionario:person().funcionario,puesto:payload.puesto,fecha_hora:'2026-11-07 22:01:02',operador:'GUARDIA'})};
    }
    if(lookup==='network')throw new Error('Sin conexión');
    const data=lookup==='missing'?{ok:true,existe:false,rut:'12345678-5'}:lookup==='invalid'?{ok:false,error:'RUT inválido'}:person();
    if(lookup==='inactive'){data.activo=false;data.presencia='SIN_MOVIMIENTOS';data.ultimo_movimiento=null;}
    return{ok:true,json:async()=>data};
  };
  new Function('window','document','fetch','Dialogos','RegistroPopup','FechasChile',sources.validation)(window,document,fetch,{confirmar:async(title,text)=>{confirmations.push({title,text});return allow;}},{abierta:()=>popupOpen,mostrar:data=>{popups.push(data);popupOpen=true;return new Promise(resolve=>{popupResolve=resolve;});}},dates);
  el('rut').value='12345678-5';el('puesto').value='Pórtico Sur';
  document.events.DOMContentLoaded();
  let prevented=false;el('rut').events.keydown({key:'Enter',preventDefault(){prevented=true;}});
  for(let i=0;i<16;i++)await Promise.resolve();
  check(prevented && calls.every(call=>call.url.endsWith('validar_rut.php')),'Enter guarda un movimiento.');
  const card=el('fichaFuncionario').innerHTML;
  check(['Persona','12345678-5','Unidad','Cabo','Dentro'].every(text=>card.includes(text)) && card.indexOf('Cabo')<card.indexOf('Estado de acceso'),'Los datos personales no aparecen antes del estado de acceso.');
  check(!card.includes('2001-02-03') && !card.includes('Último movimiento aprobado') && card.includes('Consultado: 07-11-2026 22:00'),'La ficha conserva el último movimiento o pierde la hora de consulta.');
  check(el('fichaFuncionario').innerHTML.includes('&lt;script&gt;') && !el('fichaFuncionario').innerHTML.includes('<script>'),'La ficha interpreta nombres como HTML.');
  check(el('consultaFuncionario').open && document.activeElement===el('btnCerrarConsulta'),'La consulta no abre la ventana ni sitúa el foco en su cierre.');
  check(!el('btnConsultar').disabled && !el('rut').disabled,'Los controles quedan bloqueados después de consultar.');
  const count=calls.length;await window.soloConsultar();await window.registrar('ENTRADA');
  check(calls.length===count,'Se inicia otra operación mientras la consulta está abierta.');
  el('consultaFuncionario').close();
  check(document.activeElement===el('rut') && el('rut').value==='12345678-5' && el('fichaFuncionario').innerHTML==='','Cerrar no devuelve el foco al RUT o conserva datos obsoletos.');
  await window.registrar('SALIDA');
  check(!calls.some(call=>call.url.endsWith('registrar_evento.php')) && popups.length===0,'Cancelar registra una salida o muestra un éxito.');
  check(!el('consultaFuncionario').open && el('consultaFuncionario').shown===1,'Registrar abre también la ventana informativa.');
  check(confirmations[0].text.includes('12345678-5') && confirmations[0].text.includes('Persona') && confirmations[0].text.includes('Pórtico Sur'),'La confirmación pierde la identidad o el puesto.');
  allow=true;await window.registrar('SALIDA');
  check(el('resultado').innerHTML.includes('event-card exit') && el('resultado').innerHTML.includes('07-11-2026 22:01'),'La salida no muestra su color u hora real.');
  check(el('resultado').innerHTML.includes('GUARDIA') && refreshes===1,'No se identifica al operador o actualiza personal dentro.');
  check(el('fichaFuncionario').innerHTML.includes('Fuera'),'La ficha no se actualiza tras salir.');
  check(popups.length===1 && popups[0].tono==='salida' && popups[0].estado==='Fuera del recinto' && popups[0].fecha==='2026-11-07 22:01:02','La salida no muestra su aviso con la hora guardada.');
  check(popups[0].nombre.includes('Persona') && popups[0].rut==='12345678-5' && popups[0].campos[0][1]==='Unidad' && popups[0].campos[1][1]==='Cabo','El resultado pierde los datos personales.');
  check(el('rut').value==='12345678-5','Se limpió el RUT antes de cerrar el popup.');
  const receipt=el('resultado').innerHTML;await closePopup();
  check(el('rut').value==='' && document.activeElement===el('rut') && el('fichaFuncionario').innerHTML==='' && el('resultado').innerHTML===receipt,'El cierre no prepara el siguiente RUT o pierde el comprobante de la operación.');
  const result=el('resultado').innerHTML;el('rut').value='87654321-0';el('rut').events.input();
  check(el('fichaFuncionario').innerHTML==='' && el('resultado').innerHTML===result,'Cambiar el RUT conserva una ficha incorrecta o borra el resultado anterior.');
  el('rut').value='12345678-5';failMutation=true;await window.registrar('ENTRADA');
  check(el('rut').value==='12345678-5' && el('consultaMensaje').innerHTML.includes('Consulta el estado') && !el('btnEntrada').disabled,'El fallo invita a duplicar movimientos o bloquea los controles.');
  for(const state of ['missing','invalid','network']){
    lookup=state;await window.soloConsultar();
    check(!el('consultaFuncionario').open && el('fichaFuncionario').innerHTML==='' && el('consultaMensaje').innerHTML!=='','Una consulta fallida abre una ficha anterior: '+state);
  }
  lookup='inactive';await window.soloConsultar();
  check(el('consultaFuncionario').open && el('fichaFuncionario').innerHTML.includes('INACTIVO') && el('fichaFuncionario').innerHTML.includes('Sin movimientos aprobados') && !el('fichaFuncionario').innerHTML.includes('2001-02-03'),'Se inventa presencia o se oculta el estado inactivo.');
  el('consultaFuncionario').close();lookup='found';
  const previous=calls.length;
  await Promise.all([window.soloConsultar(),window.soloConsultar()]);
  check(calls.length===previous+1 && el('consultaFuncionario').open,'La doble consulta duplica peticiones o ventanas.');
  el('consultaFuncionario').close();
  failMutation=false;await window.registrar('ENTRADA');
  check(popups.at(-1).tono==='entrada' && popups.at(-1).estado==='Dentro del recinto','La entrada no muestra su aviso de ingreso.');
  await closePopup();
  check(el('rut').value==='' && document.activeElement===el('rut'),'La entrada aprobada no prepara el siguiente registro.');
  el('rut').value='12345678-5';
  rejectMutation=true;await window.registrar('ENTRADA');
  check(popups.at(-1).tono==='rechazo' && popups.at(-1).detalle==='Movimiento duplicado' && !popups.at(-1).fecha,'El rechazo se muestra como aprobado o inventa una fecha de registro.');
  await closePopup();
  check(el('rut').value==='12345678-5' && document.activeElement===el('rut'),'Cerrar un rechazo borra el RUT que se debe revisar.');
  window.limpiar();check(el('rut').value==='' && el('resultado').textContent==='','Limpiar no reinicia la operación.');
  check(!sources.users.includes('alert(') && !sources.users.includes('prompt(') && !sources.validation.includes('confirm('),'Persisten diálogos nativos.');
  new Function(sources.users);new Function(sources.validation);
  return{checks};
}
if(typeof module!=='undefined' && module.exports){
  module.exports=comprobarOperacion;
  if(require.main===module){const fs=require('node:fs'),path=require('node:path'),root=path.resolve(__dirname,'..');
    comprobarOperacion({dates:fs.readFileSync(path.join(root,'assets/js/fechas.js'),'utf8'),validation:fs.readFileSync(path.join(root,'assets/js/validacion.js'),'utf8'),users:fs.readFileSync(path.join(root,'assets/js/config-gestion.js'),'utf8')})
      .then(result=>console.log('OK:',result.checks,'comprobaciones de operación')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}