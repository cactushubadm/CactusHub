<?php
$root=dirname(__DIR__);$storage=$root.'/storage';if(!is_dir($storage))mkdir($storage,0775,true);
$tmp=$storage.'/selftest-'.bin2hex(random_bytes(4)).'.sqlite';
putenv('CACTUS_DB_PATH='.$tmp);putenv('PAYMENT_MODE=manual');
$fail=[];$ok=[];
function tassert(bool $cond,string $name): void {global $fail,$ok;if($cond){$ok[]=$name;echo "[OK] $name\n";}else{$fail[]=$name;echo "[FALHA] $name\n";}}
try{
    if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('pdo_sqlite não carregado');
    require_once $root.'/lib/schema.php';
    $pdo=new PDO('sqlite:'.$tmp);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$pdo->exec('PRAGMA foreign_keys=ON');migrate_database($pdo);
    $pdo->prepare('INSERT INTO admins(name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['Self Test','selftest@local',password_hash('SelfTest#123',PASSWORD_DEFAULT),'admin']);
    unset($pdo);
    $_SERVER['SCRIPT_NAME']='/tools/selftest.php';$_SERVER['SERVER_NAME']='localhost';$_SERVER['HTTP_HOST']='localhost';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    require_once $root.'/lib/bootstrap.php';
    tassert(table_exists(db(),'email_queue'),'Migração: fila de e-mail');
    tassert(table_exists(db(),'checkin_logs'),'Migração: histórico de check-in');
    tassert(table_exists(db(),'legal_acceptances'),'Migração: aceites legais');
    tassert(table_exists(db(),'login_attempts'),'Migração: limitação de login');
    db()->prepare("INSERT INTO events(title,slug,event_date,venue,status,vip_enabled) VALUES(?,?,?,?,?,1)")->execute(['Evento Self Test','evento-selftest',(new DateTime('+10 days'))->format('Y-m-d H:i:s'),'Sua Marca','published']);$eventId=(int)db()->lastInsertId();
    db()->prepare('INSERT INTO ticket_types(event_id,name,price,stock,sold,active) VALUES(?,?,?,?,0,1)')->execute([$eventId,'Pista',25,3]);$typeId=(int)db()->lastInsertId();
    $reserve=db()->prepare('UPDATE ticket_types SET sold=sold+1 WHERE id=? AND (stock-sold)>=1');$reserve->execute([$typeId]);tassert($reserve->rowCount()===1,'Reserva atômica de estoque');
    $token=order_public_token();db()->prepare("INSERT INTO orders(order_number,public_token,event_id,buyer_name,buyer_email,total,status,payment_mode,expires_at,customer_ip) VALUES(?,?,?,?,?,25,'pending','manual',?,?)")->execute([order_number(),$token,$eventId,'Cliente Teste','cliente@example.test',(new DateTime('+20 min'))->format('Y-m-d H:i:s'),'127.0.0.1']);$orderId=(int)db()->lastInsertId();
    db()->prepare('INSERT INTO order_items(order_id,ticket_type_id,ticket_name,unit_price,quantity,subtotal) VALUES(?,?,?,?,1,25)')->execute([$orderId,$typeId,'Pista',25]);record_legal_acceptance($orderId,false);
    tassert(mark_order_paid($orderId)===true,'Pagamento manual e emissão');
    $count=(int)db()->query('SELECT COUNT(*) FROM tickets WHERE order_id='.$orderId)->fetchColumn();tassert($count===1,'Ingresso individual emitido');
    $code=(string)db()->query('SELECT code FROM tickets WHERE order_id='.$orderId)->fetchColumn();tassert(str_starts_with(ticket_payload($code),'CACTUS:TICKET:EV-'),'Payload QR');
    $r=checkin_ticket(ticket_payload($code),1);tassert(($r['ok']??false)===true,'Primeiro check-in aceito');
    $r2=checkin_ticket($code,1);tassert(($r2['ok']??true)===false && !empty($r2['already_used']),'Segundo check-in bloqueado');
    $tid=(int)db()->query('SELECT id FROM tickets WHERE order_id='.$orderId)->fetchColumn();undo_ticket_checkin($tid,1);tassert((string)db()->query('SELECT status FROM tickets WHERE id='.$tid)->fetchColumn()==='valid','Desfazer check-in');
    tassert((int)db()->query('SELECT COUNT(*) FROM legal_acceptances WHERE order_id='.$orderId)->fetchColumn()===1,'Aceite legal registrado');
    tassert((int)db()->query('SELECT COUNT(*) FROM email_queue WHERE dedupe_key="tickets-'.$orderId.'"')->fetchColumn()===1,'E-mail de ingresso enfileirado');
    record_login_attempt('x@test','127.0.0.1',false);tassert((login_throttle_status('x@test','127.0.0.1')['count']??0)>=1,'Tentativa de login persistente');
    tassert(is_file($root.'/assets/vendor/cactus-qr.js'),'Gerador QR local presente');
}catch(Throwable $e){$fail[]='Exceção: '.$e->getMessage();echo '[EXCEÇÃO] '.$e->getMessage()."\n";}
@unlink($tmp);@unlink($tmp.'-wal');@unlink($tmp.'-shm');
echo "\nResultado: ".count($ok)." OK / ".count($fail)." falha(s).\n";if($fail){foreach($fail as $f)echo '- '.$f."\n";exit(1);}exit(0);
