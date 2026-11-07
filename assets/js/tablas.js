(() => {
  'use strict';
  window.Tablas=Object.freeze({
    resumen(data){
      const total=Number(data.total)||0,limit=Number(data.limit)||25,page=Number(data.page)||1;
      const from=data.from ?? (total?(page-1)*limit+1:0);
      const to=data.to ?? Math.min(total,page*limit);
      return from+'–'+to+' de '+total+' · Página '+page+' de '+(Number(data.pages)||1);
    },
    estado(prefix,data){
      const previous=document.getElementById(prefix+'Prev'),next=document.getElementById(prefix+'Next'),input=document.getElementById(prefix+'Page');
      if(previous)previous.disabled=data.page<=1;
      if(next)next.disabled=data.page>=data.pages;
      if(input){input.value=data.page;input.max=data.pages;input.disabled=data.pages<=1;}
    },
    numero(prefix){
      const input=document.getElementById(prefix+'Page');
      const value=Number(input?.value);
      return Number.isInteger(value)&&value>=1?Math.min(value,Number(input.max)||1):1;
    }
  });
})();