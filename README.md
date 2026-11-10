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

La presencia operativa utiliza la última acción APROBADA registrada (ID más alto),
no la fecha más alta del historial. Así, una salida importada con fecha futura o
un retroceso del reloj no permite entradas repetidas. Los rechazos no cambian la
presencia. La ficha y Personal dentro usan este mismo criterio. No se reescriben
los movimientos anteriores; el resumen histórico conserva su orden cronológico.
Prueba de regresión: `C:\xampp\php\php.exe tests/duplicados_acceso.php`.

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

El botón **Configuración** del menú izquierdo despliega tres enlaces: **Gestión**,
**Sistema** y **Exportaciones**. Cada uno abre una pantalla independiente. Los tres
requieren una sesión de administrador; la dirección anterior
`configuraciones.php` redirige a Gestión.

- `configuracion/gestion.php`: cuentas, roles y permisos de usuarios.
- `configuracion/sistema.php`: monitor de recursos y conexión a MariaDB.
- `configuracion/exportaciones.php`: descarga de listados CSV.

Para descargar registros, abre **Exportaciones**, elige **Registros de acceso** o
**Registros de visitas** y selecciona **Un día** o **Rango de días**. En un rango,
completa **Desde** y **Hasta** y pulsa **Descargar CSV**. Se incluyen ambos días
completos en horario de Chile. El rango puede ser de un solo día; no se aceptan
fechas incompletas, invertidas ni descargas de movimientos sin un periodo elegido.
Funcionarios y usuarios se exportan como listados completos; el archivo de usuarios
no contiene contraseñas ni sus hashes. Todas las descargas requieren ADMIN.

En visitas puedes filtrar por **Solo ingresos del periodo**, **Solo salidas del
periodo** o **Ingresos o salidas del periodo**. Esta última opción incluye una
visita una sola vez si ingresó o salió dentro de las fechas elegidas. Una visita
que permaneció dentro todo el periodo sin ingresar ni salir no se incluye con ese
criterio. Accesos usa la fecha de cada movimiento.

Los archivos incluyen encabezados aunque no haya resultados, usan UTF-8 y llevan
el día o las dos fechas del rango en su nombre. Los CSV incorporan el nombre del
operador registrado; visitas incluye los operadores de entrada y salida. Los
registros antiguos sin operador dejan esa celda vacía.

Los textos que pueden interpretarse como fórmulas al abrir el CSV se prefijan con
un tabulador, incluidos teléfonos que empiezan con `+`. Esto permite tratarlos como
texto en Excel; si procesas el archivo con otro programa, considera ese prefijo.

`C:\xampp\php\php.exe tests\exportaciones.php` verifica filtros diarios y por rango,
límites de medianoche, visitas entre días, permisos y contenido de los CSV. Requiere
el servidor de pruebas y un respaldo SQL con restauración comprobada. Retira sus
datos temporales y verifica el contenido de las cuatro tablas al terminar.

## Monitor de Sistema

Sistema muestra lecturas reales del equipo que ejecuta XAMPP: uso total de CPU,
memoria física utilizada y disponible, ocupación del volumen del proyecto, tiempo
encendido, estado de MariaDB y latencia de conexión. No representa exclusivamente
los recursos utilizados por esta aplicación. Las cantidades de memoria y disco se
expresan en GiB (1 GiB = 1 073 741 824 bytes).

En Windows, PHP ejecuta un colector PowerShell de solo lectura con consultas CIM.
No instala servicios, controladores ni cambia políticas de ejecución. La lectura
de CPU usa dos muestras de los contadores de Windows; RAM procede de
`Win32_OperatingSystem`. La tarjeta de temperatura muestra un valor fijo de
**45 °C**, una barra ámbar con escala de 0 a 100 °C y el texto **Temperatura del
sistema**. Es una referencia visual; no se guarda como una medición del equipo.

El colector y la columna **Temperatura real** del historial conservan exclusivamente
la lectura de una zona térmica ACPI compatible cuando Windows la proporciona;
esa lectura **no identifica la temperatura interna de CPU**. Sin sensor, el
historial muestra un guion. Las demás métricas ausentes aparecen como
**No disponible**, sin reemplazar el dato por cero.

El panel consulta aproximadamente cada 10 segundos, permite pausar y actualizar
manualmente, y suspende las consultas al ocultar la pestaña. Varias pestañas
comparten una caché para evitar ejecutar colectores simultáneos. Los procesos de
lectura tienen un tiempo máximo de 8 segundos y los errores no publican rutas ni
credenciales.

El gráfico permite consultar los últimos 15 minutos, una hora o seis horas. Guarda
solo las lecturas obtenidas mientras Sistema está activo; los intervalos sin
lecturas aparecen como huecos. El historial tiene un máximo de 2 161 muestras,
se conserva hasta seis horas en una carpeta temporal privada de Windows y puede
desaparecer si el sistema limpia sus temporales. Cada carpeta del proyecto tiene
su propio historial; no se guarda en MariaDB ni en el directorio público.

Los avisos de uso elevado empiezan en 75 % y de uso alto en 90 % para CPU, RAM y
disco. El valor de demostración de la tarjeta de temperatura no representa un
diagnóstico térmico del equipo.

`C:\xampp\php\php.exe tests\sistema.php` verifica la normalización de métricas,
lecturas ausentes, bloqueo, caché, recuperación del historial y permisos por HTTP.
Requiere el servidor de pruebas iniciado y las cuatro cuentas de rol activas.
Retira sus archivos y sesiones temporales sin modificar registros de MariaDB.

Referencias del colector: [contadores de rendimiento de Windows](https://learn.microsoft.com/en-us/windows/win32/wmisdk/monitoring-performance-data),
[memoria y tiempo de arranque](https://learn.microsoft.com/en-us/windows/win32/cimwin32prov/win32-operatingsystem)
y [zonas térmicas ACPI](https://learn.microsoft.com/en-us/windows-hardware/design/device-experiences/design-guide).

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
salida**. Se mantienen el atajo Enter para consultar un RUT, la
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

### Historial de entradas y salidas

El resumen empareja únicamente una entrada aprobada con la salida aprobada
inmediatamente siguiente del mismo RUT, ordenando por fecha e ID. Cada rechazo
se conserva por separado, incluidas las salidas rechazadas. Las salidas antiguas
sin entrada y las entradas duplicadas permanecen visibles sin inventar parejas.
Las fechas completas distinguen permanencias nocturnas; el filtro por día incluye
la pareja si su entrada o su salida ocurrió ese día.
Prueba: `C:\xampp\php\php.exe tests\historial.php` (requiere respaldo SQL verificado).

### Personal dentro

Se consulta el último movimiento aprobado registrado de cada RUT (ID más alto).
Los ingresos sin una salida posterior registrada permanecen después de medianoche.

La desactivación administrativa no oculta a alguien que continúa dentro.
La pantalla actualiza cada 15 segundos mientras está visible, muestra la última
consulta correcta y avisa si no puede actualizar. El contador conserva el total
aunque se filtre la tabla por RUT.
Prueba: `C:\xampp\php\php.exe tests\personal_dentro.php`.
### Fechas y horario de Chile

La aplicación usa `America/Santiago` en PHP y JavaScript. Cada conexión de la
aplicación a MariaDB recibe el desfase vigente calculado por PHP; no modifica
el huso global de XAMPP ni necesita cargar tablas horarias de MariaDB.
Las fechas visibles usan DD-MM-YYYY HH:mm. Las lecturas técnicas de Sistema
conservan segundos cuando corresponda. El formato es de presentación: filtros,
respuestas de las API, fechas de la base y exportaciones CSV conservan sus valores
y formatos originales. Un DATETIME local de Chile se muestra sin convertirlo de
huso; los instantes que incluyen zona horaria se convierten a America/Santiago.
Los filtros usan días civiles completos: desde las 00:00:00 inclusive hasta
las 00:00:00 del día siguiente exclusive. Se rechazan fechas imposibles y rangos
invertidos con HTTP 400. Los movimientos existentes conservan sus DATETIME locales;
no se realiza una migración de datos. Los horarios de verano e invierno dependen
de las reglas de zona horaria instaladas en PHP y en el navegador.

Pruebas: `C:\xampp\php\php.exe tests\fechas.php` y, con Node.js disponible,
`node tests/ui_accesos.js`. La prueba de interfaz valida la lógica con elementos
simulados; no sustituye la comprobación visual en el navegador.

Referencias: [PHP: zona horaria](https://www.php.net/manual/en/function.date-default-timezone-set.php),
[MariaDB: zona de sesión](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/internationalization-and-localization/time-zones),
[Intl.DateTimeFormat](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat/formatToParts).
### Protección de administración de usuarios

Las operaciones administrativas se coordinan en una transacción y vuelven a
validar al administrador antes de guardar. No se permite eliminar, desactivar
ni degradar la cuenta propia, ni perder el último administrador activo. Las
cuentas con movimientos se desactivan en lugar de eliminarlas. Los cambios de
rol, estado o contraseña incrementan una versión de sesión; las sesiones
anteriores deben autenticarse otra vez, incluso si la cuenta se reactiva.
Migración de pruebas: `C:\xampp\php\php.exe tools/migrar-pruebas.php`, después de
respaldar SQL y verificar su restauración. Prueba: `tests/usuarios_seguridad.php`.
### Sesiones y acciones protegidas

El límite elegido es de 60 minutos sin actividad y 12 horas desde el acceso.
Los refrescos de datos no renuevan la sesión. La pantalla comunica actividad
real al servidor mediante POST con CSRF. Se utilizan cookies HttpOnly, SameSite
Lax, modo estricto y Secure cuando se sirve por HTTPS. Las acciones de escritura
y el cierre de sesión solo admiten POST con token; las APIs responden JSON 401,
403 o 405 según corresponda. El inicio valida un token no vacío y limita a ocho
intentos fallidos por usuario e IP cada diez minutos. Las contraseñas se
restablecen en un diálogo con campo oculto. Prueba: `tests/sesiones_seguridad.php`.
Referencia: [OWASP: sesiones](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
### Control de visitas

La vista inicial Dentro ahora incluye todas las visitas sin salida, aunque hayan
ingresado otro día. Historial filtra por fecha de ingreso. Ambas vistas muestran
total, páginas y última consulta; se actualizan cada 15 segundos. Se confirma la
salida con nombre y RUT, y se guarda el operador de la sesión mediante una
actualización condicional que no sobrescribe salidas previas. Los registros
antiguos mantienen el operador desconocido como Sin registro. El CSV incluye los
operadores de entrada y salida. Prueba: `tests/visitas_control.php`.
### Gestión de funcionarios desde la web

Ingresa como ADMIN y abre **Funcionarios** en el menú izquierdo. Puedes buscar
por RUT (con o sin puntos), nombre o unidad, crear fichas y editar nombre, unidad,
grado y estado. El RUT identifica la ficha y no se reemplaza al editar. La
desactivación conserva los movimientos, impide nuevas entradas y permite cerrar
un ingreso aprobado que aún no tenga salida. La lista muestra total y páginas.

Para importar, descarga **Plantilla CSV** y completa estas columnas:
`rut,nombres,unidad,grado,estado`. Guarda en CSV UTF-8, con comas o punto y coma.
Nombre, RUT y estado son obligatorios; estado acepta ACTIVO o INACTIVO. El límite
es de 1 MB y 1000 filas. La exportación de funcionarios existente también puede
revisarse e importarse: su columna fecha_actualizacion se ignora al importar.

**Solo crear** rechaza los RUT ya existentes. **Crear y actualizar** muestra los
valores que cambiarán, incluidos los campos que quedarán vacíos. Revisa todas las
filas y pulsa Confirmar importación: cualquier error bloquea el archivo completo.
La vista previa dura diez minutos y solo se utiliza una vez. Si una ficha cambia
después de revisarla, la operación se revierte completa y debes generar otra vista
previa. Guardar o importar requiere sesión de administrador y CSRF válidos.

Pruebas: `tests/funcionarios.php`, `tests/autenticacion.php` y
`node tests/ui_administracion.js` (con Node.js disponible). Las pruebas de interfaz
simulan elementos de pantalla y solicitudes; la apariencia se revisa en el navegador.

### Recuperación de estas mejoras

Los cuatro puntos se guardan en commits locales separados de pruebas.
Las migraciones agregan la versión de sesión de usuarios y el operador de salida
de visitas; no reescriben movimientos antiguos. Revertir código no revierte datos
editados o importados: conserva los respaldos SQL privados y solicita una
restauración revisando antes los movimientos posteriores al respaldo.
### Operación visual en validación

Solo consultar y Enter muestran una ventana centrada, con cierre mediante X,
Cerrar consulta o Escape. Al cerrar, el foco vuelve al RUT. La ficha destaca el
nombre, RUT, unidad y grado; después muestra la presencia registrada (Dentro,
Fuera o Sin movimientos aprobados) y la fecha y hora de consulta al pie.
Cada entrada o salida se confirma con nombre, RUT, puesto y modo.
Después del registro, una ventana del mismo estilo muestra el resultado y se
cierra a los 4 segundos (también admite X, Cerrar o Escape). Se aplica a entradas
y salidas de funcionarios y visitas. Conserva los datos personales destacados
y la hora guardada; los errores técnicos permanecen en
pantalla. La consulta simple no tiene cierre automático.
Al cerrar el resultado de una entrada o salida aprobada, se limpia el RUT y el
cursor queda listo para el siguiente funcionario. Consultas, cancelaciones y
rechazos conservan el RUT. El comprobante de la última operación permanece visible.
El resultado conserva la identidad, la fecha guardada y el operador hasta la
siguiente operación o Limpiar. Las entradas usan verde, las salidas ámbar y los
rechazos rojo. Los cambios de permisos se confirman dentro de la aplicación y
los errores aparecen en pantalla. Pruebas: `tests/operacion_visual.php`, `tests/visitas_control.php`,
`node tests/ui_operacion.js` y `node tests/ui_registro_popup.js` con Node.js disponible.
### Tablas y búsquedas

Registros, Personal dentro, Visitas, Usuarios y Funcionarios permiten elegir
25, 50 o 100 filas, muestran el rango y total y permiten ir a una página.
Los encabezados permanecen visibles al desplazar la tabla.

- Registros busca por nombre o RUT, con o sin puntos; filtra por rango de fechas,
  resultado y puesto de entrada o salida, incluidos puestos históricos. La pareja
  se conserva completa al filtrar y paginar. Limpiar consulta todo el historial.
- Personal dentro busca por nombre, RUT o unidad y filtra por puesto de ingreso.
  El contador corresponde al total dentro, aunque la tabla esté filtrada.
- Visitas busca por nombre, RUT o patente. Dentro ahora conserva todos los
  ingresos pendientes; Historial mantiene su filtro por fecha de ingreso.
- Usuarios permite buscar el nombre de cuenta y filtrar rol y estado.
- Funcionarios conserva búsqueda y filtros y añade selección de filas y página.

Las búsquedas tratan % y _ como texto. Las páginas se ordenan por claves estables;
el total y las filas se leen en la misma transacción. Si falla una consulta se
advierte que la lista no está actualizada; Personal dentro muestra — en el
contador hasta verificarlo otra vez. Las consultas nuevas reemplazan las
anteriores y el refresco automático conserva la página.

Pruebas: `tests/tablas_busquedas.php`, `node tests/ui_tablas.js` y
`node tests/ui_operacion.js`. Se verificaron 61 parejas, 122 movimientos y
81 visitas temporales, incluidos empates de fecha, filtros por puesto de salida,
búsquedas vacías y recuperación de páginas fuera del rango. Las pruebas de
interfaz usan elementos simulados; revisa la apariencia en el navegador.
## Versión oficial del 7 de noviembre de 2026

La versión revisada se publica en `main` y se identifica con la etiqueta
`version-oficial-2026-11-07`. Incluye las mejoras de acceso, visitas, funcionarios,
sesiones, tablas y exportaciones por día o rango, junto con la interfaz aprobada.

La carpeta oficial y su base de datos siguen siendo `Control-Acceso` y
`control_acceso`. Las pruebas conservan su carpeta y base separadas. La integración
requiere respaldar ambas bases, verificar una restauración temporal y ensayar las
migraciones antes de aplicarlas a la oficial. No se importan registros de pruebas.

Las migraciones solo agregan control de versión de sesión y operador de salida de
visitas, además de su tabla de seguimiento. Tras respaldar y verificar SQL, desde
la carpeta oficial se ejecuta `C:\xampp\php\php.exe tools\migrar-estable.php --aplicar`.
El comando de pruebas continúa siendo `tools/migrar-pruebas.php`. Ambos registran
las migraciones aplicadas y evitan ejecutarlas simultáneamente.

Después de la actualización, recarga con Ctrl + F5 e inicia sesión nuevamente.
Los usuarios y sus contraseñas se conservan; los movimientos anteriores sin
operador de salida siguen mostrándose sin atribuir una identidad.

## Imágenes de referencia de funcionarios

La consulta de un funcionario registrado muestra su imagen junto a nombre, RUT,
unidad y grado. Las imágenes genéricas se guardan localmente en
img/funcionarios/Imagen_hombre.png e Imagen_mujer.png; no se descargan de
servicios externos durante la consulta. Su selección no representa un dato de
sexo o identidad de género y no se deduce automáticamente del nombre.

La columna nullable funcionarios.imagen conserva la selección por ficha.
En Funcionarios > Editar se puede elegir Imagen hombre, Imagen mujer o Sin imagen
asignada y guardar. Se conservan las imágenes al importar el CSV existente o al
editar desde clientes que no envían ese campo. La selección participa en la
revisión de cambios para impedir sobrescribir una edición posterior.

Después de respaldar y verificar la restauración del SQL, ejecutar
C:\xampp\php\php.exe tools/migrar-pruebas.php en pruebas. La migración
20261110_01_imagen_funcionarios.sql es aditiva e idempotente. La versión oficial
requiere su propia publicación y migración autorizada; no recibe datos de pruebas.
Visitas y los avisos de registro de movimientos conservan su comportamiento.

Verificación: tests/imagenes_funcionarios.php, tests/funcionarios.php y
tests/ui_operacion.js. Los dos primeros requieren el servidor y respaldo SQL
verificado, usan datos temporales y comprueban su limpieza al terminar.

## RUT de funcionarios sin dígito verificador

Los funcionarios se identifican mediante el número de su RUT, por ejemplo
`20420416`. El padrón, los movimientos, las consultas, los popups, las búsquedas
y los CSV de funcionarios/accesos utilizan ese número. Se admiten puntos y
espacios de presentación, pero no ceros iniciales ni más de ocho cifras. No se
calcula ni se almacena un DV para identificar funcionarios.

Un número de ocho cifras se interpreta siempre completo como el cuerpo del RUT.
Para importar padrones anteriores, se admite un RUT con guion y DV explícito:
se comprueba su DV y después se elimina. Un DV explícito incorrecto se rechaza.
No se interpreta una novena cifra sin guion como un DV, para evitar ambigüedades.

Visitas mantiene su RUT completo y la validación de su dígito verificador. Las
funciones de visitas y su presentación no cambian. El teclado o lector puede
introducir el número de funcionario; Enter sigue siendo solo una consulta.
Los movimientos requieren la confirmación habitual.

La conversión de datos existentes se ejecuta una vez, con el servidor de pruebas
detenido y un respaldo SQL cuya restauración ya se haya comprobado:

```powershell
C:\xampp\php\php.exe tools\migrar-rut-funcionarios.php --aplicar
```

La herramienta solo permite `control_acceso_pruebas`. Adapta el padrón y sus
movimientos dentro de una transacción, conserva imágenes y fechas, no desactiva
claves foráneas y no altera visitas. Si detecta dos fichas con el mismo número,
cancela la operación. Se puede repetir sin duplicar datos. No se ejecuta desde
peticiones web. No publicar el código en el entorno oficial sin planificar su
conversión durante una ventana de mantenimiento y respaldar antes esa base.

Comprobaciones: `tests/rut_funcionarios.php`, `tests/rut_rechazos.php`,
`tests/duplicados_acceso.php`, `tests/funcionarios.php`,
`tests/imagenes_funcionarios.php`, `tests/tablas_busquedas.php`,
`tests/visitas_control.php`, `tests/exportaciones.php` y `tests/concurrencia.php`.
Las pruebas usan datos temporales y verifican las huellas de las cuatro tablas.

## Publicación de fotografías y RUT de funcionarios — 10 de noviembre de 2026

La etiqueta `version-oficial-funcionarios-2026-11-10` identifica la publicación de
fotografías de funcionarios y su RUT sin dígito verificador. Visitas conserva el
RUT completo. Las asignaciones personales de imágenes y los respaldos SQL se
mantienen fuera del repositorio.

Durante esta publicación se utiliza una ventana de mantenimiento, se verifica la
restauración del respaldo de la base oficial y se ejecuta desde la carpeta oficial:

```powershell
C:\xampp\php\php.exe tools\migrar-estable.php --aplicar
```

El comando agrega las migraciones pendientes y convierte los identificadores de
funcionarios y sus movimientos en una transacción. No importa datos de pruebas
ni modifica el RUT de visitas. Se verifican las fotografías asignadas y las
consultas HTTP antes de finalizar el mantenimiento.

Para volver al código anterior también se debe revisar el formato de los datos:
revertir únicamente el commit no devuelve los DV al padrón. El SQL anterior y la
referencia del código quedan en el respaldo privado; una restauración sobre la
base oficial debe autorizarse después de revisar los movimientos posteriores.

Desde esta publicación, cada cambio terminado se guarda con su commit. Las
mejoras futuras se preparan en pruebas y se publican cuando el usuario lo solicita.
