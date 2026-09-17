param([string]$BackupPath)
$ErrorActionPreference='Stop'
$Root=Split-Path -Parent $PSScriptRoot
$Db=Join-Path $Root 'storage\cactus-eventos.sqlite'
if([string]::IsNullOrWhiteSpace($BackupPath)){ $BackupPath=Read-Host 'Caminho completo do arquivo .sqlite de backup' }
$BackupPath=$BackupPath.Trim('"')
if(!(Test-Path -LiteralPath $BackupPath)){ throw 'Arquivo de backup nao encontrado.' }
$bytes=[System.IO.File]::ReadAllBytes($BackupPath)
if($bytes.Length -lt 16){throw 'Arquivo muito pequeno para ser um SQLite valido.'}
$header=[System.Text.Encoding]::ASCII.GetString($bytes,0,15)
if($header -ne 'SQLite format 3'){throw 'O arquivo informado nao possui cabecalho SQLite valido.'}
New-Item -ItemType Directory -Force -Path (Split-Path $Db) | Out-Null
if(Test-Path $Db){
  $stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
  Copy-Item -LiteralPath $Db -Destination (Join-Path (Split-Path $Db) "pre-restore-$stamp.sqlite") -Force
}
Copy-Item -LiteralPath $BackupPath -Destination $Db -Force
Remove-Item "$Db-wal","$Db-shm" -Force -ErrorAction SilentlyContinue
Write-Host 'Backup restaurado com sucesso.' -ForegroundColor Green
