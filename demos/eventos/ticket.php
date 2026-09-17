<?php
require __DIR__.'/lib/bootstrap.php';
$token=trim($_GET['token']??'');
$st=db()->prepare('SELECT o.*,e.title event_title,e.event_date,e.venue,e.address FROM orders o JOIN events e ON e.id=o.event_id WHERE o.public_token=? AND o.status="paid"');
$st->execute([$token]);$order=$st->fetch();
if(!$order){http_response_code(404);exit('Ingressos não disponíveis.');}
$st=db()->prepare('SELECT t.*,oi.ticket_name FROM tickets t JOIN order_items oi ON oi.id=t.order_item_id WHERE t.order_id=? ORDER BY t.id');$st->execute([$order['id']]);$tickets=$st->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Ingressos • <?=e(site_name())?></title><link rel="stylesheet" href="assets/css/site.css"><style><?=brand_css_vars()?></style><style>.ticket-qr{width:220px;max-width:100%;margin:18px auto;background:#fff;border-radius:16px;padding:10px;display:grid;place-items:center}.ticket-qr svg{width:100%;height:auto;display:block}.ticket-code{word-break:break-all}.ticket-help{text-align:center;max-width:800px;margin:28px auto 0;color:#c8bbb5}.ticket-print{display:flex;justify-content:center;margin:24px 0}@media print{.topbar,.ticket-print,.ticket-help{display:none!important}.ticket-page{background:#fff;color:#111}.digital-ticket{break-inside:avoid;box-shadow:none;border:1px solid #ccc}.tickets-head p{color:#333}}</style></head><body class="ticket-page">
<header class="topbar compact"><a class="brand" href="index.php"><img src="<?=e(brand_logo())?>" alt="<?=e(site_name())?>"></a></header>
<main class="tickets-wrap"><div class="tickets-head"><span class="eyebrow">INGRESSOS DIGITAIS</span><h1><?=e($order['event_title'])?></h1><p><?=e(dt_br($order['event_date']))?> • <?=e($order['venue'])?></p></div>
<div class="ticket-print"><button class="btn btn-ghost" onclick="window.print()">Imprimir / salvar PDF</button></div>
<div class="digital-tickets"><?php foreach($tickets as $i=>$t):$payload=ticket_payload($t['code']);?><article class="digital-ticket"><div class="ticket-top"><img src="<?=e(brand_logo())?>" alt="<?=e(site_name())?>"><span>#<?=($i+1)?></span></div><div class="ticket-main"><small><?=e($t['ticket_name'])?></small><h2><?=e($order['buyer_name'])?></h2><div class="ticket-qr tf-qr" data-payload="<?=e($payload)?>" data-size="220"><noscript>Ative o JavaScript para exibir o QR Code.</noscript></div><div class="ticket-code"><?=e($t['code'])?></div><p>Apresente o QR Code na entrada. Cada ingresso é válido para uma única utilização. Se a câmera não conseguir ler, a equipe pode digitar o código acima.</p></div><div class="ticket-status"><?=e(status_label($t['status']))?></div></article><?php endforeach;?></div>
<p class="ticket-help">Guarde este link com segurança. Ele dá acesso aos ingressos deste pedido. Não publique o QR Code em redes sociais.</p></main>
<script src="assets/vendor/cactus-qr.js"></script></body></html>
