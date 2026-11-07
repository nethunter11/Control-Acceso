const roleLabels={GUARDIA:'Guardia',CONTROL_VISITA:'Control de visitas',AUDITOR:'Auditor',ADMIN:'Administrador'};
let usersById=new Map();
let usersPage=1,usersSequence=0;
const userPending=new Set();
function escapeHtml(value){return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');}
function userMessage(text,error=false){const element=document.getElementById('nu_msg');element.textContent=text;element.classList.toggle('text-danger',error);}
async function postJSON(url,payload){
  try{
    const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await response.json();
    return response.ok?data:{ok:false,error:data.error || 'No se pudo guardar el cambio.'};
  }catch{return{ok:false,error:'No se pudo confirmar el cambio. Actualiza la lista antes de reintentar.'};}
}
async function loadUsers(page=usersPage){
  const button=document.getElementById('btnUsersRefresh');button.disabled=true;const expected=usersSequence+1;
  try{await fetchUsersTable(page);}
  catch{if(expected===usersSequence)document.getElementById('usersSummary').textContent='La lista no está actualizada. Intenta actualizar nuevamente.';}
  finally{if(expected===usersSequence)button.disabled=false;}
}
async function fetchUsersTable(page){
  const generation=++usersSequence;
  const params=new URLSearchParams({q:document.getElementById('usersSearch').value.trim(),rol:document.getElementById('usersRole').value,activo:document.getElementById('usersActive').value,limit:document.getElementById('usersLimit').value,page:String(page)});
  document.getElementById('usersSummary').textContent='Actualizando usuarios…';
  const response=await fetch('/Control-Acceso/api/admin_users_list.php?'+params,{cache:'no-store'});
  const data=await response.json();
  if(!response.ok || !data.ok)throw new Error(data.error || 'No se pudo consultar.');
  if(generation!==usersSequence)return;usersPage=data.page;
  document.getElementById('usersSummary').textContent=Tablas.resumen(data);Tablas.estado('users',data);
  usersById=new Map(data.items.map(user=>[Number(user.id),user]));
  let html='<div class="table-responsive"><table class="table table-dark table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>';
  for(const user of data.items){
    const self=Number(user.id)===Number(data.current_user_id),id=Number(user.id);
    html+='<tr><td>'+id+'</td><td class="mono">'+escapeHtml(user.username)+(self?'<div class="small text-muted">Tu cuenta</div>':'')+'</td><td><select class="form-select form-control-dark" aria-label="Rol de '+escapeHtml(user.username)+'" '+(self?'disabled':'')+' onchange="updateRole('+id+',this.value)">'+Object.entries(roleLabels).map(([role,label])=>'<option value="'+role+'" '+(user.rol===role?'selected':'')+'>'+label+'</option>').join('')+'</select></td><td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" '+(Number(user.activo)===1?'checked':'')+' '+(self?'disabled':'')+' aria-label="Cuenta activa de '+escapeHtml(user.username)+'" onchange="updateActive('+id+',this.checked)"></div></td><td><div class="table-actions"><button type="button" class="btn btn-sm btn-outline-light" data-username="'+escapeHtml(user.username)+'" onclick="resetPassPrompt('+id+',this.dataset.username)">Restablecer contraseña</button><button type="button" class="btn btn-sm btn-outline-danger" '+(self?'disabled':'')+' data-username="'+escapeHtml(user.username)+'" onclick="deleteUser('+id+',this.dataset.username)">Eliminar</button></div></td></tr>';
  }
  if(!data.items.length)html+='<tr><td colspan="5"><div class="table-empty">No se encontraron usuarios.</div></td></tr>';
  document.getElementById('usersTable').innerHTML=html+'</tbody></table></div>';
}
async function createUser(){
  const button=document.getElementById('nu_create');if(button.disabled)return;button.disabled=true;
  try{
    const data=await postJSON('/Control-Acceso/api/admin_users_create.php',{
      username:document.getElementById('nu_username').value.trim(),password:document.getElementById('nu_password').value,
      rol:document.getElementById('nu_rol').value,activo:Number(document.getElementById('nu_activo').value)
    });
    if(!data.ok){userMessage(data.error,true);return;}
    userMessage('Usuario creado.');document.getElementById('nu_username').value='';document.getElementById('nu_password').value='';await loadUsers();
  }finally{button.disabled=false;}
}
async function changeUser(id,fields,title,detail){
  if(userPending.has(id))return;userPending.add(id);
  try{
    if(!await Dialogos.confirmar(title,detail))return;
    const data=await postJSON('/Control-Acceso/api/admin_users_update.php',{id,...fields});
    userMessage(data.ok?'Permisos actualizados. Se cerraron las sesiones anteriores.':data.error,!data.ok);
  }finally{userPending.delete(id);await loadUsers();}
}
async function updateRole(id,role){
  const user=usersById.get(Number(id));if(!user)return;
  return changeUser(Number(id),{rol:role},'Cambiar rol',user.username+' · '+roleLabels[user.rol]+' → '+roleLabels[role]+'. Sus sesiones anteriores se cerrarán.');
}
async function updateActive(id,checked){
  const user=usersById.get(Number(id));if(!user)return;
  return changeUser(Number(id),{activo:checked?1:0},checked?'Activar cuenta':'Desactivar cuenta',user.username+'. '+(checked?'Podrá iniciar sesión.':'Se cerrarán sus sesiones y no podrá iniciar sesión.'));
}
async function resetPassPrompt(id,username){
  if(userPending.has(id))return;userPending.add(id);
  try{
    const password=await Dialogos.password('Restablecer contraseña','Usuario: '+username+'. Sus sesiones anteriores se cerrarán.');
    if(!password)return;
    const data=await postJSON('/Control-Acceso/api/admin_users_reset_password.php',{id,password});
    if(data.reauthenticate){window.location.assign('/Control-Acceso/auth/login.php');return;}
    userMessage(data.ok?'Contraseña actualizada. Se cerraron las sesiones anteriores.':data.error,!data.ok);
  }finally{userPending.delete(id);}
}
async function deleteUser(id,username){
  if(userPending.has(id))return;userPending.add(id);
  try{
    if(!await Dialogos.confirmar('Eliminar usuario','Usuario: '+username+'. Si tiene movimientos registrados, debes desactivarlo.'))return;
    const data=await postJSON('/Control-Acceso/api/admin_users_delete.php',{id});
    userMessage(data.ok?'Usuario eliminado.':data.error,!data.ok);
    await loadUsers();
  }finally{userPending.delete(id);}
}
document.addEventListener('DOMContentLoaded',()=>{
  document.getElementById('usersFilters').addEventListener('submit',event=>{event.preventDefault();loadUsers(1);});
  for(const id of ['usersRole','usersActive','usersLimit'])document.getElementById(id).addEventListener('change',()=>loadUsers(1));
  document.getElementById('usersPage').addEventListener('change',()=>loadUsers(Tablas.numero('users')));
  document.getElementById('usersPage').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();loadUsers(Tablas.numero('users'));}});
  loadUsers(1);
});