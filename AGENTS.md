# Trabajo en Control-Acceso

- La carpeta `C:\xampp\htdocs\Control-Acceso` y la rama `main` son la version estable.
- Prepara nuevas mejoras funcionales en la rama `pruebas`, cuya carpeta es `C:\xampp\htdocs\Control-Acceso-pruebas\web\Control-Acceso`. Usa esa carpeta como directorio de trabajo para los cambios de la aplicacion.
- Conserva el comportamiento de la version estable mientras se revisan las mejoras. Integra cambios funcionales en `main` cuando el usuario pida o apruebe pasarlos a la version estable.
- Guarda cambios pequenos con descripciones claras para poder revertir una mejora concreta. Comprueba el estado de Git y conserva cambios previos del usuario.
- Por solicitud del usuario del 10 de noviembre de 2026, crea el commit correspondiente al terminar cada cambio futuro. Mantén las mejoras en pruebas hasta que autorice pasarlas a la versión oficial.
- La etiqueta `base-estable-2026-11-05` conserva el punto de partida. No reescribas ni borres el historial de respaldo.
- La base estable es `control_acceso`; la base de pruebas es `control_acceso_pruebas`. No copies datos de pruebas sobre la base estable al integrar codigo.
- La cuenta `ca_pruebas` solo debe tener permisos sobre la base de pruebas. Su clave esta en `app/config.local.php`, ignorado por Git; no la muestres ni la publiques.
- Los respaldos completos y SQL se guardan en `%USERPROFILE%\Documents\Control-Acceso-respaldos`, fuera de la carpeta publica de Apache y de GitHub.
- Antes de cambios de estructura o datos, prepara un respaldo SQL y comprueba su restauracion en pruebas. Una restauracion de datos estables requiere que el usuario la haya solicitado, por su efecto sobre registros posteriores al respaldo.
- Consulta `README.md` para las direcciones locales y el acceso directo de inicio.

