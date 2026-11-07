<?php
require_once __DIR__.'/concurrencia.php';

final class UsuarioOperacionError extends RuntimeException {
  public function __construct(string $message, public int $status = 422) { parent::__construct($message); }
}
function usuario_texto(array $data, string $key, int $max, bool $required = true): string {
  $value = $data[$key] ?? '';
  if (!is_string($value) || !preg_match('//u',$value)) throw new UsuarioOperacionError('Campo inválido: '.$key);
  $value = trim($value);
  if (($required && $value === '') || mb_strlen($value,'UTF-8') > $max) throw new UsuarioOperacionError('Revisa el campo '.$key.' (máximo '.$max.' caracteres).');
  return $value;
}
function usuario_admin_operar(PDO $pdo, array $actor, string $action, array $data): array {
  return ejecutar_por_rut($pdo,'administracion','usuarios',function () use ($pdo,$actor,$action,$data): array {
    $lookup = $pdo->prepare('SELECT id,username,rol,activo,session_version FROM usuarios WHERE id=? FOR UPDATE');
    $lookup->execute([$actor['id']]);
    $current = $lookup->fetch();
    if (!$current || !$current['activo'] || $current['rol'] !== 'ADMIN' || (int)$current['session_version'] !== (int)$actor['session_version']) {
      throw new UsuarioOperacionError('Tu cuenta ya no tiene autorización para administrar usuarios.',403);
    }
    $allowed = ['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'];
    if ($action === 'create') {
      $username = usuario_texto($data,'username',60);
      $password = $data['password'] ?? '';
      if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) throw new UsuarioOperacionError('La contraseña debe tener entre 8 y 72 bytes.');
      $role = usuario_texto($data,'rol',30);
      if (!in_array($role,$allowed,true)) throw new UsuarioOperacionError('Selecciona un rol válido.');
      $active = $data['activo'] ?? 1;
      if (!in_array($active,[0,1,'0','1'],true)) throw new UsuarioOperacionError('Estado de cuenta inválido.');
      $duplicate = $pdo->prepare('SELECT 1 FROM usuarios WHERE username=?');
      $duplicate->execute([$username]);
      if ($duplicate->fetchColumn()) throw new UsuarioOperacionError('El nombre de usuario ya existe.',409);
      $pdo->prepare('INSERT INTO usuarios (username,password_hash,rol,activo) VALUES (?,?,?,?)')->execute([$username,password_hash($password,PASSWORD_DEFAULT),$role,(int)$active]);
      return ['ok'=>true,'id'=>(int)$pdo->lastInsertId()];
    }
    $id = filter_var($data['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if ($id === false) throw new UsuarioOperacionError('ID de usuario inválido.');
    $lookup->execute([$id]);
    $target = $lookup->fetch();
    if (!$target) throw new UsuarioOperacionError('El usuario no existe.',404);
    if ($action === 'reset_password') {
      $password = $data['password'] ?? '';
      if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) throw new UsuarioOperacionError('La contraseña debe tener entre 8 y 72 bytes.');
      $pdo->prepare('UPDATE usuarios SET password_hash=?,session_version=session_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
      return ['ok'=>true,'reauthenticate'=>(int)$actor['id'] === (int)$id];
    }
    $role = $data['rol'] ?? $target['rol'];
    $active = $data['activo'] ?? $target['activo'];
    if (!is_string($role) || !in_array($role,$allowed,true) || !in_array($active,[0,1,'0','1'],true)) throw new UsuarioOperacionError('Rol o estado inválido.');
    if ($action === 'delete') $active = 0;
    if ((int)$id === (int)$actor['id'] && ($action === 'delete' || $role !== 'ADMIN' || (int)$active !== 1)) {
      throw new UsuarioOperacionError('No puedes eliminar, desactivar ni quitar permisos a tu propia cuenta.',409);
    }
    if ($target['rol'] === 'ADMIN' && (int)$target['activo'] === 1 && ($role !== 'ADMIN' || (int)$active !== 1)) {
      $count = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='ADMIN' AND activo=1")->fetchColumn();
      if ($count <= 1) throw new UsuarioOperacionError('Debe permanecer al menos un administrador activo.',409);
    }
    if ($action === 'delete') {
      $used = $pdo->prepare('SELECT (SELECT COUNT(*) FROM registros_acceso WHERE operador_id=?) + (SELECT COUNT(*) FROM visitas WHERE operador_id=?)');
      $used->execute([$id,$id]);
      if ((int)$used->fetchColumn() > 0) throw new UsuarioOperacionError('Esta cuenta tiene movimientos registrados. Desactívala para conservar la trazabilidad.',409);
      $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
    } elseif ($action === 'update') {
      if (!array_key_exists('rol',$data) && !array_key_exists('activo',$data)) throw new UsuarioOperacionError('No hay cambios para guardar.');
      if ($target['rol'] !== $role || (int)$target['activo'] !== (int)$active) {
        $pdo->prepare('UPDATE usuarios SET rol=?,activo=?,session_version=session_version+1 WHERE id=?')->execute([$role,(int)$active,$id]);
      }
    } else throw new UsuarioOperacionError('Operación inválida.');
    return ['ok'=>true];
  });
}