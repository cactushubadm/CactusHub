<?php require __DIR__ . '/lib/bootstrap.php';
$eventId=(int)($_GET['event']??$_POST['event_id']??0);
$typeId=(int)($_GET['type']??$_POST['type_id']??0);
$event=event_by_id($eventId);
if(!$event || !event_sales_open($event)){http_response_code(404);exit('Evento ou vendas indisponíveis.');}
$maxQty=max(1,min(20,(int)($event['max_tickets_per_order']??($config['orders']['max_tickets_per_order']??8))));
$qty=max(1,min($maxQty,(int)($_GET['qty']??$_POST['qty']??1)));
$st=db()->prepare('SELECT * FROM ticket_types WHERE id=? AND event_id=? AND active=1');$st->execute([$typeId,$eventId]);$type=$st->fetch();
if(!$type){http_response_code(404);exit('Tipo de ingresso indisponível.');}
$available=ticket_available($type);if($available<$qty)exit('Quantidade indisponível para este ingresso.');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); release_expired_orders();
    $name=trim($_POST['buyer_name']??'');$email=trim($_POST['buyer_email']??'');$phone=trim($_POST['buyer_phone']??'');$doc=trim($_POST['buyer_document']??'');$accepted=isset($_POST['accept_legal']);$marketing=isset($_POST['marketing_opt_in']);
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){$error='Informe nome e e-mail válidos.';}elseif(!$accepted){$error='Para concluir a compra, aceite os Termos de Uso, a Política de Privacidade e a Política de Cancelamentos.';}
    else{
        $pdo=db();$pdo->beginTransaction();
        try{
            // Reserva atômica: evita venda acima do estoque mesmo com dois checkouts simultâneos.
            $reserve=$pdo->prepare('UPDATE ticket_types SET sold=sold+? WHERE id=? AND event_id=? AND active=1 AND (stock-sold)>=?');
            $reserve->execute([$qty,$typeId,$eventId,$qty]);
            if($reserve->rowCount()!==1)throw new RuntimeException('Os últimos ingressos acabaram de ser reservados por outro cliente. Atualize a página.');
            $fresh=$pdo->prepare('SELECT name,price FROM ticket_types WHERE id=?');$fresh->execute([$typeId]);$fresh=$fresh->fetch();
            $total=(float)$fresh['price']*$qty;$number=order_number();$token=order_public_token();$mode=payment_mode();
            $minutes=max(5,min(60,(int)($config['orders']['reservation_minutes']??20)));$expires=db_plus_utc('+'.$minutes.' minutes');
            $pdo->prepare('INSERT INTO orders(order_number,public_token,event_id,buyer_name,buyer_email,buyer_phone,buyer_document,total,status,payment_mode,external_reference,expires_at,customer_ip) VALUES(?,?,?,?,?,?,?,?,"pending",?,?,?,?)')
                ->execute([$number,$token,$eventId,$name,$email,$phone,$doc,$total,$mode,'',$expires,request_ip()]);
            $orderId=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE orders SET external_reference=? WHERE id=?')->execute([(string)$orderId,$orderId]);
            $pdo->prepare('INSERT INTO order_items(order_id,ticket_type_id,ticket_name,unit_price,quantity,subtotal) VALUES(?,?,?,?,?,?)')
                ->execute([$orderId,$typeId,$fresh['name'],$fresh['price'],$qty,$total]);
            record_legal_acceptance($orderId,$marketing);
            $pdo->commit();
            audit_log('order.created','order',$orderId,['mode'=>$mode,'qty'=>$qty]);

            if($mode==='mercadopago'){
                try{
                    $base=payment_base_url();
                    if(!str_starts_with($base,'https://'))throw new RuntimeException('A URL pública HTTPS não está configurada no painel.');
                    if(mp_webhook_secret()==='')throw new RuntimeException('O Webhook Secret do Mercado Pago não está configurado no painel.');
                    $pref=mp_request('POST','/checkout/preferences',[
                        'items'=>[['id'=>(string)$typeId,'title'=>$event['title'].' - '.$fresh['name'],'quantity'=>$qty,'currency_id'=>'BRL','unit_price'=>(float)$fresh['price']]],
                        'payer'=>['name'=>$name,'email'=>$email],
                        'external_reference'=>(string)$orderId,
                        'back_urls'=>[
                            'success'=>base_url('payment_return.php?token='.$token.'&result=success'),
                            'pending'=>base_url('payment_return.php?token='.$token.'&result=pending'),
                            'failure'=>base_url('payment_return.php?token='.$token.'&result=failure')
                        ],
                        'auto_return'=>'approved',
                        'notification_url'=>base_url('webhook.php'),
                        'expires'=>true,
                        'expiration_date_from'=>(new DateTime('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.vP'),
                        'expiration_date_to'=>(new DateTime($expires,new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.vP'),
                        'statement_descriptor'=>(substr(preg_replace('/[^A-Z0-9 ]/','',mb_strtoupper(site_name())),0,22) ?: 'EVENTOS')
                    ]);
                    $url=(string)($pref['init_point']??$pref['sandbox_init_point']??'');
                    db()->prepare('UPDATE orders SET checkout_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$url,$orderId]);
                    if($url!=='')redirect($url);
                    throw new RuntimeException('O Mercado Pago não retornou URL de checkout.');
                }catch(Throwable $e){
                    flash('error','Pedido reservado, mas o pagamento online não iniciou: '.$e->getMessage());
                    redirect('payment_return.php?token='.$token.'&result=pending');
                }
            }
            redirect('payment_return.php?token='.$token.'&result=manual');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='Não foi possível concluir o pedido: '.$e->getMessage();}
    }
}
$total=(float)$type['price']*$qty;
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Checkout • <?=e(site_name())?></title><link rel="stylesheet" href="assets/css/site.css"><style><?=brand_css_vars()?></style></head><body class="dark-page">
<header class="topbar compact"><a class="brand" href="index.php"><img src="<?=e(brand_logo())?>" alt="<?=e(site_name())?>"></a></header>
<main class="checkout-wrap"><section class="checkout-summary"><span class="eyebrow">SEU INGRESSO</span><h1><?=e($event['title'])?></h1><p><?=e(dt_br($event['event_date']))?></p><div class="summary-ticket"><div><b><?=e($type['name'])?></b><small><?=intval($qty)?> ingresso(s) × <?=money((float)$type['price'])?></small></div><strong><?=money($total)?></strong></div><p class="muted">A reserva fica protegida por <?=intval($config['orders']['reservation_minutes']??20)?> minutos enquanto o pagamento está pendente.</p><a class="text-link light-link" href="event.php?slug=<?=urlencode($event['slug'])?>">← Alterar seleção</a></section>
<section class="checkout-form"><span class="eyebrow dark">DADOS DO COMPRADOR</span><h2>Finalize sua compra</h2><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post" class="form-grid one"><?=csrf_field()?><input type="hidden" name="event_id" value="<?=$eventId?>"><input type="hidden" name="type_id" value="<?=$typeId?>"><input type="hidden" name="qty" value="<?=$qty?>"><label>Nome completo<input name="buyer_name" required autocomplete="name" value="<?=e($_POST['buyer_name']??'')?>"></label><label>E-mail<input type="email" name="buyer_email" required autocomplete="email" value="<?=e($_POST['buyer_email']??'')?>"></label><label>Telefone / WhatsApp<input name="buyer_phone" autocomplete="tel" value="<?=e($_POST['buyer_phone']??'')?>"></label><label>CPF / documento<input name="buyer_document" value="<?=e($_POST['buyer_document']??'')?>"></label><label class="legal-check"><input type="checkbox" name="accept_legal" required <?=isset($_POST['accept_legal'])?'checked':''?>> <span>Li e aceito os <a href="termos.php" target="_blank">Termos de Uso e Compra</a>, a <a href="privacidade.php" target="_blank">Política de Privacidade</a> e a <a href="cancelamento.php" target="_blank">Política de Cancelamentos e Reembolsos</a>.</span></label><label class="legal-check"><input type="checkbox" name="marketing_opt_in" <?=isset($_POST['marketing_opt_in'])?'checked':''?>> <span>Quero receber novidades e informações de próximos eventos por e-mail/WhatsApp. Opcional.</span></label><div class="checkout-total"><span>Total</span><strong><?=money($total)?></strong></div><button class="btn btn-primary wide" type="submit"><?=payment_mode()==='mercadopago'?'Ir para pagamento seguro':'Criar pedido'?></button><small class="checkout-note"><?=payment_mode()==='mercadopago'?'Você será redirecionado ao Mercado Pago.':'Modo manual: o pedido será confirmado pelo caixa/administração.'?></small></form></section></main></body></html>
