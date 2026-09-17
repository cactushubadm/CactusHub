<?php
require __DIR__.'/../lib/bootstrap.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Método não permitido.']);exit;}
$token=(string)($_POST['csrf']??'');
if($token===''||!hash_equals((string)($_SESSION['csrf']??''),$token)){http_response_code(419);echo json_encode(['ok'=>false,'message'=>'Sessão expirada. Atualize a página.']);exit;}
$raw=(string)($_POST['code']??'');
$result=checkin_ticket($raw,(int)(admin_user()['id']??0));
http_response_code($result['ok']?200:422);
$ticket=$result['ticket']??null;
if($ticket){$result['ticket']=[
    'id'=>(int)$ticket['id'],'code'=>$ticket['code'],'buyer_name'=>$ticket['buyer_name']??'','ticket_name'=>$ticket['ticket_name']??'',
    'event_title'=>$ticket['event_title']??'','event_date'=>dt_br($ticket['event_date']??''),'checked_in_at'=>dt_db_br($ticket['checked_in_at']??db_now_utc()),
];}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
