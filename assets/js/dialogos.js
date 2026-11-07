(() => {
  'use strict';
  let dialog;
  function open(title,message,password) {
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.className = 'card card-dark p-4';
      dialog.style.cssText = 'max-width:480px;width:calc(100% - 32px);color:var(--bs-body-color)';
      dialog.setAttribute('aria-labelledby','actionDialogTitle');
      dialog.innerHTML = '<form method="dialog"><h2 class="h5" id="actionDialogTitle"></h2><p id="actionDialogMessage"></p><label class="form-label" for="actionDialogPassword">Nueva contraseña</label><input id="actionDialogPassword" type="password" autocomplete="new-password" class="form-control form-control-dark mb-3" minlength="8" maxlength="72"><div class="d-flex gap-2 justify-content-end"><button class="btn btn-outline-secondary" value="cancel" formnovalidate>Cancelar</button><button class="btn btn-primary" value="save">Confirmar</button></div></form>';
      document.body.append(dialog);
    }
    if (dialog.open) return Promise.resolve(null);
    const input = dialog.querySelector('input');
    dialog.querySelector('h2').textContent = title;
    dialog.querySelector('p').textContent = message;
    input.hidden = !password;
    input.required = password;
    input.disabled = !password;
    dialog.querySelector('label').hidden = !password;
    input.value = '';
    dialog.returnValue = 'cancel';
    return new Promise(resolve => {
      dialog.onclose = () => { const result = dialog.returnValue === 'save' ? (password ? input.value : true) : null; input.value = ''; resolve(result); };
      dialog.showModal();
      if (password) input.focus();
    });
  }
  window.Dialogos = Object.freeze({
    confirmar:(title,message)=>open(title,message,false),
    password:(title,message)=>open(title,message,true)
  });
})();