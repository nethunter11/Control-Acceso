async function comprobarOperacion(sources){
  let checks=0;const check=(yes,text)=>{if(!yes)throw new Error(text);checks++;};
  const fields=new Map();
  const el=id=>{if(!fields.has(id))fields.set(id,{value:'',textContent:'',innerHTML:'',disabled:false,dataset:{},events:{},setAttribute(){},focus(){},classList:{toggle(){}},addEventListener(name,fn){this.events[name]=fn;}});return fields.get(id);};
  const document={getElementById:el,events:{},addEventListener(name,fn){this.events[name]=fn;}};
  let presence='DENTRO',allow=false,refreshes=0,failMutation=false;
  const calls=[],confirmations=[];
  const person=()=>({ok:true,existe:true,activo:true,rut:'12345678-5',funcionario:{rut:'12345678-5',nombres:'<script>Persona</script>',unidad:'Unidad',grado:'Cabo'},presencia:presence,ultimo_movimiento:{tipo:presence==='DENTRO'?'ENTRADA':'SALIDA',fecha_hora:'2001-02-03 23:00:00',puesto:'Pórtico Norte',operador:'GUARDIA'},checked_at:'2026-11-07 22:00:00'});
  const window={cargarDentro:async()=>{refreshes++;}};
  const fetch=async(url,options)=>{
    const payload=JSON.parse(options.body);calls.push({url,payload});
    if(url.endsWith('registrar_evento.php')){
      if(failMutation)throw new Error('Sin conexión');
      presence=payload.tipo==='ENTRADA'?'DENTRO':'FUERA';
      return{ok:true,json:async()=>({ok:true,resultado:'APROBADO',guardado:true,rut:payload.rut,funcionario:person().funcionario,puesto:payload.puesto,fecha_hora:'2026-11-07 22:01:02',operador:'GUARDIA'})};
    }
    return{ok:true,json:async()=>person()};
  };
  new Function('window','document','fetch','Dialogos',sources.validation)(window,document,fetch,{confirmar:async(title,text)=>{confirmations.push({title,text});return allow;}});
  el('rut').value='12345678-5';el('puesto').value='Pórtico Sur';
  document.events.DOMContentLoaded();
  let prevented=false;el('rut').events.keydown({key:'Enter',preventDefault(){prevented=true;}});
  for(let i=0;i<16;i++)await Promise.resolve();
  check(prevented && calls.every(call=>call.url.endsWith('validar_rut.php')),'Enter guarda un movimiento.');
  check(el('fichaFuncionario').innerHTML.includes('Dentro') && el('fichaFuncionario').innerHTML.includes('2001-02-03'),'No aparece presencia e ingreso anterior.');
  check(el('fichaFuncionario').innerHTML.includes('&lt;script&gt;') && !el('fichaFuncionario').innerHTML.includes('<script>'),'La ficha interpreta nombres como HTML.');
  await window.registrar('SALIDA');
  check(!calls.some(call=>call.url.endsWith('registrar_evento.php')),'Cancelar registra una salida.');
  check(confirmations[0].text.includes('12345678-5') && confirmations[0].text.includes('Persona') && confirmations[0].text.includes('Pórtico Sur'),'La confirmación pierde la identidad o el puesto.');
  allow=true;await window.registrar('SALIDA');
  check(el('resultado').innerHTML.includes('event-card exit') && el('resultado').innerHTML.includes('2026-11-07 22:01:02'),'La salida no muestra su color u hora real.');
  check(el('resultado').innerHTML.includes('GUARDIA') && refreshes===1,'No se identifica al operador o actualiza personal dentro.');
  check(el('fichaFuncionario').innerHTML.includes('Fuera'),'La ficha no se actualiza tras salir.');
  const result=el('resultado').innerHTML;el('rut').value='87654321-0';el('rut').events.input();
  check(el('fichaFuncionario').innerHTML==='' && el('resultado').innerHTML===result,'Cambiar el RUT conserva una ficha incorrecta o borra el resultado anterior.');
  el('rut').value='12345678-5';failMutation=true;await window.registrar('ENTRADA');
  check(el('consultaMensaje').innerHTML.includes('Consulta el estado') && !el('btnEntrada').disabled,'El fallo invita a duplicar movimientos o bloquea los controles.');
  window.limpiar();check(el('rut').value==='' && el('resultado').textContent==='','Limpiar no reinicia la operación.');
  check(!sources.users.includes('alert(') && !sources.users.includes('prompt(') && !sources.validation.includes('confirm('),'Persisten diálogos nativos.');
  new Function(sources.users);new Function(sources.validation);
  return{checks};
}
if(typeof module!=='undefined' && module.exports){
  module.exports=comprobarOperacion;
  if(require.main===module){const fs=require('node:fs'),path=require('node:path'),root=path.resolve(__dirname,'..');
    comprobarOperacion({validation:fs.readFileSync(path.join(root,'assets/js/validacion.js'),'utf8'),users:fs.readFileSync(path.join(root,'assets/js/config-gestion.js'),'utf8')})
      .then(result=>console.log('OK:',result.checks,'comprobaciones de operación')).catch(error=>{console.error(error.message);process.exitCode=1;});
  }
}