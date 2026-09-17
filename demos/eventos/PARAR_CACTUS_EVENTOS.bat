@echo off
setlocal
cd /d "%~dp0"
set "PIDFILE=%~dp0storage\server.pid"
if not exist "%PIDFILE%" (
  echo Nenhum PID do servidor foi encontrado.
  exit /b 0
)
set /p PID=<"%PIDFILE%"
if "%PID%"=="" exit /b 0
powershell -NoProfile -Command "$p=%PID%; $proc=Get-Process -Id $p -ErrorAction SilentlyContinue; if($proc -and $proc.ProcessName -eq 'php'){Stop-Process -Id $p -Force; Write-Host 'Servidor Cactus Eventos encerrado.'} else {Write-Host 'O processo salvo nao esta mais ativo.'}"
del /q "%PIDFILE%" 2>nul
del /q "%~dp0storage\server.port" 2>nul
endlocal
