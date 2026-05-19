<?php
require_once __DIR__ . '/../app/auth.php';
require_roles(['ADMIN']);

require_once __DIR__ . '/../app/db.php';

$table = strtolower(trim($_GET['table'] ?? ''));
$allowed = ['usuarios','funcionarios','registros_acceso','visitas'];

if (!in_array($table, $allowed, true)) {
  http_response_code(400);
  exit('Tabla inválida');
}

$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$table.'.csv"');

$out = fopen('php://output', 'w');

// Saca todo (simple). Si quieres limitar columnas, lo ajustamos.
$st = $pdo->query("SELECT * FROM `$table`");
$first = $st->fetch(PDO::FETCH_ASSOC);

if (!$first) {
  // cabecera vacía
  fputcsv($out, []);
  fclose($out);
  exit;
}

fputcsv($out, array_keys($first));
fputcsv($out, array_values($first));

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, array_values($row));
}

fclose($out);