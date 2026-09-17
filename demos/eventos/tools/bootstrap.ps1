$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$Root = Split-Path -Parent $PSScriptRoot
$Runtime = Join-Path $Root 'runtime\php'
$Php = Join-Path $Runtime 'php.exe'
$Zip = Join-Path $Root 'runtime\php.zip'
$PhpUrls = @(
  'https://downloads.php.net/~windows/releases/archives/php-8.4.25-nts-Win32-vs17-x64.zip',
  'https://windows.php.net/downloads/releases/archives/php-8.4.25-nts-Win32-vs17-x64.zip'
)
$PhpSha = '43a8f67ed2e5223fafb21293c85976361808855405278cef2cf3037c3ae2529c'
$VcUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'

function Write-Step($Text){ Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Download-File($Urls,$Target){
  $last = $null
  foreach($u in $Urls){
    try { Write-Host "Baixando: $u"; Invoke-WebRequest -Uri $u -OutFile $Target -UseBasicParsing; return }
    catch { $last = $_; Remove-Item $Target -Force -ErrorAction SilentlyContinue }
  }
  throw "Falha ao baixar o PHP. $last"
}
function Configure-PHP {
    $storage = Join-Path $Root 'storage'
    New-Item -ItemType Directory -Force -Path $storage | Out-Null
    $errorLog = (Join-Path $storage 'php-error.log').Replace('\','/')
    $ini = @"
[PHP]
extension_dir="ext"
extension=pdo_sqlite
extension=sqlite3
extension=curl
extension=mbstring
extension=openssl
extension=fileinfo
date.timezone=America/Sao_Paulo
memory_limit=256M
upload_max_filesize=12M
post_max_size=16M
max_execution_time=60
display_errors=On
log_errors=On
error_log="$errorLog"
session.cookie_httponly=1
session.cookie_samesite=Lax
"@
    Set-Content -LiteralPath (Join-Path $Runtime 'php.ini') -Value $ini -Encoding ASCII
}
function Test-PortAvailable([int]$Port){
  $listener = $null
  try {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback,$Port)
    $listener.Start(); return $true
  } catch { return $false }
  finally { if($listener){ try{$listener.Stop()}catch{} } }
}

if(!(Test-Path $Php)){
    Write-Step 'Preparando PHP 8.4 portátil'
    New-Item -ItemType Directory -Force -Path (Join-Path $Root 'runtime') | Out-Null
    Download-File $PhpUrls $Zip
    $hash=(Get-FileHash -Algorithm SHA256 -LiteralPath $Zip).Hash.ToLower()
    if($hash -ne $PhpSha){ Remove-Item $Zip -Force -ErrorAction SilentlyContinue; throw "Hash do PHP inválido. Esperado $PhpSha, recebido $hash" }
    New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
    Expand-Archive -LiteralPath $Zip -DestinationPath $Runtime -Force
    Remove-Item $Zip -Force
}
Configure-PHP

Write-Step 'Validando PHP e extensões'
$test = & $Php -r "echo extension_loaded('pdo_sqlite') && extension_loaded('openssl') && extension_loaded('fileinfo') ? 'OK' : 'FAIL';" 2>&1
if($LASTEXITCODE -ne 0 -or "$test" -notmatch 'OK'){
    Write-Step 'Instalando Microsoft Visual C++ Runtime necessário ao PHP'
    $vc=Join-Path $env:TEMP 'vc_redist_cactus_eventos.exe'
    Invoke-WebRequest -Uri $VcUrl -OutFile $vc -UseBasicParsing
    Start-Process -FilePath $vc -ArgumentList '/install','/quiet','/norestart' -Wait
    Remove-Item $vc -Force -ErrorAction SilentlyContinue
    $test = & $Php -r "echo extension_loaded('pdo_sqlite') && extension_loaded('openssl') && extension_loaded('fileinfo') ? 'OK' : 'FAIL';" 2>&1
    if($LASTEXITCODE -ne 0 -or "$test" -notmatch 'OK'){ throw "PHP abriu, mas as extensões necessárias não foram carregadas: $test" }
}

New-Item -ItemType Directory -Force -Path (Join-Path $Root 'storage') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Root 'assets\uploads') | Out-Null
$port = $null
foreach($p in 8088..8098){ if(Test-PortAvailable $p){ $port=$p; break } }
if($null -eq $port){ throw 'Nenhuma porta livre entre 8088 e 8098.' }
Write-Step "Iniciando Cactus Eventos em http://127.0.0.1:$port"
$router = Join-Path $Root 'router.php'
$args = "-S 127.0.0.1:$port -t `"$Root`" `"$router`""
$proc = Start-Process -FilePath $Php -WorkingDirectory $Root -ArgumentList $args -WindowStyle Minimized -PassThru
Set-Content -LiteralPath (Join-Path $Root 'storage\server.pid') -Value $proc.Id -Encoding ASCII
Set-Content -LiteralPath (Join-Path $Root 'storage\server.port') -Value $port -Encoding ASCII
Start-Sleep -Seconds 2
Start-Process "http://127.0.0.1:$port/install.php"
Write-Host "`nCactus Eventos está rodando em http://127.0.0.1:$port" -ForegroundColor Green
Write-Host 'Use PARAR_CACTUS_EVENTOS.bat para encerrar este servidor local.'
