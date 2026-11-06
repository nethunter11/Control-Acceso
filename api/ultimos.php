<?php
// api/ultimos.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['ADMIN','GUARDIA','AUDITOR']);

try {
  $pdo = db();

  // Filtros (GET)
  $rut = trim($_GET['rut'] ?? '');
  $resultado = strtoupper(trim($_GET['resultado'] ?? '')); // APROBADO | RECHAZADO | (vacío = ambos)
  $desde = trim($_GET['desde'] ?? ''); // YYYY-MM-DD
  $hasta = trim($_GET['hasta'] ?? ''); // YYYY-MM-DD
  $limit = intval($_GET['limit'] ?? 15);
  if ($limit < 1) $limit = 15;
  if ($limit > 200) $limit = 200;

  $where = [];
  $params = [];

  if ($rut !== '') {
    // Búsqueda flexible: contiene
    $where[] = "r.rut LIKE ?";
    $params[] = "%" . $rut . "%";
  }

  if (in_array($resultado, ['APROBADO', 'RECHAZADO'], true)) {
    $where[] = "r.resultado = ?";
    $params[] = $resultado;
  }

  if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = "r.fecha_hora >= ?";
    $params[] = $desde . " 00:00:00";
  }

  if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = "r.fecha_hora <= ?";
    $params[] = $hasta . " 23:59:59";
  }

  $sql = "SELECT r.id, r.rut, r.tipo, r.modo, r.fecha_hora, r.puesto, r.resultado, r.motivo,
                 r.operador_id, u.username AS operador
          FROM registros_acceso r
          LEFT JOIN usuarios u ON u.id = r.operador_id";

  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }

  $sql .= " ORDER BY r.fecha_hora DESC, r.id DESC LIMIT " . $limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
