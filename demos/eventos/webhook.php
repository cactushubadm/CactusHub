<?php require __DIR__.'/lib/bootstrap.php';header('Content-Type:application/json; charset=utf-8');
if(mp_access_token()===''){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'payment_credentials_missing']);exit;}
$rawText=file_get_contents('php://input')?:'';$raw=json_decode($rawText,true)?:[];
$paymentId=(string)($_GET['data_id']??$_GET['data.id']??($raw['data']['id']??''));$eventType=(string)($_GET['type']??($raw['type']??''));
$xSig=(string)($_SERVER['HTTP_X_SIGNATURE']??'');$xReq=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');$secret=mp_webhook_secret();$signatureValid=$secret!==''?verify_mp_signature($xSig,$xReq,$paymentId,$secret):false;
if($secret!==''&&!$signatureValid){webhook_log('mercadopago',$paymentId,$eventType,false,401,$rawText,'Assinatura inválida');http_response_code(401);echo json_encode(['ok'=>false,'error'=>'invalid_signature']);exit;}
if($paymentId===''){webhook_log('mercadopago',null,$eventType,$signatureValid,200,$rawText);http_response_code(200);echo json_encode(['ok'=>true]);exit;}
try{
 $payment=mp_request('GET','/v1/payments/'.rawurlencode($paymentId));$status=(string)($payment['status']??'');$orderId=(int)($payment['external_reference']??0);
 if($orderId){
   $ost=db()->prepare('SELECT * FROM orders WHERE id=?');$ost->execute([$orderId]);$order=$ost->fetch();
   if($order){
     if($status==='approved'){
       if(!validate_mp_payment_for_order($payment,$order)) throw new RuntimeException('Pagamento aprovado não confere com o pedido.');
       mark_order_paid($orderId,$paymentId);
     }elseif(in_array($status,['cancelled','rejected'],true)){try{cancel_order($orderId);}catch(Throwable $ignored){}}
     elseif(in_array($status,['refunded','charged_back'],true)){try{refund_order($orderId);}catch(Throwable $ignored){}}
   }
 }
 webhook_log('mercadopago',$paymentId,$eventType,$signatureValid,200,$rawText);http_response_code(200);echo json_encode(['ok'=>true]);
}catch(Throwable $e){webhook_log('mercadopago',$paymentId,$eventType,$signatureValid,500,$rawText,$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'processing_error']);}
