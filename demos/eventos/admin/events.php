<?php require __DIR__ . '/../lib/bootstrap.php';require_admin();$pageTitle='Eventos';
$events=db()->query("SELECT e.*, (SELECT COUNT(*) FROM ticket_types t WHERE t.event_id=e.id) ticket_types_count, (SELECT COUNT(*) FROM vip_entries v WHERE v.event_id=e.id AND v.status!='removed') vip_count FROM events e ORDER BY datetime(e.event_date) DESC")->fetchAll();
require '_header.php';?>
<div class="admin-card"><div class="card-head"><div><h2>Agenda</h2><p>Crie eventos, cadastre lotes de ingressos e habilite a lista VIP.</p></div><a class="btn btn-primary btn-small" href="event-form.php">+ Novo evento</a></div>
<div class="table-wrap"><table><thead><tr><th>Evento</th><th>Data</th><th>Status</th><th>Ingressos</th><th>VIP</th><th>Ações</th></tr></thead><tbody>
<?php if(!$events):?><tr><td colspan="6">Nenhum evento cadastrado.</td></tr><?php endif;?>
<?php foreach($events as $ev):?><tr><td><b><?=e($ev['title'])?></b><small class="cell-sub"><?=e($ev['venue'])?></small></td><td><?=e(dt_br($ev['event_date']))?></td><td><span class="pill"><?=e(status_label($ev['status']))?></span></td><td><?=intval($ev['ticket_types_count'])?> tipo(s)</td><td><?=intval($ev['vip_count'])?> nome(s)</td><td class="actions"><a href="event-form.php?id=<?=intval($ev['id'])?>">Editar</a><a href="vip.php?event=<?=intval($ev['id'])?>">VIP</a><?php if($ev['status']==='published'):?><a href="../event.php?slug=<?=urlencode($ev['slug'])?>" target="_blank">Ver ↗</a><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php require '_footer.php';?>
