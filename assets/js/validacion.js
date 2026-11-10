(() => {
  'use strict';
  const el=id=>document.getElementById(id);
  const escape=value=>String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let busy=false,mode='PEATONAL';
  const controls=['rut','puesto','btnEntrada','btnSalida','btnConsultar','btnLimpiar','btnModoPeatonal','btnModoVehiculo'];
  function lock(value) {busy=value;for(const id of controls) if(el(id))el(id).disabled=value;el('fichaFuncionario').setAttribute('aria-busy',String(value));}
  function note(text,kind='info') {el('consultaMensaje').innerHTML='<div class="alert alert-'+kind+' mb-0">'+escape(text)+'</div>';}
  async function post(path,payload) {
    const response=await fetch('/Control-Acceso/api/'+path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await response.json();
    if(!response.ok) throw new Error(data.error || 'No se pudo completar la consulta.');
    return data;
  }
  function renderCard(data) {
    if(!data.ok || !data.existe) {el('fichaFuncionario').innerHTML='';return;}
    const person=data.funcionario;
    const image=['/Control-Acceso/img/funcionarios/Imagen_hombre.png','/Control-Acceso/img/funcionarios/Imagen_mujer.png'].includes(person.imagen_url)?person.imagen_url:null;
    const inside=data.presencia==='DENTRO',outside=data.presencia==='FUERA';
    const presence=inside?'Dentro del recinto':outside?'Fuera del recinto':'Sin movimientos aprobados';
    const presenceClass=inside?'is-inside':outside?'is-outside':'is-unknown';
    el('fichaFuncionario').innerHTML=`
      <section class="consulta-identidad" aria-label="Datos personales">
        <div class="consulta-perfil">
          <div class="consulta-retrato" ${image?'':'role="img" aria-label="Sin imagen disponible"'}>
            <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
            ${image?'<img class="consulta-foto" src="'+escape(image)+'" alt="Imagen de referencia del funcionario" width="156" height="195" decoding="async">':''}
          </div>
          <div class="consulta-informacion">
        <div class="consulta-persona">
          <div><div class="consulta-identidad-label">Datos del funcionario</div><h3>${escape(person.nombres)}</h3></div>
          <span class="func-badge ${data.activo?'ok':'bad'}"><i class="bi ${data.activo?'bi-check-circle':'bi-x-circle'}" aria-hidden="true"></i>${data.activo?'ACTIVO':'INACTIVO'}</span>
        </div>
        <p class="consulta-rut"><span>RUT</span><strong class="mono">${escape(person.rut)}</strong></p>
        <dl class="consulta-datos">
          <div><dt>Unidad</dt><dd>${escape(person.unidad || 'Sin registro')}</dd></div>
          <div><dt>Grado</dt><dd>${escape(person.grado || 'Sin registro')}</dd></div>
        </dl>
          </div>
        </div>
      </section>
      <div class="consulta-presencia ${presenceClass}">
        <i class="bi ${inside?'bi-person-check':outside?'bi-box-arrow-right':'bi-dash-circle'}" aria-hidden="true"></i>
        <div><div class="func-label">Estado de acceso</div><div class="consulta-presencia-valor">${presence}</div><p>Según los movimientos aprobados registrados.</p></div>
      </div>
      <p class="consulta-actualizacion"><i class="bi bi-clock" aria-hidden="true"></i>Consultado: ${escape(FechasChile.fechaHora(data.checked_at))}</p>`;
    el('fichaFuncionario').querySelector?.('.consulta-foto')?.addEventListener('error',event=>{const picture=event.currentTarget;picture.hidden=true;picture.parentElement.setAttribute('role','img');picture.parentElement.setAttribute('aria-label','Sin imagen disponible');});
  }
  async function consult(rut) {
    const data=await post('validar_rut.php',{rut});
    renderCard(data);
    if(!data.ok) note(data.error || 'No se pudo consultar.','danger');
    else if(!data.existe) note('RUT no registrado: '+data.rut,'warning');
    else el('consultaMensaje').textContent='';
    return data;
  }
  window.soloConsultar=async function () {
    if(busy || el('consultaFuncionario').open || RegistroPopup.abierta())return;
    const rut=el('rut').value.trim();
    if(!rut){note('Ingresa un RUT.','warning');el('rut').focus();return;}
    let data;
    lock(true);
    try {data=await consult(rut);}
    catch(error){el('fichaFuncionario').innerHTML='';note(error.message,'danger');}
    finally {lock(false);}
    if(data?.ok && data.existe) {
      el('consultaFuncionario').showModal();
      el('btnCerrarConsulta').focus();
    }
  };
  function renderResult(data,requested) {
    const ok=data.resultado==='APROBADO',exit=requested.tipo==='SALIDA';
    const tone=ok?(exit?'exit':'entry'):'bad';
    const title=(exit?'Salida':'Entrada')+(ok?' registrada':' rechazada');
    const name=data.funcionario?.nombres || 'Persona no registrada';
    const date=data.fecha_hora?FechasChile.fechaHora(data.fecha_hora):(data.guardado===false?'Sin nuevo registro':'Fecha no disponible');
    el('resultado').innerHTML='<div class="event-card '+tone+'"><div class="event-head"><div><div class="func-label mb-1">Última operación</div><div class="event-title"><i class="bi '+(ok?(exit?'bi-box-arrow-right':'bi-box-arrow-in-right'):'bi-x-circle')+'" aria-hidden="true"></i> '+title+'</div></div><span class="event-badge '+(ok?(exit?'exit':'ok'):'bad')+'">'+(ok?'APROBADO':'RECHAZADO')+'</span></div><div class="event-value">'+escape(name)+'</div><div class="mono mt-1">'+escape(data.rut || requested.rut)+'</div><div class="event-subtitle">'+escape(data.puesto || requested.puesto)+' · '+(requested.modo==='VEHICULO'?'Vehicular':'Peatonal')+'</div><div class="small mt-2">'+escape(date)+(data.guardado && data.operador?' · '+escape(data.operador):'')+'</div>'+(data.motivo?'<div class="event-motivo">'+escape(data.motivo)+'</div>':'')+'</div>';
  }
  window.registrar=async function (type) {
    if(busy || el('consultaFuncionario').open || RegistroPopup.abierta() || !['ENTRADA','SALIDA'].includes(type))return;
    const payload={rut:el('rut').value.trim(),tipo:type,puesto:el('puesto').value,modo:mode};
    if(!payload.rut){note('Ingresa un RUT.','warning');el('rut').focus();return;}
    let popupClosed,clearRutAfterClose=false;
    lock(true);
    try {
      const person=await consult(payload.rut);
      if(!person.ok || !person.existe)return;
      payload.rut=person.rut;
      const action=type==='ENTRADA'?'entrada':'salida';
      const confirmed=await Dialogos.confirmar('Confirmar '+action,person.funcionario.nombres+' · RUT '+person.rut+' · '+payload.puesto+' · '+(mode==='VEHICULO'?'Vehicular':'Peatonal'));
      if(!confirmed)return;
      const data=await post('registrar_evento.php',payload);
      if(!data.ok){note(data.error || 'No se pudo registrar el movimiento.','danger');return;}
      renderResult(data,payload);
      const approved=data.resultado==='APROBADO',exit=type==='SALIDA';
      clearRutAfterClose=approved && data.guardado===true;
      const funcionario=data.funcionario || person.funcionario;
      popupClosed=RegistroPopup.mostrar({
        titulo:(exit?'Salida':'Entrada')+(approved?' registrada':' rechazada'),subtitulo:'Registro de funcionario',
        tono:approved?(exit?'salida':'entrada'):'rechazo',etiqueta:'Datos del funcionario',
        funcionario:true,imagen_url:person.funcionario.imagen_url,
        nombre:funcionario.nombres,rut:data.rut || payload.rut,activo:funcionario.estado?funcionario.estado==='ACTIVO':person.activo,
        campos:[['Unidad',funcionario.unidad],['Grado',funcionario.grado]],
        estado:approved?(exit?'Fuera del recinto':'Dentro del recinto'):'Movimiento rechazado',
        detalle:data.motivo || (approved?'Movimiento registrado correctamente.':'No se registró el movimiento.'),fecha:data.guardado?data.fecha_hora:null
      });
      try {await consult(payload.rut);} catch {el('fichaFuncionario').innerHTML='';note('La operación fue respondida; no se pudo actualizar la ficha. Consulta nuevamente.','warning');}
      if(typeof window.cargarDentro==='function')await window.cargarDentro();
    } catch(error){el('fichaFuncionario').innerHTML='';note(error.message+' Consulta el estado antes de volver a registrar.','danger');}
    finally {
      lock(false);
      if(popupClosed)popupClosed.then(()=>{
        if(RegistroPopup.abierta() || busy)return;
        if(clearRutAfterClose){el('rut').value='';el('fichaFuncionario').textContent='';}
        el('rut').focus();
      });
    }
  };
  window.setModo=function (value) {
    if(busy || !['PEATONAL','VEHICULO'].includes(value))return;
    mode=value;
    for(const [id,current] of [['btnModoPeatonal','PEATONAL'],['btnModoVehiculo','VEHICULO']]){el(id).classList.toggle('active',current===value);el(id).setAttribute('aria-pressed',String(current===value));}
  };
  window.limpiar=function () {
    if(busy)return;
    el('rut').value='';for(const id of ['consultaMensaje','fichaFuncionario','resultado'])el(id).textContent='';el('rut').focus();
  };
  document.addEventListener('DOMContentLoaded',()=>{
    el('consultaFuncionario').addEventListener('close',()=>{el('fichaFuncionario').textContent='';el('rut').focus();});
    el('rut').addEventListener('input',()=>{el('fichaFuncionario').innerHTML='';el('consultaMensaje').textContent='';});
    el('rut').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();window.soloConsultar();}});
    document.getElementById('quickSearch')?.addEventListener('keydown',event=>{if(event.key==='Enter'){el('rut').value=event.target.value.trim();window.soloConsultar();}});
  });
})();
