<?php
require_once __DIR__.'/../app/auth.php';
require_api_roles(['ADMIN','GUARDIA','CONTROL_VISITA','AUDITOR'],['GET','POST']);
echo json_encode(['ok'=>true,'expires_at'=>auth_expires_at(),'server_time'=>time()],JSON_UNESCAPED_UNICODE);