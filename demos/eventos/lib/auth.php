<?php
function admin_user(): ?array { return $_SESSION['admin'] ?? null; }
function require_admin(): void { if (!admin_user()) redirect(base_url('admin/login.php')); }

function login_throttle_status(string $email, string $ip): array
{
    $email=strtolower(trim($email));$ip=substr($ip,0,80);
    try{
        db()->exec("DELETE FROM login_attempts WHERE datetime(attempted_at)<datetime('now','-24 hours')");
        $st=db()->prepare("SELECT COUNT(*) c,MAX(CAST(strftime('%s',attempted_at) AS INTEGER)) last_ts FROM login_attempts WHERE successful=0 AND ip_address=? AND lower(COALESCE(email,''))=lower(?) AND datetime(attempted_at)>=datetime('now','-15 minutes')");
        $st->execute([$ip,$email]);$row=$st->fetch();$count=(int)($row['c']??0);
        if($count<5)return ['blocked'=>false,'seconds'=>0,'count'=>$count];
        $last=(int)($row['last_ts']??time());$seconds=max(1,900-(time()-$last));
        return ['blocked'=>true,'seconds'=>$seconds,'count'=>$count];
    }catch(Throwable $e){return ['blocked'=>false,'seconds'=>0,'count'=>0];}
}

function record_login_attempt(string $email,string $ip,bool $successful): void
{
    try{db()->prepare('INSERT INTO login_attempts(email,ip_address,successful) VALUES(?,?,?)')->execute([strtolower(trim($email)),substr($ip,0,80),$successful?1:0]);}catch(Throwable $ignored){}
}

function clear_login_failures(string $email,string $ip): void
{
    try{db()->prepare("DELETE FROM login_attempts WHERE successful=0 AND ip_address=? AND lower(COALESCE(email,''))=lower(?)")->execute([substr($ip,0,80),strtolower(trim($email))]);}catch(Throwable $ignored){}
}

function attempt_login(string $email, string $password): bool
{
    $email=trim($email);$ip=request_ip();$throttle=login_throttle_status($email,$ip);
    if($throttle['blocked']){audit_log('auth.login_blocked',null,null,['email'=>$email,'seconds'=>$throttle['seconds']]);return false;}
    $st=db()->prepare('SELECT * FROM admins WHERE lower(email)=lower(?) AND active=1 LIMIT 1');$st->execute([$email]);$user=$st->fetch();
    if(!$user || !password_verify($password,$user['password_hash'])) {
        record_login_attempt($email,$ip,false);audit_log('auth.login_failed',null,null,['email'=>$email]);return false;
    }
    clear_login_failures($email,$ip);record_login_attempt($email,$ip,true);
    session_regenerate_id(true);
    $_SESSION['admin']=['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']??'admin'];
    db()->prepare('UPDATE admins SET last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$user['id']]);
    audit_log('auth.login','admin',(int)$user['id']);
    return true;
}
function logout_admin(): void
{
    audit_log('auth.logout','admin',isset($_SESSION['admin']['id'])?(int)$_SESSION['admin']['id']:null);
    unset($_SESSION['admin']); session_regenerate_id(true);
}
