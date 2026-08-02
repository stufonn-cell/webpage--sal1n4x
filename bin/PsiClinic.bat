@echo off
REM PsiClinic - acceso directo para Windows.
REM Hecho por Salinas | github.com/stufonn-cell
REM
REM Ajusta RUTA si clonaste el proyecto en otra carpeta dentro de WSL.

set RUTA=~/proyectos/psiclinic

echo Iniciando Docker Desktop si no esta activo...
tasklist /FI "IMAGENAME eq Docker Desktop.exe" | find /I "Docker Desktop.exe" >nul || (
    start "" "%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
    echo Esperando a que Docker Desktop termine de arrancar...
    timeout /t 35 /nobreak >nul
)

echo Levantando PsiClinic...
wsl -d Ubuntu-22.04 -- bash -lc "cd %RUTA% && ./bin/psiclinic up"

start http://localhost:8080

echo.
echo PsiClinic esta corriendo en http://localhost:8080
echo Esta ventana se puede cerrar.
pause
