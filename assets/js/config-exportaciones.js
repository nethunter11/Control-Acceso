    function syncExportControls(){
      const table = document.getElementById('csvTable').value;
      const period = document.getElementById('csvPeriod');
      const supportsDate = ['registros_acceso','visitas'].includes(table);
      const daily = supportsDate && period.value === 'dia';
      document.getElementById('csvPeriodGroup').hidden = !supportsDate;
      period.disabled = !supportsDate;
      document.getElementById('csvDateGroup').hidden = !daily;
      document.getElementById('csvDate').disabled = !daily;
      document.getElementById('csvDate').required = daily;
      document.getElementById('csvCriterionGroup').hidden = !(daily && table === 'visitas');
      document.getElementById('csvCriterion').disabled = !(daily && table === 'visitas');
      const descriptions = {
        registros_acceso: daily ? 'Incluye los movimientos registrados durante el día seleccionado.' : 'Incluye todos los movimientos de acceso.',
        visitas: daily ? 'Elige si quieres las visitas que ingresaron, salieron o tuvieron cualquiera de esas actividades ese día.' : 'Incluye todas las visitas, con sus fechas de ingreso y salida.',
        funcionarios: 'Descarga el listado completo de funcionarios.',
        usuarios: 'Descarga los usuarios, roles y estado de sus cuentas. No incluye contraseñas.'
      };
      document.getElementById('csvHelp').textContent = descriptions[table] || '';
      document.getElementById('csvMessage').textContent = '';
    }

    async function exportCsv(){
      const form = document.getElementById('csvForm');
      const button = document.getElementById('btnExportCsv');
      const message = document.getElementById('csvMessage');
      if (button.disabled || !form.reportValidity()) return;
      const table = document.getElementById('csvTable').value;
      const params = new URLSearchParams({ table });
      if (['registros_acceso','visitas'].includes(table) && document.getElementById('csvPeriod').value === 'dia') {
        params.set('fecha', document.getElementById('csvDate').value);
        if (table === 'visitas') params.set('criterio', document.getElementById('csvCriterion').value);
      }
      const previousLabel = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando CSV...';
      message.textContent = '';
      message.classList.remove('text-danger','text-success');
      try {
        const response = await fetch('/Control-Acceso/api/admin_export_csv.php?' + params.toString(), { cache:'no-store' });
        if (!response.ok) {
          const error = await response.json().catch(() => ({}));
          throw new Error(error.error || 'No se pudo descargar el CSV.');
        }
        if (!(response.headers.get('Content-Type') || '').toLowerCase().startsWith('text/csv')) {
          throw new Error('No se recibió un archivo CSV. Vuelve a iniciar sesión e inténtalo nuevamente.');
        }
        const blob = await response.blob();
        const filename = /filename="([^"]+)"/.exec(response.headers.get('Content-Disposition') || '')?.[1] || table + '.csv';
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.hidden = true;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        const count = Number(response.headers.get('X-Export-Row-Count') || 0);
        message.textContent = count === 0
          ? 'No se encontraron registros para el filtro elegido. Se descargó un CSV con encabezados.'
          : `CSV descargado: ${count.toLocaleString('es-CL')} registros.`;
        message.classList.add('text-success');
      } catch (error) {
        message.textContent = error.message || 'No se pudo descargar el CSV.';
        message.classList.add('text-danger');
      } finally {
        button.disabled = false;
        button.innerHTML = previousLabel;
      }
    }


document.addEventListener('DOMContentLoaded', () => {
  syncExportControls();
  document.getElementById('csvTable').addEventListener('change',syncExportControls);
  document.getElementById('csvPeriod').addEventListener('change',syncExportControls);
  document.getElementById('csvForm').addEventListener('submit',event=>{event.preventDefault();exportCsv();});
});
