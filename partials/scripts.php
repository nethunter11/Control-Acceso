<?php $UI_VER = filemtime(__DIR__ . '/../assets/js/ui.js'); ?>
<script src="/Control-Acceso/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/Control-Acceso/assets/js/tablas.js?v=<?= filemtime(__DIR__ . '/../assets/js/tablas.js') ?>"></script>
<script src="/Control-Acceso/assets/js/fechas.js?v=<?= filemtime(__DIR__ . '/../assets/js/fechas.js') ?>"></script>
<?php if (auth_user()): ?>
<script id="appSession" type="application/json"><?= json_encode(['csrf'=>csrf_token(),'expires_at'=>auth_expires_at()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="/Control-Acceso/assets/js/sesion.js?v=<?= filemtime(__DIR__.'/../assets/js/sesion.js') ?>"></script>
<script src="/Control-Acceso/assets/js/dialogos.js?v=<?= filemtime(__DIR__.'/../assets/js/dialogos.js') ?>"></script>
<?php endif; ?>
<script src="/Control-Acceso/assets/js/ui.js?v=<?= $UI_VER ?>"></script>
