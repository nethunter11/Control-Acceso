(() => {
  'use strict';
  const el=id=>document.getElementById(id);
  const escape=value=>String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  let records=new Map(),revision=null,page=1,preview=null,sequence=0,previewSequence=0;
  async function request(data,query='') {
    const response=await fetch('/Control-Acceso/api/admin_funcionarios.php'+query,data?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}:{cache:'no-store'});
    const body=await response.json();
    if(!response.ok || !body.ok) throw new Error(body.error || 'No se pudo completar la operación.');
    return body;
  }
  function message(id,text,error=false) {el(id).textContent=text;el(id).classList.toggle('text-danger',error);}
  function clearForm() {
    revision=null; el('staffForm').reset(); el('sf_rut').readOnly=false; el('staffFormTitle').textContent='Nuevo funcionario';
  }
  function invalidatePreview() {previewSequence++;preview=null;el('staffImportApply').disabled=true;el('staffPreviewPanel').hidden=true;}
  async function load(nextPage=1) {
    const current=++sequence;
    try {
      const params=new URLSearchParams({q:el('staffSearch').value.trim(),estado:el('staffStateFilter').value,page:String(nextPage),limit:el('staffLimit').value});
      const data=await request(null,'?'+params);
      if(current!==sequence) return;
      page=data.page; records=new Map(data.items.map(row=>[row.rut,row]));
      let html='<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>RUT</th><th>Nombre</th><th>Unidad</th><th>Grado</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
      for(const row of data.items) html+='<tr><td class="mono">'+escape(row.rut)+'</td><td>'+escape(row.nombres)+'</td><td>'+escape(row.unidad || '—')+'</td><td>'+escape(row.grado || '—')+'</td><td><span class="badge '+(row.estado==='ACTIVO'?'text-bg-success':'text-bg-secondary')+'">'+escape(row.estado)+'</span></td><td><button type="button" class="btn btn-sm btn-outline-light" data-edit="'+escape(row.rut)+'">Editar</button></td></tr>';
      if(!data.items.length) html+='<tr><td colspan="6"><div class="table-empty">No hay funcionarios para esta búsqueda.</div></td></tr>';
      el('staffTable').innerHTML=html+'</tbody></table></div>';
      message('staffListSummary',Tablas.resumen(data));Tablas.estado('staff',data);
    } catch(error) {if(current===sequence) message('staffListSummary',error.message,true);}
  }
  function edit(rut) {
    const row=records.get(rut); if(!row) return;
    revision=row.revision;
    for(const field of ['rut','nombres','unidad','grado','estado']) el('sf_'+field).value=row[field] ?? '';
    el('sf_rut').readOnly=true; el('staffFormTitle').textContent='Editar funcionario · '+row.rut;
    message('staffMessage','Los cambios se aplican al guardar.');
    el('staffForm').scrollIntoView({block:'center'}); el('sf_nombres').focus();
  }
  async function save(event) {
    event.preventDefault(); if(el('staffSave').disabled) return;
    const data={accion:'guardar',revision};
    for(const field of ['rut','nombres','unidad','grado','estado']) data[field]=el('sf_'+field).value.trim();
    if(data.estado==='INACTIVO' && !await Dialogos.confirmar('Guardar funcionario inactivo',data.nombres+' · '+data.rut+'. No podrá registrar nuevos ingresos.')) return;
    el('staffSave').disabled=true;
    try {
      await request(data); clearForm(); message('staffMessage','Funcionario guardado: '+data.nombres+' · '+data.rut);
      await load(page);
    } catch(error) {message('staffMessage',error.message,true);}
    finally {el('staffSave').disabled=false;}
  }
  async function previewFile(event) {
    event.preventDefault(); if(el('staffPreview').disabled) return; invalidatePreview();
    const generation=previewSequence;
    const file=el('staffCsv').files[0];
    if(!file || file.size>1048576) {message('staffImportMessage','Selecciona un CSV de hasta 1 MB.',true);return;}
    el('staffPreview').disabled=true; el('staffCsv').disabled=true; el('staffImportMode').disabled=true;
    try {
      let csv; try {csv=new TextDecoder("utf-8",{fatal:true}).decode(await file.arrayBuffer());} catch {throw new Error("El archivo debe estar guardado como CSV UTF-8.");}
      const data=await request({accion:'preview',csv,modo:el('staffImportMode').value});
      if(generation!==previewSequence) return;
      const view=data.preview; preview=data.id?{id:data.id,crear:view.crear,actualizar:view.actualizar}:null;
      el('staffPreviewPanel').hidden=false;
      el('staffPreviewSummary').textContent=view.crear+' nuevos · '+view.actualizar+' existentes · '+view.errores+' errores. La vista previa dura 10 minutos.';
      let html='<div class="table-responsive"><table class="table table-dark align-middle"><thead><tr><th>Fila</th><th>RUT</th><th>Nombre</th><th>Unidad</th><th>Grado</th><th>Estado</th><th>Acción / observación</th></tr></thead><tbody>';
      for(const row of view.rows) {
        const item=row.data || {};
        const changes=row.cambios.length?'<details><summary>'+row.cambios.length+' cambios</summary><ul>'+row.cambios.map(change=>'<li>'+escape(change.campo)+': '+escape(change.anterior || 'vacío')+' → '+escape(change.nuevo || 'vacío')+'</li>').join('')+'</ul></details>':'';
        html+='<tr><td>'+row.fila+'</td><td>'+escape(item.rut || '—')+'</td><td>'+escape(item.nombres || '—')+'</td><td>'+escape(item.unidad || '—')+'</td><td>'+escape(item.grado || '—')+'</td><td>'+escape(item.estado || '—')+'</td><td>'+(row.error?'<span class="text-danger">'+escape(row.error)+'</span>':escape(row.accion)+changes)+'</td></tr>';
      }
      el('staffPreviewTable').innerHTML=html+'</tbody></table></div>';
      el('staffImportApply').disabled=!preview;
      message('staffImportMessage',view.errores?'Corrige todos los errores y vuelve a revisar.':'Revisa la tabla y confirma la importación.',Boolean(view.errores));
    } catch(error) {message('staffImportMessage',error.message,true);}
    finally {el('staffPreview').disabled=false;el('staffCsv').disabled=false;el('staffImportMode').disabled=false;}
  }
  async function apply() {
    if(!preview || el('staffImportApply').disabled) return;
    const pending=preview;
    if(!await Dialogos.confirmar('Confirmar importación','Se crearán '+pending.crear+' funcionarios y se actualizarán '+pending.actualizar+'.')) return;
    if(preview!==pending) return;
    el('staffImportApply').disabled=true;
    try {
      const data=await request({accion:'importar',id:pending.id});
      invalidatePreview(); clearForm();
      message('staffImportMessage','Importación completa: '+data.creados+' creados y '+data.actualizados+' actualizados.');
      await load(1);
    } catch(error) {preview=null;message('staffImportMessage',error.message+' Vuelve a revisar el archivo.',true);}
  }
  document.addEventListener('DOMContentLoaded',()=>{
    el('staffForm').addEventListener('submit',save);
    el('staffNew').addEventListener('click',()=>{clearForm();message('staffMessage','');});
    el('staffSearchForm').addEventListener('submit',event=>{event.preventDefault();load(1);});
    el('staffStateFilter').addEventListener('change',()=>load(1));
    el('staffLimit').addEventListener('change',()=>load(1));
    el('staffPage').addEventListener('change',()=>load(Tablas.numero('staff')));
    el('staffPage').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();load(Tablas.numero('staff'));}});
    el('staffPrev').addEventListener('click',()=>load(page-1));
    el('staffNext').addEventListener('click',()=>load(page+1));
    el('staffTable').addEventListener('click',event=>{const button=event.target.closest('[data-edit]');if(button)edit(button.dataset.edit);});
    el('staffImportForm').addEventListener('submit',previewFile);
    el('staffCsv').addEventListener('change',invalidatePreview);
    el('staffImportMode').addEventListener('change',invalidatePreview);
    el('staffImportApply').addEventListener('click',apply);
    load();
  });
})();