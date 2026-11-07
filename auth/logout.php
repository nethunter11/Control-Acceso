<?php
require_once __DIR__.'/../app/auth.php';
auth_start_session();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!csrf_validate($_POST['csrf'] ?? null)) { http_response_code(403); echo 'Recarga la página antes de cerrar sesión.'; exit; }
auth_logout();
header('Location: /Control-Acceso/auth/login.php');