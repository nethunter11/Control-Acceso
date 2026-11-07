<?php
$pageInfo = [
  'validacion' => ['Operaciones', 'Validación de acceso', 'Consulta funcionarios y registra sus entradas y salidas.'],
  'visitas' => ['Operaciones', 'Control de visitas', 'Registra ingresos y consulta el estado de cada visita.'],
  'funcionarios' => ['Administración', 'Funcionarios', 'Administra el personal institucional y revisa los cambios antes de importar.'],
  'registros' => ['Seguimiento', 'Registros de acceso', 'Consulta movimientos, resultados y operadores.'],
  'reportes' => ['Seguimiento', 'Reportes', 'Revisa la actividad de acceso y su distribución en el tiempo.'],
  'config' => ['Administración', 'Configuración', 'Administra usuarios, revisa el sistema y exporta tus registros.'],
  'config_gestion' => ['Configuración', 'Gestión de usuarios', 'Administra las cuentas, roles y permisos del sistema.'],
  'config_sistema' => ['Configuración', 'Sistema', 'Supervisa el equipo que ejecuta XAMPP y el estado de la base de datos.'],
  'config_exportaciones' => ['Configuración', 'Exportaciones', 'Descarga registros completos o selecciona un día de consulta.'],
][$activePage ?? ''] ?? ['Control de acceso', 'Panel operativo', ''];
?>
<div class="page-heading">
  <div>
    <div class="page-eyebrow"><?= htmlspecialchars($pageInfo[0], ENT_QUOTES, 'UTF-8') ?></div>
    <h1><?= htmlspecialchars($pageInfo[1], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($pageInfo[2], ENT_QUOTES, 'UTF-8') ?></p>
  </div>
  <span class="institution-tag"><i class="bi bi-shield-check" aria-hidden="true"></i>Uso institucional</span>
</div>
