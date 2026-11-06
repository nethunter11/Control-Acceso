<?php
require_once __DIR__.'/app/auth.php';
require_roles(['ADMIN']);
header('Location: /Control-Acceso/configuracion/gestion.php');
exit;
