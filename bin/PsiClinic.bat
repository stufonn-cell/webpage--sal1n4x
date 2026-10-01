@echo off
REM PsiClinic - Windows shortcut.
REM Made by Salinas | github.com/stufonn-cell
REM
REM Change PROJECT_DIR if you cloned the project into another folder inside WSL.

set PROJECT_DIR=~/projects/psiclinic

echo Starting Docker Desktop if it is not running...
tasklist /FI "IMAGENAME eq Docker Desktop.exe" | find /I "Docker Desktop.exe" >nul || (
    start "" "%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
    echo Waiting for Docker Desktop to finish starting...
    timeout /t 35 /nobreak >nul
)

echo Starting PsiClinic...
wsl -d Ubuntu-22.04 -- bash -lc "cd %PROJECT_DIR% && ./bin/psiclinic up"

start http://localhost:8080

echo.
echo PsiClinic is running at http://localhost:8080
echo You can close this window.
pause
