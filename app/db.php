<?php
// app/db.php
require_once __DIR__ . '/config.php';

function db(): PDO {
  $database = app_config()['database'];
  $host = $database['host'];
  $port = (int)$database['port'];
  $dbname = $database['name'];
  $user = $database['user'];
  $pass = $database['password'];
  $charset = $database['charset'];

  $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Cada conexión usa el desfase vigente de Santiago; no requiere tablas horarias en MariaDB.
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . desfase_horario_chile() . "'",
  ];
  return new PDO($dsn, $user, $pass, $opt);
}
