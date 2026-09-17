<?php
require __DIR__.'/lib/bootstrap.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $now=time();
    $attempts=$_SESSION['order_lookup_attempts']??[];
    $attempts=array_values(array_filter($attempts,fn($t)=>$t>$now-300));
    if(count($attempts)>=12){
        $error='Muitas consultas em pouco tempo. Aguarde alguns minutos e tente novamente.';
    }else{
        $attempts[]=$now;$_SESSION['order_lookup_attempts']=$attempts;
        $number=strtoupper(trim((string)($_POST['order_number']??'')));
        $key=trim((string)($_POST['key']??''));
        if($number===''||$key===''){$error='Informe o número do pedido e o e-mail ou documento da compra.';}
        else{
            $doc=normalize_document($key);
            $st=db()->prepare("SELECT public_token FROM orders WHERE upper(order_number)=upper(?) AND (lower(buyer_email)=lower(?) OR replace(replace(replace(replace(buyer_document,'.',''),'-',''),'/',''),' ','')=?) LIMIT 1");
            $st->execute([$number,$key,$doc]);
            $token=$st->fetchColumn();
            if($token){
                audit_log('order.customer_lookup',null,null,['order_number'=>$number]);
                redirect('payment_return.php?token='.urlencode((string)$token));
            }
            $error='Pedido não localizado. Confira o número e os dados informados.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meus ingressos • <?=e(site_name())?></title><link rel="stylesheet" href="assets/css/site.css"><style><?=brand_css_vars()?></style></head><body class="dark-page">
<header class="topbar compact"><a class="brand" href="index.php"><img src="<?=e(brand_logo())?>" alt="<?=e(site_name())?>"></a><nav><a href="index.php#agenda">Eventos</a><a href="vip-check.php">Lista VIP</a></nav></header>
<main class="vip-page"><div class="vip-copy"><span class="eyebrow">MEUS INGRESSOS</span><h1>Recupere seu pedido</h1><p>Use o número recebido na compra e o mesmo e-mail ou documento informado no checkout.</p></div><div class="vip-box"><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post" class="form-grid one"><?=csrf_field()?><label>Número do pedido<input name="order_number" required autocomplete="off" placeholder="EVT-260910-ABC123" value="<?=e($_POST['order_number']??'')?>"></label><label>E-mail ou CPF/documento<input name="key" required autocomplete="email" placeholder="seu@email.com ou 000.000.000-00" value="<?=e($_POST['key']??'')?>"></label><button class="btn btn-primary">Localizar pedido</button></form><p class="checkout-note">Pedidos pagos exibem os ingressos digitais. Pedidos pendentes mostram a situação e, quando disponível, o botão para continuar o pagamento.</p></div></main>
</body></html>
