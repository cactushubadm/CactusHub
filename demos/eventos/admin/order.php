<?php
require __DIR__.'/../lib/bootstrap.php';require_admin();
$id=(int)($_GET['id']??$_POST['id']??0);
$load=function()use($id){$st=db()->prepare('SELECT o.*,e.title event_title,e.event_date FROM orders o JOIN events e ON e.id=o.event_id WHERE o.id=?');$st->execute([$id]);return $st->fetch()?:null;};
$order=$load();if(!$order){http_response_code(404);exit('Pedido não encontrado.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action']??'';
    try{
        if($action==='paid'){
            $issued=mark_order_paid($id);flash($issued?'success':'error',$issued?'Pagamento confirmado e ingressos emitidos.':'Pagamento registrado, mas o pedido entrou em revisão por indisponibilidade de estoque.');
        }elseif($action==='cancel'){
            cancel_order($id);flash('success','Pedido cancelado e estoque liberado.');queue_order_status_email($id,'cancelled');
        }elseif($action==='refund'){
            if($order['payment_mode']==='mercadopago'){
                if(empty($order['external_payment_id']))throw new RuntimeException('O pedido não possui ID de pagamento do Mercado Pago para reembolso automático.');
                $refund=mp_refund_payment((string)$order['external_payment_id'],'cactus-refund-'.$id);
                $refundStatus=(string)($refund['status']??'');
                if($refundStatus!==''&&!in_array($refundStatus,['approved','refunded'],true))throw new RuntimeException('O Mercado Pago não confirmou o reembolso. Status: '.$refundStatus);
            }
            refund_order($id);flash('success',$order['payment_mode']==='mercadopago'?'Reembolso solicitado ao Mercado Pago e pedido encerrado localmente.':'Pedido marcado como reembolsado, ingressos invalidados e estoque liberado.');
        }elseif($action==='resend'){
            if(!queue_order_tickets_email($id,true))throw new RuntimeException('Não foi possível colocar o e-mail na fila. Confira se o pedido está pago e se o e-mail é válido.');
            flash('success','Reenvio dos ingressos colocado na fila de e-mail.');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());}
    redirect('order.php?id='.$id);
}
$order=$load();
$items=db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');$items->execute([$id]);$items=$items->fetchAll();
$tickets=db()->prepare('SELECT t.*,oi.ticket_name FROM tickets t JOIN order_items oi ON oi.id=t.order_item_id WHERE t.order_id=? ORDER BY t.id');$tickets->execute([$id]);$tickets=$tickets->fetchAll();
$accept=db()->prepare('SELECT * FROM legal_acceptances WHERE order_id=?');$accept->execute([$id]);$accept=$accept->fetch();
$pageTitle='Pedido '.$order['order_number'];require '_header.php';?>
<?php if((int)($order['requires_review']??0)===1):?><div class="alert error"><b>PEDIDO EM REVISÃO.</b> <?=e($order['review_reason']?:'Requer conferência manual.')?></div><?php endif;?>
<div class="order-detail-grid"><div class="admin-card"><div class="card-head"><div><h2>Resumo do pedido</h2><p><?=e($order['event_title'])?> • <?=e(dt_br($order['event_date']))?></p></div><span class="pill <?=e($order['status'])?>"><?=e(status_label($order['status']))?></span></div>
<div class="order-info-grid"><div><small>Comprador</small><b><?=e($order['buyer_name'])?></b></div><div><small>E-mail</small><b><?=e($order['buyer_email'])?></b></div><div><small>Telefone</small><b><?=e($order['buyer_phone'])?></b></div><div><small>Documento</small><b><?=e($order['buyer_document'])?></b></div><div><small>Criado em</small><b><?=e(dt_db_br($order['created_at']))?></b></div><div><small>Pagamento</small><b><?=e($order['payment_mode'])?></b></div><div><small>Reserva até</small><b><?=e($order['expires_at']?dt_db_br($order['expires_at']):'—')?></b></div><div><small>ID externo</small><b><?=e($order['external_payment_id']?:'—')?></b></div></div>
<hr><h3>Itens</h3><?php foreach($items as $it):?><div class="order-item"><span><?=intval($it['quantity'])?> × <?=e($it['ticket_name'])?></span><b><?=money((float)$it['subtotal'])?></b></div><?php endforeach;?><div class="order-total"><span>Total</span><strong><?=money((float)$order['total'])?></strong></div>
<?php if($accept):?><hr><h3>Aceites legais</h3><p class="muted">Termos <?=e($accept['terms_version'])?> • Privacidade <?=e($accept['privacy_version'])?> • Cancelamento <?=e($accept['cancellation_version'])?> • Marketing: <?=$accept['marketing_opt_in']?'sim':'não'?> • <?=e(dt_db_br($accept['created_at']))?></p><?php endif;?></div>
<div class="admin-card"><h2>Ações</h2>
<?php if($order['status']==='pending'):?><form method="post" class="action-stack"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-primary" name="action" value="paid" onclick="return confirm('Confirmar pagamento e emitir os ingressos?')">Marcar como pago</button><button class="btn btn-ghost dark-outline" name="action" value="cancel" onclick="return confirm('Cancelar pedido e liberar o estoque?')">Cancelar pedido</button></form>
<?php elseif($order['status']==='paid'):?><div class="alert success">Pagamento confirmado em <?=e(dt_db_br($order['paid_at']))?>.</div><form method="post" class="action-stack"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-secondary" name="action" value="resend">Reenviar ingressos por e-mail</button><button class="btn btn-ghost dark-outline" name="action" value="refund" onclick="return confirm('Confirmar reembolso? Esta ação invalida os ingressos e libera o estoque.')">Reembolsar pedido</button></form>
<?php elseif($order['status']==='review'):?><div class="alert error">Pagamento confirmado, mas não houve emissão porque o estoque reservado já não estava disponível. Recomendado: conferir com a operação e reembolsar se não houver como acomodar o cliente.</div><form method="post" class="action-stack"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-ghost dark-outline" name="action" value="refund" onclick="return confirm('Reembolsar este pagamento em revisão?')">Reembolsar pagamento</button></form>
<?php else:?><p>Não há ações manuais para este status.</p><?php endif;?>
<p class="muted">A aprovação do Mercado Pago chega pelo webhook. O e-mail de ingresso é colocado em fila e enviado pelo cron/SMTP.</p></div></div>
<?php if($tickets):?><div class="admin-card"><div class="card-head"><div><h2>Ingressos emitidos</h2><p>QR Code e código individual para validação na portaria.</p></div><a class="btn btn-small btn-secondary" target="_blank" href="../ticket.php?token=<?=urlencode($order['public_token'])?>">Abrir ingressos ↗</a></div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Tipo</th><th>Titular</th><th>Status</th><th>Check-in</th></tr></thead><tbody><?php foreach($tickets as $t):?><tr><td><code><?=e($t['code'])?></code></td><td><?=e($t['ticket_name'])?></td><td><?=e($t['holder_name'])?></td><td><span class="pill"><?=e(status_label($t['status']))?></span></td><td><?=e($t['checked_in_at']?dt_db_br($t['checked_in_at']):'—')?></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
<?php require '_footer.php';?>
