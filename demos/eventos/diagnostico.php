<?php
$config = require __DIR__.'/config.php';
$checks=[];
$checks[]=['PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>='),PHP_VERSION];
foreach(['pdo_sqlite','openssl','fileinfo'] as $ext)$checks[]=['Extensão '.$ext,extension_loaded($ext),extension_loaded($ext)?'OK':'Não carregada'];
$checks[]=['mbstring',extension_loaded('mbstring'),'recomendado para e-mails e textos UTF-8'];
$checks[]=['cURL ou allow_url_fopen',function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN),'necessário para Mercado Pago'];
$storage=__DIR__.'/storage';if(!is_dir($storage))@mkdir($storage,0775,true);
$uploads=__DIR__.'/assets/uploads';if(!is_dir($uploads))@mkdir($uploads,0775,true);
$checks[]=['Pasta storage gravável',is_dir($storage)&&is_writable($storage),$storage];
$checks[]=['Pasta uploads gravável',is_dir($uploads)&&is_writable($uploads),$uploads];
$checks[]=['Gerador QR local',is_file(__DIR__.'/assets/vendor/cactus-qr.js'),'assets/vendor/cactus-qr.js'];
$dbOk=false;$dbMsg='';
if(extension_loaded('pdo_sqlite')){try{require_once __DIR__.'/lib/db.php';require_once __DIR__.'/lib/schema.php';$pdo=db();migrate_database($pdo);$required=['admins','events','orders','tickets','email_queue','checkin_logs','legal_acceptances','login_attempts'];$missing=[];foreach($required as $t){if(!table_exists($pdo,$t))$missing[]=$t;}$dbOk=!$missing;$dbMsg=$missing?'Tabelas ausentes: '.implode(', ',$missing):'SQLite conectado e migrações do banco OK';}catch(Throwable $e){$dbMsg=$e->getMessage();}}
$checks[]=['Banco de dados',$dbOk,$dbMsg?:'Não testado'];
$ok=!in_array(false,array_column($checks,1),true);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico • Cactus Eventos</title><link rel="stylesheet" href="assets/css/site.css"></head><body class="install-page"><main class="install-card" style="width:min(820px,100%)"><img src="assets/media/placeholder-logo.svg" alt="Sua logo" class="install-logo"><span class="eyebrow dark">DIAGNÓSTICO • CACTUS EVENTOS</span><h1><?= $ok?'Ambiente pronto':'Ajustes necessários' ?></h1><div class="table-wrap"><table><thead><tr><th>Item</th><th>Status</th><th>Detalhe</th></tr></thead><tbody><?php foreach($checks as $c):?><tr><td><?=htmlspecialchars($c[0])?></td><td><b><?= $c[1]?'✓ OK':'✕ FALHA' ?></b></td><td><?=htmlspecialchars((string)$c[2])?></td></tr><?php endforeach;?></tbody></table></div><p><?php if($ok):?><a class="btn btn-primary" href="install.php">Continuar</a><?php else:?><b>Na HostGator, abra o Gerenciador MultiPHP e selecione PHP 8.2/8.3.</b> Depois confirme pdo_sqlite, OpenSSL, cURL e permissões das pastas.<?php endif;?></p></main></body></html>
