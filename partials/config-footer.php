<?php if (!isset($configScript)) { http_response_code(404); exit; } ?>
    </section>
  </main>
</div>
<?php include __DIR__.'/scripts.php'; ?>
<?php if ($activePage === 'config_sistema'): ?>
<script src="/Control-Acceso/assets/vendor/chartjs/chart.umd.min.js"></script>
<?php endif; ?>
<script src="/Control-Acceso/assets/js/<?= $configScript ?>.js?v=<?= filemtime(__DIR__.'/../assets/js/'.$configScript.'.js') ?>"></script>
</body>
</html>
