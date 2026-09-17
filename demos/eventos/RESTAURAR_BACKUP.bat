@echo off
setlocal
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\restore_backup.ps1" -BackupPath "%~1"
if errorlevel 1 pause
endlocal
