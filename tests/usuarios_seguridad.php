<?php
require_once __DIR__.'/support/accesos_fixture.php';
require_once __DIR__.'/../app/usuarios.php';
qa_run(function (array &$qa): void {
  $admin = $qa['users']['ADMIN'];
  $actor = qa_json($qa,'api/admin_users_list.php')['current_user_id'];
  qa_check($actor === (int)$admin['id'],'No se identifica la cuenta propia.');
  $row = $qa['pdo']->query('SELECT id,session_version FROM usuarios WHERE id='.(int)$admin['id'])->fetch();
  $actor = ['id'=>(int)$row['id'],'session_version'=>(int)$row['session_version']];
  foreach ([['update',['activo'=>0]],['update',['rol'=>'GUARDIA']],['delete',[]]] as [$action,$fields]) {
    try { usuario_admin_operar($qa['pdo'],$actor,$action,['id'=>$actor['id']]+$fields); throw new RuntimeException('Se permitió bloquear la cuenta propia.'); }
    catch (UsuarioOperacionError $expected) { qa_check($expected->status === 409,'Error inesperado al proteger la cuenta propia.'); }
  }
  $created = [];
  $sessions = [];
  try {
    $new = usuario_admin_operar($qa['pdo'],$actor,'create',['username'=>$qa['marker'],'password'=>'Temporal-8765','rol'=>'GUARDIA','activo'=>1]);
    $id = $new['id']; $created[] = $id;
    session_id(bin2hex(random_bytes(16))); auth_login(['id'=>$id]); $session = session_id(); $sessions[]=$session; session_write_close();
    qa_check(qa_http($session,'api/personal_dentro.php')['status'] === 200,'La sesión nueva no funciona.');
    usuario_admin_operar($qa['pdo'],$actor,'update',['id'=>$id,'activo'=>0]);
    qa_check(qa_http($session,'api/personal_dentro.php')['status'] === 401,'La desactivación no revoca la sesión.');
    usuario_admin_operar($qa['pdo'],$actor,'update',['id'=>$id,'activo'=>1]);
    qa_check(qa_http($session,'api/personal_dentro.php')['status'] === 401,'Reactivar recuperó una sesión revocada.');
    session_id(bin2hex(random_bytes(16))); auth_login(['id'=>$id]); $session = session_id(); $sessions[]=$session; session_write_close();
    usuario_admin_operar($qa['pdo'],$actor,'update',['id'=>$id,'rol'=>'AUDITOR']);
    qa_check(qa_http($session,'api/personal_dentro.php')['status'] === 401,'Cambiar el rol no revoca la sesión.');
    session_id(bin2hex(random_bytes(16))); auth_login(['id'=>$id]); $session = session_id(); $sessions[]=$session; session_write_close();
    usuario_admin_operar($qa['pdo'],$actor,'reset_password',['id'=>$id,'password'=>'Cambio-09876']);
    qa_check(qa_http($session,'api/reportes_kpi.php?desde=2026-01-01&hasta=2026-01-01')['status'] === 401,'Restablecer contraseña no revoca la sesión.');
    $rut=qa_staff($qa);
    $event=qa_event($qa,$rut,'ENTRADA','2001-01-01 12:00:00');
    $qa['pdo']->prepare('UPDATE registros_acceso SET operador_id=? WHERE id=?')->execute([$id,$event]);
    try { usuario_admin_operar($qa['pdo'],$actor,'delete',['id'=>$id]); throw new RuntimeException('Se eliminó un operador con historial.'); }
    catch (UsuarioOperacionError $expected) { qa_check($expected->status===409,'No se explicó cómo conservar el historial.'); }
    $qa['pdo']->prepare('UPDATE registros_acceso SET operador_id=NULL WHERE id=?')->execute([$event]);
    usuario_admin_operar($qa['pdo'],$actor,'delete',['id'=>$id]);
    $created=[];
    try { usuario_admin_operar($qa['pdo'],$actor,'update',['id'=>$id,'activo'=>0]); throw new RuntimeException('Se aceptó un usuario inexistente.'); }
    catch (UsuarioOperacionError $expected) { qa_check($expected->status===404,'No se distingue un usuario inexistente.'); }
    echo "OK: cuenta propia protegida, historial preservado y sesiones revocadas por estado, rol o contraseña\n";
  } finally {
    foreach ($created as $id) $qa['pdo']->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    foreach ($sessions as $session) { $file=app_config()['session']['save_path'].'/sess_'.$session; if(is_file($file)) unlink($file); }
  }
});