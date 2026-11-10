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
  const fechaHoraFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone:zona,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23',calendar:'gregory',numberingSystem:'latn'
  });
  function partes(value) {
    const original=value == null?'':String(value);
    if(value instanceof Date || /[T ]\d{2}:\d{2}.*(?:Z|[+-]\d{2}:?\d{2})$/i.test(original)) {
      if(!(value instanceof Date)){try{desplazar(original.slice(0,10),0);}catch{return null;}}
      const instant=value instanceof Date?value:new Date(original);
      if(!Number.isFinite(instant.getTime()))return null;
      const p=Object.fromEntries(fechaHoraFormatter.formatToParts(instant).map(part=>[part.type,part.value]));
      return {day:p.day,month:p.month,year:p.year,hour:p.hour,minute:p.minute,second:p.second};
    }
    // Los DATETIME de la base ya expresan hora civil de Chile: no se convierten a otro huso.
    const match=/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?)?$/.exec(original);
    if(!match)return null;
    try {desplazar(match[1]+'-'+match[2]+'-'+match[3],0);}catch{return null;}
    if(match[4]!==undefined && (+match[4]>23 || +match[5]>59 || +(match[6] || 0)>59))return null;
    return {year:match[1],month:match[2],day:match[3],hour:match[4],minute:match[5],second:match[6] || '00'};
  }
  function fecha(value) {
    const p=partes(value);
    return p?p.day+'-'+p.month+'-'+p.year:String(value ?? '').trim() || '—';
  }
  function fechaHora(value,{segundos=false}={}) {
    const p=partes(value);
    if(!p)return String(value ?? '').trim() || '—';
    return p.day+'-'+p.month+'-'+p.year+(p.hour===undefined?'':' '+p.hour+':'+p.minute+(segundos?':'+p.second:''));
  }
  window.FechasChile = Object.freeze({zona,hoy,desplazar,fecha,fechaHora});
})();