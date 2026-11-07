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
    const person=data.funcionario,last=data.ultimo_movimiento;
    const presence=data.presencia==='DENTRO'?'Dentro':data.presencia==='FUERA'?'Fuera':'Sin movimientos aprobados';
    const presenceClass=data.presencia==='DENTRO'?'text-bg-success':data.presencia==='FUERA'?'text-bg-warning':'badge-soft';
    el('fichaFuncionario').innerHTML='<div class="panel func-card '+(data.activo?'is-ok':'is-bad')+'"><div class="func-head"><div><div class="func-title">'+escape(person.nombres)+'</div><div class="func-subtitle mono">'+escape(person.rut)+'</div></div><span class="func-badge '+(data.activo?'ok':'bad')+'">'+(data.activo?'ACTIVO':'INACTIVO')+'</span></div><div class="presence-line"><span class="badge '+presenceClass+'">'+presence+'</span><span class="small text-muted">Presencia según movimientos aprobados</span></div><div class="func-grid mt-3"><div><div class="func-label">Unidad</div><div class="func-value">'+escape(person.unidad || '—')+'</div></div><div><div class="func-label">Grado</div><div class="func-value">'+escape(person.grado || '—')+'</div></div></div><div class="last-movement mt-3"><div class="func-label">Último movimiento aprobado</div>'+(last?'<div class="func-value">'+(last.tipo==='ENTRADA'?'Entrada':'Salida')+' · '+escape(last.fecha_hora)+'</div><div class="small mt-1">'+escape(last.puesto || 'Puesto sin registro')+' · '+escape(last.operador || 'Operador sin registro')+'</div>':'<p class="small mb-0 mt-1">Aún no hay movimientos aprobados.</p>')+'</div><div class="func-subtitle mt-3">Consultado: '+escape(data.checked_at)+'</div></div>';
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
    if(busy)return;
    const rut=el('rut').value.trim();
    if(!rut){note('Ingresa un RUT.','warning');el('rut').focus();return;}
    lock(true);
    try {await consult(rut);}
    catch(error){el('fichaFuncionario').innerHTML='';note(error.message,'danger');}
    finally {lock(false);}
  };
  function renderResult(data,requested) {
    const ok=data.resultado==='APROBADO',exit=requested.tipo==='SALIDA';
    const tone=ok?(exit?'exit':'entry'):'bad';
    const title=(exit?'Salida':'Entrada')+(ok?' registrada':' rechazada');
    const name=data.funcionario?.nombres || 'Persona no registrada';
    const date=data.fecha_hora || (data.guardado===false?'Sin nuevo registro':'Fecha no disponible');
    el('resultado').innerHTML='<div class="event-card '+tone+'"><div class="event-head"><div><div class="func-label mb-1">Última operación</div><div class="event-title"><i class="bi '+(ok?(exit?'bi-box-arrow-right':'bi-box-arrow-in-right'):'bi-x-circle')+'" aria-hidden="true"></i> '+title+'</div></div><span class="event-badge '+(ok?(exit?'exit':'ok'):'bad')+'">'+(ok?'APROBADO':'RECHAZADO')+'</span></div><div class="event-value">'+escape(name)+'</div><div class="mono mt-1">'+escape(data.rut || requested.rut)+'</div><div class="event-subtitle">'+escape(data.puesto || requested.puesto)+' · '+(requested.modo==='VEHICULO'?'Vehicular':'Peatonal')+'</div><div class="small mt-2">'+escape(date)+(data.guardado && data.operador?' · '+escape(data.operador):'')+'</div>'+(data.motivo?'<div class="event-motivo">'+escape(data.motivo)+'</div>':'')+'</div>';
  }
  window.registrar=async function (type) {
    if(busy || !['ENTRADA','SALIDA'].includes(type))return;
    const payload={rut:el('rut').value.trim(),tipo:type,puesto:el('puesto').value,modo:mode};
    if(!payload.rut){note('Ingresa un RUT.','warning');el('rut').focus();return;}
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
      try {await consult(payload.rut);} catch {el('fichaFuncionario').innerHTML='';note('La operación fue respondida; no se pudo actualizar la ficha. Consulta nuevamente.','warning');}
      if(typeof window.cargarDentro==='function')await window.cargarDentro();
    } catch(error){el('fichaFuncionario').innerHTML='';note(error.message+' Consulta el estado antes de volver a registrar.','danger');}
    finally {lock(false);}
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
    el('rut').addEventListener('input',()=>{el('fichaFuncionario').innerHTML='';el('consultaMensaje').textContent='';});
    el('rut').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();window.soloConsultar();}});
    document.getElementById('quickSearch')?.addEventListener('keydown',event=>{if(event.key==='Enter'){el('rut').value=event.target.value.trim();window.soloConsultar();}});
  });
})();