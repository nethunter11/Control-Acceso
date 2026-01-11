<?php
// api/ultimos.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/db.php';

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
    $where[] = "rut LIKE ?";
    $params[] = "%" . $rut . "%";
  }

  if (in_array($resultado, ['APROBADO', 'RECHAZADO'], true)) {
    $where[] = "resultado = ?";
    $params[] = $resultado;
  }

  if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = "fecha_hora >= ?";
    $params[] = $desde . " 00:00:00";
  }

  if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = "fecha_hora <= ?";
    $params[] = $hasta . " 23:59:59";
  }

  $sql = "SELECT id, rut, tipo, modo, fecha_hora, puesto, resultado, motivo
          FROM registros_acceso";

  if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
  }

  $sql .= " ORDER BY fecha_hora DESC, id DESC LIMIT " . $limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
