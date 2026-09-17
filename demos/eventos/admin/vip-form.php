<?php require __DIR__.'/../lib/bootstrap.php';require_admin();$id=(int)($_GET['id']??$_POST['id']??0);$eventPreset=(int)($_GET['event']??0);$row=null;$error='';
if($id){$st=db()->prepare('SELECT * FROM vip_entries WHERE id=?');$st->execute([$id]);$row=$st->fetch();if(!$row){http_response_code(404);exit('Cadastro não encontrado.');}}
$events=db()->query("SELECT id,title,event_date,vip_enabled,vip_capacity FROM events ORDER BY datetime(event_date) DESC")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$eventId=(int)($_POST['event_id']??0);$name=trim($_POST['name']??'');$companions=max(0,min(20,(int)($_POST['companions']??0)));$document=trim($_POST['document']??'');$email=trim($_POST['email']??'');
 if(!$eventId||$name==='')$error='Selecione o evento e informe o nome.';
 elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Informe um e-mail válido ou deixe o campo vazio.';
 else{
  $event=event_by_id($eventId);if(!$event||!(int)$event['vip_enabled'])$error='A Lista VIP está desativada para este evento.';
  else{
   $used=vip_people_count($eventId);$current=0;if($row && (int)$row['event_id']===$eventId && ($row['status']??'')!=='removed')$current=1+(int)$row['companions'];$cap=(int)$event['vip_capacity'];
   if($cap>0 && ($used-$current+1+$companions)>$cap)$error='A capacidade da Lista VIP seria ultrapassada. Restam '.max(0,$cap-($used-$current)).' vaga(s).';
   else{
    $docNorm=normalize_document($document);$dupSql="SELECT id,name FROM vip_entries WHERE event_id=? AND status!='removed' AND id<>? AND (";$dupArgs=[$eventId,$id];$parts=[];
    if($docNorm!==''){$parts[]="replace(replace(replace(document,'.',''),'-',''),'/','')=?";$dupArgs[]=$docNorm;}
    if($email!==''){$parts[]='lower(email)=lower(?)';$dupArgs[]=$email;}
    $dup=null;if($parts){$dupSql.=implode(' OR ',$parts).') LIMIT 1';$st=db()->prepare($dupSql);$st->execute($dupArgs);$dup=$st->fetch();}
    if($dup)$error='Já existe um cadastro para este documento/e-mail no evento: '.$dup['name'].'.';
    else{try{$vals=[$eventId,$name,$document,$email,trim($_POST['phone']??''),$companions,trim($_POST['notes']??'')];if($id){$vals[]=$id;db()->prepare('UPDATE vip_entries SET event_id=?,name=?,document=?,email=?,phone=?,companions=?,notes=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($vals);}else{db()->prepare('INSERT INTO vip_entries(event_id,name,document,email,phone,companions,notes,status) VALUES(?,?,?,?,?,?,?,"active")')->execute($vals);$id=(int)db()->lastInsertId();}audit_log('vip.saved','vip',$id,['event_id'=>$eventId]);flash('success','Nome salvo na Lista VIP.');redirect('vip.php?event='.$eventId);}catch(Throwable $e){$error='Erro ao salvar: '.$e->getMessage();}}
   }
  }
 }
}
$pageTitle=$id?'Editar VIP':'Adicionar VIP';require '_header.php';?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><div class="admin-card narrow-card"><div class="card-head"><div><h2><?=e($pageTitle)?></h2><p>O cadastro fica vinculado a um único evento e respeita a capacidade definida.</p></div></div><form method="post" class="form-grid one"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><label>Evento<select name="event_id" required><option value="">Selecione</option><?php foreach($events as $ev):$selected=(int)($row['event_id']??$eventPreset)===(int)$ev['id'];?><option value="<?=intval($ev['id'])?>" <?=$selected?'selected':''?> <?=!$ev['vip_enabled']?'disabled':''?>><?=e($ev['title'])?> — <?=date_br($ev['event_date'])?><?=!$ev['vip_enabled']?' (VIP desativada)':''?></option><?php endforeach;?></select></label><label>Nome completo<input name="name" required value="<?=e($row['name']??'')?>"></label><div class="form-grid two"><label>CPF / documento<input name="document" value="<?=e($row['document']??'')?>"></label><label>Acompanhantes<input type="number" min="0" max="20" name="companions" value="<?=intval($row['companions']??0)?>"></label></div><label>E-mail<input type="email" name="email" value="<?=e($row['email']??'')?>"></label><label>Telefone / WhatsApp<input name="phone" value="<?=e($row['phone']??'')?>"></label><label>Observações<textarea name="notes" rows="4"><?=e($row['notes']??'')?></textarea></label><div><button class="btn btn-primary">Salvar</button> <a class="btn btn-ghost dark-outline" href="vip.php">Cancelar</a></div></form></div><?php require '_footer.php';?>
