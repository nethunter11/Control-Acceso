# Control de Acceso

Aplicación web en PHP y MariaDB para validar accesos, registrar entradas y salidas,
gestionar visitas y consultar reportes. Se ejecuta localmente con XAMPP.

## Versiones del proyecto

| Uso | Rama de Git | Dirección local |
| --- | --- | --- |
| Versión estable | `main` | http://localhost/Control-Acceso/ |
| Versión de pruebas | `pruebas` | http://localhost:8081/Control-Acceso/ |

La carpeta estable es `C:\xampp\htdocs\Control-Acceso`.
La carpeta de pruebas es
`C:\xampp\htdocs\Control-Acceso-pruebas\web\Control-Acceso`.
Git mantiene ambas carpetas como *worktrees*: cada una tiene su propia rama y sus
archivos, y ambas comparten el historial. No cambies de rama dentro de la carpeta
estable para realizar pruebas.

La etiqueta **`base-estable-2026-11-05`** identifica el estado funcional guardado
antes de preparar este entorno. Incluye los cinco archivos que estaban modificados
al comenzar, además del historial anterior. La rama antigua `master` se conserva
como referencia histórica.

## Cómo probar cambios

1. Inicia **MySQL** desde el panel de XAMPP. Para abrir también la versión estable,
   inicia **Apache**.
2. Ejecuta `C:\xampp\htdocs\Control-Acceso-pruebas\Abrir-pruebas.cmd`.
3. Abre http://localhost:8081/Control-Acceso/ y revisa los cambios.
4. Indica cuáles quieres conservar, ajustar o deshacer. Los cambios se preparan
   en `pruebas`; pasan a `main` después de tu aprobación.

En VS Code, abre la carpeta de pruebas indicada arriba cuando quieras trabajar con
Codex en nuevas mejoras. La ventana de la versión estable puede permanecer abierta
para comparar.

Las dos versiones usan bases de datos y sesiones distintas. La versión de pruebas
usa `control_acceso_pruebas` y una cuenta de MariaDB con permisos únicamente sobre
esa base. Su configuración privada está en `app/config.local.php`, que Git ignora.
El servidor de pruebas escucha solamente en `127.0.0.1:8081`.

El servidor de pruebas es para desarrollo local. No debe utilizarse como servidor
de producción ni exponerse a la red pública.

## Respaldos y cómo volver atrás

El respaldo inicial está en
`%USERPROFILE%\Documents\Control-Acceso-respaldos\base-2026-11-05-*\`.
Contiene un ZIP del proyecto completo, incluyendo `.git`, una exportación SQL de
`control_acceso`, un parche con los cambios originales y sus comprobaciones SHA-256.
Conserva otra copia en un disco distinto o almacenamiento privado.

- **Código:** Git permite recuperar versiones guardadas y revertir cambios. Pide
  el cambio por su descripción; no necesitas ejecutar comandos de restauración.
- **Datos:** Git no guarda los registros de MariaDB. Antes de cambios en tablas o
  datos se necesita un respaldo SQL independiente y comprobar su restauración en
  pruebas.
- **Publicación:** pasar código a `main` no copia la base de pruebas sobre la
  estable. Los cambios de estructura se revisan por separado para conservar los
  registros reales.

Restaurar una base de datos antigua puede eliminar registros agregados después del
respaldo. Debe planificarse antes de ejecutar una restauración sobre la versión
estable.

No subas a GitHub los respaldos, exportaciones con datos personales ni archivos de
configuración local. Este repositorio contiene el código y la documentación.

## Requisitos actuales

- PHP 8.2 y la extensión PDO MySQL.
- MariaDB 10.4 o una versión compatible.
- XAMPP en `C:\xampp` para los accesos directos locales.
- Git para mantener el historial y las dos carpetas de trabajo.

La configuración de la versión estable sigue en `app/db.php`. La preparación del
entorno de pruebas se mantiene en la rama `pruebas`.
