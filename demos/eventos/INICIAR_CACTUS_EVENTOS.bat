@echo off
setlocal
cd /d "%~dp0"
title Cactus Eventos White Label - Inicializador
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\bootstrap.ps1"
if errorlevel 1 (
  echo.
  echo O inicializador encontrou um erro. Consulte a mensagem acima.
  pause
)
endlocal
