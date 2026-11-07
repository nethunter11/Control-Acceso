(() => {
  'use strict';
  const config = document.getElementById('appSession');
  if (!config) return;
  const state = JSON.parse(config.textContent);
  const originalFetch = window.fetch.bind(window);
  let expired = false;
  let activity = false;
  let busy = false;
  const expire = () => {
    if (expired) return;
    expired = true;
    window.location.assign('/Control-Acceso/auth/login.php?err='+encodeURIComponent('La sesión terminó. Vuelve a iniciar sesión.'));
  };
  window.fetch = async function (input, init = {}) {
    const address = new URL(typeof input === 'string' || input instanceof URL ? input : input.url,window.location.href);
    const local = address.origin === window.location.origin && address.pathname.startsWith('/Control-Acceso/');
    if (!local) return originalFetch(input,init);
    const method = String(init.method || (typeof input === 'object' ? input.method : '') || 'GET').toUpperCase();
    const headers = new Headers(init.headers || (typeof input === 'object' ? input.headers : undefined));
    if (!['GET','HEAD','OPTIONS'].includes(method)) headers.set('X-CSRF-Token',state.csrf);
    const response = await originalFetch(input,{...init,headers});
    if (response.status === 401) expire();
    return response;
  };
  for (const event of ['pointerdown','keydown','input','touchstart']) document.addEventListener(event,event => {
    if (event.isTrusted) activity = true;
  },{passive:true});
  async function check() {
    if (busy || expired || document.hidden) return;
    busy = true;
    const hadActivity = activity;
    activity = false;
    try {
      const response = await window.fetch('/Control-Acceso/api/session.php',{
        method:hadActivity ? 'POST' : 'GET',cache:'no-store'
      });
      if (response.ok) {
        const data = await response.json();
        state.expires_at = data.expires_at;
      } else if (response.status === 403) expire();
    } catch {
      if (hadActivity) activity = true;
    } finally { busy = false; }
  }
  setInterval(check,30000);
  document.addEventListener('visibilitychange',() => { if (!document.hidden) check(); });
  window.addEventListener('pageshow',event => { if (event.persisted) check(); });
})();