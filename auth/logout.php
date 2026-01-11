<?php
require_once __DIR__ . '/../app/auth.php';
auth_logout();
header('Location: /Control-Acceso/auth/login.php');
exit;
