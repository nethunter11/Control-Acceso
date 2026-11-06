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

## Rechazos de RUT

Los RUT inválidos o no registrados muestran `RECHAZADO` y el motivo, sin producir un
error interno. La respuesta indica `guardado: false`: estos intentos no se agregan
al historial de movimientos, que exige un funcionario existente. Los movimientos
de funcionarios existentes conservan sus reglas y su registro habitual.

Para comprobar este comportamiento con el servidor de pruebas iniciado, ejecuta
`C:\xampp\php\php.exe tests\rut_rechazos.php` desde la carpeta de pruebas. La prueba
comprueba entradas y salidas para ADMIN y GUARDIA, sin agregar movimientos a la base.

## Movimientos simultáneos

Las API coordinan por RUT la comprobación del estado y el registro del movimiento.
Si dos puestos solicitan la misma entrada o salida a la vez, una solicitud puede
aprobarse y la siguiente comprueba el estado actualizado antes de responder.
Después de una salida se puede registrar una nueva entrada normalmente.

Las entradas de visitas también se coordinan por RUT. Para sus salidas, solo se
informa `APROBADO` si la solicitud cambió efectivamente la visita de `DENTRO` a
`SALIO`. Así se evita informar varias salidas aprobadas para una sola visita.

La coordinación incluye el nombre de la base de datos: pruebas y estable son
independientes. Los RUT distintos tampoco comparten turno. Si una solicitud espera
más de cinco segundos, recibe HTTP `409` con un mensaje para intentar nuevamente.

`C:\xampp\php\php.exe tests\concurrencia.php` comprueba las API en procesos PHP
independientes y con sesiones distintas; no depende del servidor web. Antes de
ejecutarlo se necesita un respaldo SQL de pruebas con restauración comprobada.
La comprobación crea datos y retardos exclusivos para la prueba y los retira al
terminar, verificando que las cuatro tablas conserven su contenido.

## Operador de cada movimiento

Cada nuevo movimiento guardado de funcionarios conserva `operador_id`, tomado de
la sesión del usuario que lo registra. El navegador no puede elegir el operador
enviando otro identificador. Se aplica tanto a los movimientos aprobados como a los
rechazos que sí se guardan en el historial.

La API del historial identifica al operador. En la pantalla de registros se
muestran **Operador entrada** y **Operador salida**, porque pueden
corresponder a usuarios distintos. Los registros antiguos sin esa información
aparecen como **Sin registro**; no se les asigna un operador de forma retroactiva.
Una salida todavía pendiente se muestra con un guion.

## Pórticos

En Validación de acceso, **Puesto** es un desplegable con tres opciones: **Pórtico
Principal**, **Pórtico Sur** y **Pórtico Norte**. La selección inicial es Principal.
El servidor valida las opciones antes de registrar un movimiento.

El formulario de visitas no incluye un puesto editable. Todos los nuevos ingresos
de visitas se asignan desde el servidor a **Pórtico de Visitas**. Los nombres de
puestos de registros históricos se conservan.

`C:\xampp\php\php.exe tests\puestos.php` comprueba el desplegable, las tres opciones
en MariaDB, el rechazo de otros valores y el pórtico fijo de visitas. Requiere un
respaldo SQL de pruebas con restauración comprobada y el servidor iniciado; los
datos temporales se retiran al terminar.

## Configuración y exportaciones

Configuración organiza sus opciones en tres secciones desplegables: **Gestión de
usuarios**, **Sistema / diagnóstico** y **Exportaciones**. La comprobación de la
base de datos se actualiza al abrir diagnóstico y también puede repetirse con
**Probar conexión**.

Para descargar registros, abre **Exportaciones**, elige **Registros de acceso** o
**Registros de visitas**, selecciona **Un día** y la fecha, y pulsa **Descargar CSV**.
También puedes elegir **Todos los registros**. Funcionarios y usuarios se exportan
como listados completos; el archivo de usuarios no contiene contraseñas ni sus
hashes. Estas descargas requieren una sesión de administrador.

En visitas puedes filtrar por **Solo ingresos del día**, **Solo salidas del día** o
**Ingresos o salidas del día**. Esta última opción incluye una visita una sola vez
si ingresó o salió en la fecha elegida, aunque la otra actividad ocurriera otro día.
Una visita que permaneció dentro todo el día sin ingresar ni salir ese día no se
incluye con ese criterio. Accesos usa la fecha de cada movimiento.

Los archivos incluyen encabezados aunque el filtro no encuentre registros, usan
UTF-8 para conservar tildes en Excel y llevan la fecha en su nombre cuando se
filtran por día. Los CSV incorporan el nombre del operador registrado; en visitas
corresponde al operador de ingreso. Los registros antiguos sin operador dejan esa
celda vacía.

Los textos que pueden interpretarse como fórmulas al abrir el CSV se prefijan con
un tabulador, incluidos teléfonos que empiezan con `+`. Esto permite tratarlos como
texto en Excel; si procesas el archivo con otro programa, considera ese prefijo.

`C:\xampp\php\php.exe tests\exportaciones.php` verifica filtros diarios y completos,
límites de medianoche, visitas entre días, permisos y contenido de los CSV. Requiere
el servidor de pruebas y un respaldo SQL con restauración comprobada. Retira sus
datos temporales y verifica el contenido de las cuatro tablas al terminar.

## Diseño y lectura en pórticos

El inicio de sesión y las cinco secciones comparten una identidad institucional
oscura: azul marino, superficies de alto contraste, controles amplios y tablas
contenidas en paneles con desplazamiento horizontal cuando hace falta espacio.
Se conserva el escudo institucional y se unifican los textos en español.

El botón **Contraste** activa una variante oscura con textos y bordes más marcados.
La preferencia se guarda en ese navegador. El menú puede contraerse en escritorio;
en pantallas pequeñas se abre sobre el contenido y se cierra con el botón de
cierre, al pulsar fuera o con **Escape**. Las páginas admiten navegación por teclado
y un enlace **Ir al contenido**.

En Validación, las acciones principales son **Registrar entrada** y **Registrar
salida**. Se mantienen el atajo Enter para registrar una entrada desde RUT, la
consulta independiente, los puestos permitidos y las reglas de registro.
Reportes conserva los indicadores y distingue entradas en azul y salidas en ámbar.
Los listados vacíos muestran un mensaje para distinguirlos de una carga pendiente.

El punto anterior al rediseño está identificado por
`respaldo-pruebas-2026-11-06` (commit `48229d0`). El cambio visual se prepara en
`pruebas` para revisarlo antes de integrarlo en la versión estable.

## Requisitos actuales

- PHP 8.2 y la extensión PDO MySQL.
- MariaDB 10.4 o una versión compatible.
- XAMPP en `C:\xampp` para los accesos directos locales.
- Git para mantener el historial y las dos carpetas de trabajo.

La configuración de la versión estable sigue en `app/db.php`. La preparación del
entorno de pruebas se mantiene en la rama `pruebas`.
