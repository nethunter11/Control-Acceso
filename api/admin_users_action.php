<?php
// Punto común de las operaciones de usuarios; los archivos públicos fijan la acción.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/usuarios.php';
require_api_roles(['ADMIN'],['POST']);
if (!isset($userAction)) { http_response_code(404); exit; }
try {
  $data = json_decode(file_get_contents('php://input'),true);
  if (!is_array($data)) throw new UsuarioOperacionError('Los datos de la solicitud no son válidos.');
  $response = usuario_admin_operar(db(),auth_user(),$userAction,$data);
  echo json_encode($response,JSON_UNESCAPED_UNICODE);
} catch (UsuarioOperacionError $error) {
  http_response_code($error->status);
  echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (BloqueoRutOcupado $error) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'Hay otra administración de usuarios en curso. Reintenta.'],JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
  error_log('Administración de usuarios: '.$error->getMessage());
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se pudo guardar la operación.'],JSON_UNESCAPED_UNICODE);
}