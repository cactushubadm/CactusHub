<?php
require __DIR__.'/lib/bootstrap.php';
$cli=PHP_SAPI==='cli';
if(!$cli){
    header('Content-Type: application/json; charset=utf-8');
    $token=(string)($_GET['token']??'');
    if($token===''||!hash_equals(cron_token(),$token)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'forbidden']);exit;}
}
$expired=release_expired_orders();$mail=process_email_queue(50);
$result=['ok'=>true,'expired_orders'=>$expired,'emails_sent'=>$mail['sent']??0,'emails_failed'=>$mail['failed']??0,'at'=>date(DATE_ATOM)];
if($cli)echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;else echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
