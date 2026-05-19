<?php
// api/validar_rut.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/rut.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['ADMIN','GUARDIA']);


function input_data(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  return $_POST;
}

try {
  $data = input_data();
  $rut = $data['rut'] ?? '';
  $rut = rut_normalizar($rut);

  if (!rut_es_valido($rut)) {
    echo json_encode([
      'ok' => false,
      'error' => 'RUT inválido (formato o dígito verificador).',
      'rut' => $rut
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo = db();
  $st = $pdo->prepare("SELECT rut, nombres, unidad, grado, estado FROM funcionarios WHERE rut = ?");
  $st->execute([$rut]);
  $f = $st->fetch();

  if (!$f) {
    echo json_encode([
      'ok' => true,
      'rut' => $rut,
      'existe' => false,
      'activo' => false,
      'funcionario' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $activo = ($f['estado'] === 'ACTIVO');

  echo json_encode([
    'ok' => true,
    'rut' => $rut,
    'existe' => true,
    'activo' => $activo,
    'funcionario' => [
      'rut' => $f['rut'],
      'nombres' => $f['nombres'],
      'unidad' => $f['unidad'],
      'grado' => $f['grado'],
      'estado' => $f['estado'],
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
