@echo off
setlocal
title Control-Acceso - Entorno de pruebas
set "CONTROL_ACCESO_ENV=testing"
for %%I in ("%~dp0..\..") do set "TEST_DOCUMENT_ROOT=%%~fI"

if not exist "C:\xampp\php\php.exe" (
  echo No se encontro PHP en C:\xampp\php\php.exe.
  pause
  exit /b 1
)

"C:\xampp\php\php.exe" "%~dp0verificar-entorno.php"
if errorlevel 1 (
  pause
  exit /b 1
)

echo.
echo Abre http://localhost:8081/Control-Acceso/ en tu navegador.
echo Deja esta ventana abierta mientras realizas pruebas.
echo Para detener las pruebas, presiona Ctrl+C en esta ventana.
echo.
"C:\xampp\php\php.exe" -S 127.0.0.1:8081 -t "%TEST_DOCUMENT_ROOT%"
if errorlevel 1 (
  echo.
  echo No se pudo iniciar el servidor. Si ya esta abierto, usa la direccion anterior.
  pause
  exit /b 1
)
