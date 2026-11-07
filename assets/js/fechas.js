(() => {
  'use strict';
  const zona = 'America/Santiago';
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone:zona, year:'numeric', month:'2-digit', day:'2-digit', calendar:'gregory', numberingSystem:'latn'
  });
  function hoy(instant = new Date()) {
    const parts = Object.fromEntries(formatter.formatToParts(instant).map(part => [part.type,part.value]));
    return parts.year+'-'+parts.month+'-'+parts.day;
  }
  // Suma días civiles, independientemente del huso del computador y de los días de 23/25 horas.
  function desplazar(day, count) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(day) || !Number.isInteger(count)) throw new Error('Fecha o desplazamiento inválido.');
    const date = new Date(day+'T12:00:00Z');
    if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0,10) !== day) throw new Error('Fecha inválida.');
    date.setUTCDate(date.getUTCDate()+count);
    return date.toISOString().slice(0,10);
  }
  window.FechasChile = Object.freeze({zona,hoy,desplazar});
})();