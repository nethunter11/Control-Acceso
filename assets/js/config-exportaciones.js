function validateExportRange() {
  const from=document.getElementById('csvFrom');
  const to=document.getElementById('csvTo');
  from.max=to.value || '9999-12-30';
  to.min=from.value || '1000-01-01';
  const reversed=!from.disabled && !to.disabled && from.value && to.value && from.value>to.value;
  to.setCustomValidity(reversed?'La fecha Hasta debe ser igual o posterior a Desde.':'');
}
function syncExportControls() {
  const table=document.getElementById('csvTable').value;
  const period=document.getElementById('csvPeriod');
  const dated=['registros_acceso','visitas'].includes(table);
  if(!['dia','rango'].includes(period.value))period.value='dia';
  const daily=dated && period.value==='dia',ranged=dated && period.value==='rango';
  document.getElementById('csvPeriodGroup').hidden=!dated;
  period.disabled=!dated;
  for(const [group,id,active] of [['csvDateGroup','csvDate',daily],['csvFromGroup','csvFrom',ranged],['csvToGroup','csvTo',ranged]]) {
    document.getElementById(group).hidden=!active;
    const input=document.getElementById(id);input.disabled=!active;input.required=active;
  }
  document.getElementById('csvCriterionGroup').hidden=!(dated && table==='visitas');
  document.getElementById('csvCriterion').disabled=!(dated && table==='visitas');
  validateExportRange();
  const descriptions={
    registros_acceso:daily?'Incluye los movimientos del día seleccionado.':'Incluye los movimientos desde el inicio del primer día hasta el final del último día.',
    visitas:daily?'Elige las visitas que ingresaron, salieron o tuvieron cualquiera de esas actividades ese día.':'Elige las visitas que ingresaron, salieron o tuvieron cualquiera de esas actividades dentro del rango. Se incluyen ambos días completos.',
    funcionarios:'Descarga el listado completo de funcionarios.',
    usuarios:'Descarga los usuarios, roles y estado de sus cuentas. No incluye contraseñas.'
  };
  document.getElementById('csvHelp').textContent=descriptions[table] || '';
  const message=document.getElementById('csvMessage');message.textContent='';message.classList.remove('text-danger','text-success');
}
async function exportCsv() {
  const form=document.getElementById('csvForm');
  const button=document.getElementById('btnExportCsv');
  const message=document.getElementById('csvMessage');
  if(button.disabled)return;
  validateExportRange();
  if(!form.reportValidity())return;
  const table=document.getElementById('csvTable').value;
  const params=new URLSearchParams({table});
  if(['registros_acceso','visitas'].includes(table)) {
    const period=document.getElementById('csvPeriod').value;
    if(period==='dia')params.set('fecha',document.getElementById('csvDate').value);
    else if(period==='rango') {
      params.set('desde',document.getElementById('csvFrom').value);
      params.set('hasta',document.getElementById('csvTo').value);
    } else {
      message.textContent='Selecciona un día o un rango de fechas.';message.classList.add('text-danger');return;
    }
    if(table==='visitas')params.set('criterio',document.getElementById('csvCriterion').value);
  }
  const previousLabel=button.innerHTML;
  const controls=['csvTable','csvPeriod','csvDate','csvFrom','csvTo','csvCriterion'].map(id=>document.getElementById(id));
  const previousDisabled=controls.map(input=>input.disabled);
  for(const input of controls)input.disabled=true;
  button.disabled=true;form.setAttribute('aria-busy','true');
  button.innerHTML='<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando CSV...';
  message.textContent='';message.classList.remove('text-danger','text-success');
  try {
    const response=await fetch('/Control-Acceso/api/admin_export_csv.php?'+params,{cache:'no-store'});
    if(!response.ok) {
      const error=await response.json().catch(()=>({}));
      throw new Error(error.error || 'No se pudo descargar el CSV.');
    }
    if(!(response.headers.get('Content-Type') || '').toLowerCase().startsWith('text/csv'))throw new Error('No se recibió un archivo CSV. Vuelve a iniciar sesión e inténtalo nuevamente.');
    const blob=await response.blob();
    const filename=/filename="([^"]+)"/.exec(response.headers.get('Content-Disposition') || '')?.[1] || table+'.csv';
    const url=URL.createObjectURL(blob);
    const link=document.createElement('a');link.href=url;link.download=filename;link.hidden=true;
    document.body.appendChild(link);link.click();link.remove();
    setTimeout(()=>URL.revokeObjectURL(url),1000);
    const count=Number(response.headers.get('X-Export-Row-Count') || 0);
    message.textContent=count===0?'No se encontraron registros para el filtro elegido. Se descargó un CSV con encabezados.':'CSV descargado: '+count.toLocaleString('es-CL')+' registros.';
    message.classList.add('text-success');
  } catch(error) {
    message.textContent=error.message || 'No se pudo descargar el CSV.';message.classList.add('text-danger');
  } finally {
    controls.forEach((input,index)=>{input.disabled=previousDisabled[index];});
    button.disabled=false;button.innerHTML=previousLabel;form.setAttribute('aria-busy','false');
  }
}
document.addEventListener('DOMContentLoaded',()=>{
  syncExportControls();
  for(const id of ['csvTable','csvPeriod'])document.getElementById(id).addEventListener('change',syncExportControls);
  for(const id of ['csvDate','csvFrom','csvTo','csvCriterion'])document.getElementById(id).addEventListener('change',syncExportControls);
  for(const id of ['csvFrom','csvTo'])document.getElementById(id).addEventListener('input',validateExportRange);
  document.getElementById('csvForm').addEventListener('submit',event=>{event.preventDefault();exportCsv();});
});