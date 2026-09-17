<?php require __DIR__ . '/lib/bootstrap.php';
$events = db()->query("SELECT id,title,event_date FROM events WHERE status='published' AND vip_enabled=1 AND datetime(event_date) >= datetime('now','-1 day') ORDER BY datetime(event_date) ASC LIMIT 30")->fetchAll();
$result = null; $searched=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $searched=true;
    $eventId=(int)($_POST['event_id']??0); $key=trim($_POST['key']??'');
    if($eventId && $key!==''){
        $st=db()->prepare("SELECT v.*, e.title event_title, e.event_date FROM vip_entries v JOIN events e ON e.id=v.event_id WHERE v.event_id=? AND v.status IN ('active','checked_in') AND (lower(v.email)=lower(?) OR replace(replace(replace(v.document,'.',''),'-',''),'/','')=replace(replace(replace(?,'.',''),'-',''),'/','')) LIMIT 1");
        $st->execute([$eventId,$key,$key]); $result=$st->fetch()?:null;
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lista VIP • <?=e(site_name())?></title><link rel="stylesheet" href="assets/css/site.css"><style><?=brand_css_vars()?></style></head><body class="dark-page">
<header class="topbar compact"><a class="brand" href="index.php"><img src="<?=e(brand_logo())?>" alt="<?=e(site_name())?>"></a><nav><a href="index.php#agenda">Eventos</a></nav></header>
<main class="vip-page"><div class="vip-copy"><span class="eyebrow">LISTA VIP</span><h1>Consulte seu nome</h1><p>Selecione o evento e informe o e-mail ou documento utilizado no cadastro da lista.</p></div><div class="vip-box"><form method="post" class="form-grid one"><?=csrf_field()?>
<label>Evento<select name="event_id" required><option value="">Selecione</option><?php foreach($events as $ev):?><option value="<?=intval($ev['id'])?>" <?=((int)($_POST['event_id']??$_GET['event']??0)==(int)$ev['id'])?'selected':''?>><?=e($ev['title'])?> — <?=date_br($ev['event_date'])?></option><?php endforeach;?></select></label>
<label>E-mail ou CPF/documento<input name="key" value="<?=e($_POST['key']??'')?>" required placeholder="seu@email.com ou 000.000.000-00"></label><button class="btn btn-primary">Consultar lista</button></form>
<?php if($searched):?><?php if($result):?><div class="vip-result ok"><span>✓</span><div><b>Nome confirmado na lista</b><strong><?=e($result['name'])?></strong><small><?=e($result['event_title'])?> • <?=e(dt_br($result['event_date']))?><?=((int)$result['companions']>0)?' • +'.intval($result['companions']).' acompanhante(s)':''?></small></div></div><?php else:?><div class="vip-result no"><span>×</span><div><b>Cadastro não localizado</b><small>Confirme os dados com a equipe responsável pela lista VIP.</small></div></div><?php endif;?><?php endif;?></div></main>
</body></html>
