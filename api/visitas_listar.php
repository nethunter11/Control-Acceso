<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

require_roles(['ADMIN','CONTROL_VISITA']);

try {
  $pdo = db();

  $fecha = trim($_GET['fecha'] ?? '');     // YYYY-MM-DD
  $rut = trim($_GET['rut'] ?? '');
  $estado = strtoupper(trim($_GET['estado'] ?? '')); // DENTRO / SALIO / (vacío)
  $limit = intval($_GET['limit'] ?? 50);
  if ($limit < 1) $limit = 50;
  if ($limit > 200) $limit = 200;

  $where = [];
  $params = [];

  if ($rut !== '') {
    $where[] = "rut LIKE ?";
    $params[] = "%$rut%";
  }

  if ($estado !== '' && in_array($estado, ['DENTRO','SALIO'], true)) {
    $where[] = "estado = ?";
    $params[] = $estado;
  }

  if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $where[] = "fecha_hora_entrada >= ? AND fecha_hora_entrada <= ?";
    $params[] = $fecha . " 00:00:00";
    $params[] = $fecha . " 23:59:59";
  }

  $sql = "SELECT id, nombre, rut, telefono, modo, patente, puesto, motivo,
                 fecha_hora_entrada, fecha_hora_salida, estado
          FROM visitas";

  if ($where) $sql .= " WHERE " . implode(" AND ", $where);

  $sql .= " ORDER BY fecha_hora_entrada DESC LIMIT " . $limit;

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}