(() => {
  'use strict';
  const escape=value=>String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  const tones={entrada:{icon:'bi-box-arrow-in-right',badge:'ok'},salida:{icon:'bi-box-arrow-right',badge:'exit'},rechazo:{icon:'bi-x-circle',badge:'bad'}};
  let dialog,timer=null,finish=null;
  function settle() {clearTimeout(timer);timer=null;const resolve=finish;finish=null;resolve?.();}
  function initialize() {
    if(dialog)return;
    dialog=document.createElement('dialog');
    dialog.className='consulta-dialog registro-popup';
    dialog.setAttribute('aria-labelledby','registroPopupTitulo');
    dialog.setAttribute('aria-describedby','registroPopupDescripcion');
    dialog.innerHTML=`<form method="dialog">
      <header class="consulta-dialog-header">
        <div class="consulta-dialog-heading"><span class="consulta-dialog-icon"><i data-popup-icon class="bi" aria-hidden="true"></i></span><div><h2 id="registroPopupTitulo"></h2><p id="registroPopupDescripcion"></p></div></div>
        <button type="submit" class="btn-icon" aria-label="Cerrar resultado"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      </header>
      <div class="consulta-dialog-body" data-popup-body></div>
      <footer class="consulta-dialog-footer"><span class="registro-popup-tiempo"><i class="bi bi-clock" aria-hidden="true"></i>Se cerrará automáticamente en 4 segundos</span><button type="submit" class="btn btn-primary" data-popup-close autofocus>Cerrar</button></footer>
    </form>`;
    dialog.addEventListener('close',()=>{
      if(dialog.open)return;
      dialog.querySelector('[data-popup-body]').textContent='';
      settle();
    });
    document.body.append(dialog);
    window.addEventListener('pagehide',()=>{if(dialog.open)dialog.close();clearTimeout(timer);});
  }
  function mostrar(data) {
    initialize();
    // Cada resultado dispone de sus propios cuatro segundos completos.
    settle();
    const tone=Object.hasOwn(tones,data.tono)?data.tono:'entrada',style=tones[tone];
    dialog.dataset.tone=tone;
    const staff=data.funcionario===true;
    const image=staff && ['/Control-Acceso/img/funcionarios/Imagen_hombre.png','/Control-Acceso/img/funcionarios/Imagen_mujer.png'].includes(data.imagen_url)?data.imagen_url:null;
    dialog.dataset.funcionario=String(staff);
    dialog.querySelector('#registroPopupTitulo').textContent=data.titulo;
    dialog.querySelector('#registroPopupDescripcion').textContent=data.subtitulo || 'Resultado del registro';
    dialog.querySelector('[data-popup-icon]').className='bi '+style.icon;
    const badge=data.activo===true?'ACTIVO':data.activo===false?'INACTIVO':'VISITA';
    const badgeClass=data.activo===true?'ok':data.activo===false?'bad':'registro-badge-visita';
    dialog.querySelector('[data-popup-body]').innerHTML=`
      <section class="consulta-identidad" aria-label="Datos personales">
        ${staff?'<div class="consulta-perfil"><div class="consulta-retrato" '+(image?'':'role="img" aria-label="Sin imagen disponible"')+'><i class="bi bi-person-bounding-box" aria-hidden="true"></i>'+(image?'<img class="consulta-foto" src="'+escape(image)+'" alt="Imagen de referencia del funcionario" width="156" height="195" decoding="async">':'')+'</div><div class="consulta-informacion">':''}
        <div class="consulta-persona"><div><div class="consulta-identidad-label">${escape(data.etiqueta || 'Datos personales')}</div><h3>${escape(data.nombre)}</h3></div><span class="func-badge ${badgeClass}">${badge}</span></div>
        <p class="consulta-rut"><span>RUT</span><strong class="mono">${escape(data.rut)}</strong></p>
        <dl class="consulta-datos">${(data.campos || []).map(([label,value])=>'<div><dt>'+escape(label)+'</dt><dd>'+escape(value || 'Sin registro')+'</dd></div>').join('')}</dl>
        ${staff?'</div></div>':''}
      </section>
      <div class="consulta-presencia registro-popup-estado"><i class="bi ${style.icon}" aria-hidden="true"></i><div><div class="func-label">Estado de acceso</div><div class="consulta-presencia-valor">${escape(data.estado)}</div>${data.detalle?'<p>'+escape(data.detalle)+'</p>':''}</div><span class="event-badge ${style.badge}">${tone==='rechazo'?'RECHAZADO':'APROBADO'}</span></div>
      ${data.fecha?'<p class="consulta-actualizacion"><i class="bi bi-clock" aria-hidden="true"></i>Registrado: '+escape(FechasChile.fechaHora(data.fecha))+'</p>':''}`;
    if(image)dialog.querySelector('.consulta-foto')?.addEventListener('error',event=>{const picture=event.currentTarget;picture.hidden=true;picture.parentElement.setAttribute('role','img');picture.parentElement.setAttribute('aria-label','Sin imagen disponible');});
    const closed=new Promise(resolve=>{finish=resolve;});
    if(!dialog.open)dialog.showModal();
    dialog.querySelector('[data-popup-close]').focus();
    timer=setTimeout(()=>{if(dialog.open)dialog.close();},4000);
    return closed;
  }
  window.RegistroPopup=Object.freeze({mostrar,abierta:()=>Boolean(dialog?.open)});
})();
