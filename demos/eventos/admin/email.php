<?php
require __DIR__.'/../lib/bootstrap.php';require_admin();$pageTitle='E-mails';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=$_POST['action']??'';
    try{
        if($action==='test'){
            $to=trim($_POST['test_email']??'');if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail válido para teste.');
            send_transactional_message($to,'Teste '.site_name(),'Teste de e-mail • '.site_name(),html_email_shell('E-mail funcionando','<p>Este é um teste enviado pelo painel. Se você recebeu esta mensagem, o envio transacional está operacional.</p>'),'Teste de e-mail '.site_name());
            audit_log('email.test_sent',null,null,['to'=>$to]);set_app_setting('email_last_test_ok',date('Y-m-d H:i:s'));set_app_setting('email_last_test_to',$to);flash('success','E-mail de teste processado para '.$to.'. Confirme o recebimento na caixa de entrada.');
        }elseif($action==='process'){
            $r=process_email_queue(50);flash('success','Fila processada: '.$r['sent'].' enviado(s), '.$r['failed'].' falha(s).');
        }elseif($action==='retry'){
            $id=(int)($_POST['id']??0);db()->prepare("UPDATE email_queue SET status='pending',next_attempt_at=CURRENT_TIMESTAMP,last_error=NULL WHERE id=?")->execute([$id]);flash('success','Mensagem colocada novamente na fila.');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());}
    redirect('email.php');
}
$queue=db()->query('SELECT * FROM email_queue ORDER BY id DESC LIMIT 150')->fetchAll();
$counts=[];foreach(db()->query('SELECT status,COUNT(*) c FROM email_queue GROUP BY status')->fetchAll() as $r)$counts[$r['status']]=$r['c'];
require '_header.php';?>
<div class="stats-grid"><div class="stat-card"><small>Pendentes</small><strong><?=intval($counts['pending']??0)?></strong></div><div class="stat-card"><small>Enviados</small><strong><?=intval($counts['sent']??0)?></strong></div><div class="stat-card"><small>Falhas</small><strong><?=intval($counts['failed']??0)?></strong></div></div>
<div class="admin-card"><div class="card-head"><div><h2>Teste e processamento</h2><p>O cron processa a fila automaticamente; aqui você pode testar manualmente.</p></div></div>
<form method="post" class="form-grid two"><?=csrf_field()?><label>E-mail para teste<input type="email" name="test_email" value="<?=e(app_setting('support_email',smtp_setting('from_email')))?>"></label><div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><button class="btn btn-primary" name="action" value="test">Enviar teste de e-mail</button><button class="btn btn-secondary" name="action" value="process">Processar fila agora</button></div></form>
<?php if(!email_transport_configured()):?><div class="alert error">O método de envio selecionado ainda não está completo. Configure em <a href="settings.php">Configurações</a>.</div><?php else:?><div class="alert success">Método atual: <?=e(email_transport_detail())?>. Envie um teste e confirme o recebimento.</div><?php endif;?></div>
<div class="admin-card"><div class="card-head"><div><h2>Fila de e-mails</h2><p>Últimas 150 mensagens transacionais.</p></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Destinatário</th><th>Assunto</th><th>Status</th><th>Tentativas</th><th>Próxima</th><th>Erro</th><th></th></tr></thead><tbody><?php foreach($queue as $q):?><tr><td>#<?=intval($q['id'])?></td><td><?=e($q['to_name'])?><br><small><?=e($q['to_email'])?></small></td><td><?=e($q['subject'])?></td><td><span class="pill <?=e($q['status'])?>"><?=e(status_label($q['status']))?></span></td><td><?=intval($q['attempts'])?> / <?=intval($q['max_attempts'])?></td><td><?=e($q['next_attempt_at']?dt_db_br($q['next_attempt_at']):'—')?></td><td><small><?=e($q['last_error']?:'—')?></small></td><td><?php if($q['status']==='failed'):?><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=intval($q['id'])?>"><button class="btn btn-small btn-ghost dark-outline" name="action" value="retry">Repetir</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$queue):?><tr><td colspan="8">Nenhuma mensagem na fila.</td></tr><?php endif;?></tbody></table></div></div>
<?php require '_footer.php';?>
