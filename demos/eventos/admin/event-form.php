<?php require __DIR__ . '/../lib/bootstrap.php';require_admin();
$id=(int)($_GET['id']??$_POST['id']??0);$event=$id?event_by_id($id):null;$types=$event?ticket_types($id):[];$error='';
if($id && !$event){http_response_code(404);exit('Evento não encontrado.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $title=trim($_POST['title']??'');$date=trim($_POST['event_date']??'');$status=$_POST['status']??'draft';
    if($title===''||$date===''){$error='Informe título e data do evento.';}
    elseif(!strtotime(str_replace('T',' ',$date))){$error='Data do evento inválida.';}
    elseif(!empty($_POST['sales_start'])&&!empty($_POST['sales_end'])&&strtotime(str_replace('T',' ',$_POST['sales_end']))<strtotime(str_replace('T',' ',$_POST['sales_start']))){$error='O fim das vendas não pode ser anterior ao início.';}
    else{
        $pdo=db();$pdo->beginTransaction();
        try{
            $slug=unique_event_slug($title,$id?:null);
            $oldCover=$event['cover_image']??null;$cover=$oldCover;
            if(isset($_POST['remove_cover'])) $cover=null;
            if(!empty($_FILES['cover_image']['tmp_name']) && is_uploaded_file($_FILES['cover_image']['tmp_name'])){
                if((int)$_FILES['cover_image']['size'] > 5*1024*1024) throw new RuntimeException('A imagem do evento deve ter no máximo 5 MB.');
                $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($_FILES['cover_image']['tmp_name']);
                $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if(!isset($allowed[$mime])) throw new RuntimeException('Arquivo inválido. Use uma imagem JPG, PNG ou WEBP real.');
                $uploadDir=__DIR__.'/../assets/uploads'; if(!is_dir($uploadDir) && !mkdir($uploadDir,0775,true) && !is_dir($uploadDir)) throw new RuntimeException('Não foi possível criar a pasta de uploads.');
                $filename='event-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.'.$allowed[$mime];
                if(!move_uploaded_file($_FILES['cover_image']['tmp_name'],$uploadDir.'/'.$filename)) throw new RuntimeException('Não foi possível salvar a imagem do evento.');
                $cover='assets/uploads/'.$filename;
            }
            $values=[
                $title,$slug,trim($_POST['description']??''),str_replace('T',' ',$date),trim($_POST['doors_time']??''),trim($_POST['venue']??''),trim($_POST['address']??''),$cover,
                in_array($status,['draft','published','closed'],true)?$status:'draft',isset($_POST['vip_enabled'])?1:0,max(0,(int)($_POST['vip_capacity']??0)),
                ($_POST['sales_start']??'')?str_replace('T',' ',$_POST['sales_start']):null,($_POST['sales_end']??'')?str_replace('T',' ',$_POST['sales_end']):null,
                max(0,(int)($_POST['minimum_age']??18)),max(1,min(20,(int)($_POST['max_tickets_per_order']??8)))
            ];
            $requestedVipCapacity=max(0,(int)($_POST['vip_capacity']??0));
            if($id && $requestedVipCapacity>0 && vip_people_count($id)>$requestedVipCapacity) throw new RuntimeException('A capacidade VIP não pode ficar abaixo do total já cadastrado na lista.');
            if($id){
                $sql='UPDATE events SET title=?,slug=?,description=?,event_date=?,doors_time=?,venue=?,address=?,cover_image=?,status=?,vip_enabled=?,vip_capacity=?,sales_start=?,sales_end=?,minimum_age=?,max_tickets_per_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?';
                $values[]=$id;$pdo->prepare($sql)->execute($values);
            }else{
                $sql='INSERT INTO events(title,slug,description,event_date,doors_time,venue,address,cover_image,status,vip_enabled,vip_capacity,sales_start,sales_end,minimum_age,max_tickets_per_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $pdo->prepare($sql)->execute($values);$id=(int)$pdo->lastInsertId();
            }

            $typeIds=$_POST['ticket_id']??[];$names=$_POST['ticket_name']??[];$descs=$_POST['ticket_desc']??[];$prices=$_POST['ticket_price']??[];$stocks=$_POST['ticket_stock']??[];
            $keep=[];
            foreach($names as $i=>$name){
                $name=trim($name);if($name==='')continue;
                $typeId=(int)($typeIds[$i]??0);$desc=trim($descs[$i]??'');$price=max(0.0,(float)str_replace(',','.',(string)($prices[$i]??0)));$stock=max(0,(int)($stocks[$i]??0));
                if($typeId){
                    $exists=$pdo->prepare('SELECT id,sold FROM ticket_types WHERE id=? AND event_id=?');$exists->execute([$typeId,$id]);
                    if($existingType=$exists->fetch()){if($stock<(int)$existingType['sold']) throw new RuntimeException('O estoque de '.$name.' não pode ser menor que a quantidade já reservada/vendida.');$pdo->prepare('UPDATE ticket_types SET name=?,description=?,price=?,stock=?,sort_order=?,active=1 WHERE id=?')->execute([$name,$desc,$price,$stock,$i,$typeId]);$keep[]=$typeId;continue;}
                }
                $pdo->prepare('INSERT INTO ticket_types(event_id,name,description,price,stock,sort_order) VALUES(?,?,?,?,?,?)')->execute([$id,$name,$desc,$price,$stock,$i]);$keep[]=(int)$pdo->lastInsertId();
            }
            // Tipos removidos só são apagados se ainda não tiverem venda; caso contrário são apenas desativados.
            $existing=$pdo->prepare('SELECT id,sold FROM ticket_types WHERE event_id=?');$existing->execute([$id]);
            foreach($existing->fetchAll() as $row){if(!in_array((int)$row['id'],$keep,true)){if((int)$row['sold']===0)$pdo->prepare('DELETE FROM ticket_types WHERE id=?')->execute([$row['id']]);else $pdo->prepare('UPDATE ticket_types SET active=0 WHERE id=?')->execute([$row['id']]);}}
            $pdo->commit();if($oldCover && $oldCover!==$cover && str_starts_with($oldCover,'assets/uploads/')) @unlink(__DIR__.'/../'.$oldCover);audit_log('event.saved','event',$id,['title'=>$title]);flash('success','Evento salvo com sucesso.');redirect('event-form.php?id='.$id);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='Erro ao salvar: '.$e->getMessage();}
    }
    $event=$id?event_by_id($id):null;$types=$id?ticket_types($id):[];
}
$pageTitle=$id?'Editar evento':'Novo evento';require '_header.php';
function dt_input(?string $v):string{return $v?str_replace(' ','T',substr($v,0,16)):'';}
?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="admin-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>">
<div class="admin-card"><div class="card-head"><div><h2>Dados do evento</h2><p>Informações que aparecerão no site.</p></div><button class="btn btn-primary btn-small" type="submit">Salvar evento</button></div>
<div class="form-grid two"><label>Título<input name="title" required value="<?=e($event['title']??'')?>"></label><label>Status<select name="status"><option value="draft" <?=($event['status']??'draft')==='draft'?'selected':''?>>Rascunho</option><option value="published" <?=($event['status']??'')==='published'?'selected':''?>>Publicado</option><option value="closed" <?=($event['status']??'')==='closed'?'selected':''?>>Encerrado</option></select></label><label>Data e hora do evento<input type="datetime-local" name="event_date" required value="<?=e(dt_input($event['event_date']??''))?>"></label><label>Abertura da casa<input type="time" name="doors_time" value="<?=e($event['doors_time']??'')?>"></label><label>Local<input name="venue" value="<?=e($event['venue']??site_name())?>"></label><label>Endereço<input name="address" value="<?=e($event['address']??app_setting('address',$config['address']??''))?>"></label><label>Início das vendas<input type="datetime-local" name="sales_start" value="<?=e(dt_input($event['sales_start']??''))?>"></label><label>Fim das vendas<input type="datetime-local" name="sales_end" value="<?=e(dt_input($event['sales_end']??''))?>"></label><label>Classificação etária<input type="number" min="0" max="99" name="minimum_age" value="<?=intval($event['minimum_age']??18)?>"></label><label>Máx. ingressos por pedido<input type="number" min="1" max="20" name="max_tickets_per_order" value="<?=intval($event['max_tickets_per_order']??8)?>"></label><label class="full">Descrição<textarea name="description" rows="5"><?=e($event['description']??'')?></textarea></label><label class="full">Imagem / banner do evento<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><?php if(!empty($event['cover_image'])):?><span class="cover-preview"><img src="../<?=e($event['cover_image'])?>" alt="Capa atual"><label class="check-line"><input type="checkbox" name="remove_cover"> Remover imagem atual</label></span><?php endif;?></label></div></div>

<div class="admin-card"><div class="card-head"><div><h2>Ingressos / lotes</h2><p>Cadastre Pista, Camarote, Mesa, Lote 1, Lote 2 etc.</p></div><button class="btn btn-small btn-secondary" type="button" id="add-ticket-type">+ Adicionar tipo</button></div><div id="ticket-types" class="ticket-types-editor">
<?php if(!$types):$types=[['id'=>'','name'=>'Pista','description'=>'Ingresso individual','price'=>'0.00','stock'=>'100','sold'=>0]];endif;?>
<?php foreach($types as $t):?><div class="ticket-type-row"><input type="hidden" name="ticket_id[]" value="<?=intval($t['id']??0)?>"><label>Nome<input name="ticket_name[]" value="<?=e($t['name'])?>" required></label><label>Descrição<input name="ticket_desc[]" value="<?=e($t['description']??'')?>"></label><label>Preço (R$)<input type="number" step="0.01" min="0" name="ticket_price[]" value="<?=e((string)$t['price'])?>"></label><label>Quantidade<input type="number" min="0" name="ticket_stock[]" value="<?=intval($t['stock'])?>"></label><span class="sold-hint">Vendidos: <?=intval($t['sold']??0)?></span><button class="icon-btn remove-ticket" type="button" title="Remover">×</button></div><?php endforeach;?></div></div>

<div class="admin-card"><div class="card-head"><div><h2>Lista VIP</h2><p>Ative a lista especificamente para este evento.</p></div></div><div class="form-grid two"><label class="check-line"><input type="checkbox" name="vip_enabled" <?=($event['vip_enabled']??1)?'checked':''?>> Lista VIP habilitada</label><label>Capacidade / referência<input type="number" min="0" name="vip_capacity" value="<?=intval($event['vip_capacity']??0)?>"></label></div><?php if($id):?><p><a class="text-link" href="vip.php?event=<?=$id?>">Administrar nomes da lista VIP →</a></p><?php endif;?></div>
<div class="sticky-save"><button class="btn btn-primary" type="submit">Salvar alterações</button><a class="btn btn-ghost dark-outline" href="events.php">Voltar</a></div></form><?php if($id):?><div class="admin-card danger-zone"><div class="card-head"><div><h2>Operações do evento</h2><p>Encerrar mantém histórico. Excluir só é permitido quando não existem pedidos ou nomes VIP.</p></div></div><div class="action-row"><form method="post" action="event-action.php" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-secondary" name="action" value="close" onclick="return confirm('Encerrar este evento?')">Encerrar evento</button></form><form method="post" action="event-action.php" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-ghost dark-outline" name="action" value="delete" onclick="return confirm('Excluir definitivamente este evento?')">Excluir evento</button></form></div></div><?php endif;?>
<template id="ticket-type-template"><div class="ticket-type-row"><input type="hidden" name="ticket_id[]" value="0"><label>Nome<input name="ticket_name[]" required placeholder="Ex.: Lote 2"></label><label>Descrição<input name="ticket_desc[]" placeholder="Ex.: Pista individual"></label><label>Preço (R$)<input type="number" step="0.01" min="0" name="ticket_price[]" value="0.00"></label><label>Quantidade<input type="number" min="0" name="ticket_stock[]" value="100"></label><span class="sold-hint">Vendidos: 0</span><button class="icon-btn remove-ticket" type="button">×</button></div></template>
<?php require '_footer.php';?>
