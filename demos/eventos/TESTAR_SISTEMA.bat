@echo off
setlocal
cd /d "%~dp0"
set "PHP=%~dp0runtime\php\php.exe"
if not exist "%PHP%" (
  echo O PHP portatil ainda nao foi instalado.
  echo Execute primeiro INICIAR_CACTUS_EVENTOS.bat e depois rode este teste.
  pause
  exit /b 1
)
"%PHP%" "%~dp0tools\selftest.php"
set ERR=%ERRORLEVEL%
echo.
if "%ERR%"=="0" (echo TESTE CONCLUIDO COM SUCESSO.) else (echo O TESTE ENCONTROU FALHAS.)
pause
exit /b %ERR%
