<?php

function smtp_setting(string $key, string $default=''): string
{
    global $config;
    $envMap=[
        'host'=>'SMTP_HOST','port'=>'SMTP_PORT','encryption'=>'SMTP_ENCRYPTION','username'=>'SMTP_USERNAME','password'=>'SMTP_PASSWORD',
        'from_email'=>'SMTP_FROM_EMAIL','from_name'=>'SMTP_FROM_NAME','reply_to'=>'SMTP_REPLY_TO'
    ];
    $envName=$envMap[$key]??null;
    if($envName){$env=getenv($envName);if($env!==false && trim((string)$env)!=='')return trim((string)$env);}
    $setting=app_setting('smtp_'.$key,'');
    if($setting!=='')return $setting;
    $cfg=(string)($config['smtp'][$key]??'');
    if($cfg!=='')return $cfg;
    return $default;
}

function email_transport(): string
{
    global $config;
    $env=getenv('MAIL_TRANSPORT');
    if($env!==false && trim((string)$env)!=='')$mode=strtolower(trim((string)$env));
    else $mode=strtolower(trim(app_setting('mail_transport',(string)($config['smtp']['transport']??'phpmail'))));
    return in_array($mode,['phpmail','smtp','spool'],true)?$mode:'phpmail';
}

function smtp_configured(): bool
{
    $host=smtp_setting('host');$from=smtp_setting('from_email');$user=smtp_setting('username');$pass=smtp_setting('password');
    if($host===''||filter_var($from,FILTER_VALIDATE_EMAIL)===false)return false;
    if($user!==''&&$pass==='')return false;
    return true;
}

function phpmail_configured(): bool
{
    return function_exists('mail') && filter_var(smtp_setting('from_email'),FILTER_VALIDATE_EMAIL)!==false;
}

function spool_configured(): bool
{
    $dir=dirname(__DIR__).'/storage/mail_spool';
    if(!is_dir($dir))@mkdir($dir,0775,true);
    return is_dir($dir)&&is_writable($dir);
}

function email_transport_configured(): bool
{
    return match(email_transport()){
        'smtp'=>smtp_configured(),
        'spool'=>spool_configured(),
        default=>phpmail_configured(),
    };
}

function email_transport_detail(): string
{
    return match(email_transport()){
        'smtp'=>smtp_configured()?'SMTP autenticado configurado':'SMTP incompleto',
        'spool'=>spool_configured()?'fila local / arquivo EML':'spool local indisponível',
        default=>phpmail_configured()?'PHP mail() configurado — faça o teste de entrega':'PHP mail() indisponível',
    };
}

function smtp_read($fp): string
{
    $response='';
    while(!feof($fp)){$line=fgets($fp,515);if($line===false)break;$response.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}
    return $response;
}
function smtp_expect($fp,array $codes): string{$resp=smtp_read($fp);$code=(int)substr($resp,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP respondeu '.$code.': '.trim($resp));return $resp;}
function smtp_cmd($fp,string $cmd,array $codes): string{fwrite($fp,$cmd."\r\n");return smtp_expect($fp,$codes);}
function mail_header_encode(string $value): string{if(function_exists('mb_encode_mimeheader'))return mb_encode_mimeheader($value,'UTF-8','B',"\r\n");return '=?UTF-8?B?'.base64_encode($value).'?=';}

function build_multipart_message(string $toEmail,string $toName,string $subject,string $html,string $text=''): array
{
    $from=smtp_setting('from_email');$fromName=smtp_setting('from_name',site_name());$reply=smtp_setting('reply_to',$from);
    if(!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('E-mail remetente inválido.');
    if(!filter_var($reply,FILTER_VALIDATE_EMAIL))$reply=$from;
    if(!filter_var($toEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Destinatário inválido.');
    $hostname=preg_replace('/[^a-z0-9.-]/i','',$_SERVER['SERVER_NAME']??'localhost')?:'localhost';
    $boundary='=_CactusEventos_'.bin2hex(random_bytes(8));
    if($text==='')$text=trim(preg_replace('/\s+/',' ',strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html))));
    $headers=[
        'Date: '.date(DATE_RFC2822),
        'From: '.mail_header_encode($fromName).' <'.$from.'>',
        'Reply-To: '.$reply,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
    ];
    $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text))."\r\n";
    $body.='--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html))."\r\n";
    $body.='--'.$boundary."--\r\n";
    return [$headers,$body,$from,$fromName,$reply,$hostname];
}

function php_mail_send_message(string $toEmail,string $toName,string $subject,string $html,string $text=''): void
{
    if(!phpmail_configured())throw new RuntimeException('PHP mail() não está disponível/configurado neste servidor.');
    [$headers,$body]=build_multipart_message($toEmail,$toName,$subject,$html,$text);
    $to=($toName!==''?mail_header_encode($toName).' ':'').'<'.$toEmail.'>';
    $ok=@mail($to,mail_header_encode($subject),$body,implode("\r\n",$headers));
    if(!$ok)throw new RuntimeException('O servidor recusou o envio via PHP mail(). Selecione SMTP nas configurações.');
}

function smtp_send_message(string $toEmail,string $toName,string $subject,string $html,string $text=''): void
{
    $host=smtp_setting('host');$port=(int)(smtp_setting('port','587')?:587);$enc=strtolower(smtp_setting('encryption','tls'));
    $user=smtp_setting('username');$pass=smtp_setting('password');
    if(!smtp_configured())throw new RuntimeException('SMTP não configurado por completo.');
    [$headers,$body,$from,$fromName,$reply,$hostname]=build_multipart_message($toEmail,$toName,$subject,$html,$text);
    $transport=$enc==='ssl'?'ssl://':'';$errno=0;$errstr='';
    $fp=@stream_socket_client($transport.$host.':'.$port,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
    if(!$fp)throw new RuntimeException('Falha ao conectar ao SMTP: '.$errstr.' ('.$errno.')');
    stream_set_timeout($fp,20);
    try{
        smtp_expect($fp,[220]);smtp_cmd($fp,'EHLO '.$hostname,[250]);
        if($enc==='tls'){smtp_cmd($fp,'STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Falha ao ativar TLS no SMTP.');smtp_cmd($fp,'EHLO '.$hostname,[250]);}
        if($user!==''){smtp_cmd($fp,'AUTH LOGIN',[334]);smtp_cmd($fp,base64_encode($user),[334]);smtp_cmd($fp,base64_encode($pass),[235]);}
        smtp_cmd($fp,'MAIL FROM:<'.$from.'>',[250]);smtp_cmd($fp,'RCPT TO:<'.$toEmail.'>',[250,251]);smtp_cmd($fp,'DATA',[354]);
        $smtpHeaders=array_merge($headers,['To: '.($toName!==''?mail_header_encode($toName).' ':'').'<'.$toEmail.'>','Subject: '.mail_header_encode($subject),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$hostname.'>']);
        $payload=implode("\r\n",$smtpHeaders)."\r\n\r\n".$body;$payload=preg_replace('/(?m)^\./','..',$payload);
        fwrite($fp,$payload."\r\n.\r\n");smtp_expect($fp,[250]);@smtp_cmd($fp,'QUIT',[221]);
    }finally{@fclose($fp);}
}

function spool_send_message(string $toEmail,string $toName,string $subject,string $html,string $text=''): void
{
    if(!spool_configured())throw new RuntimeException('Spool local indisponível.');
    [$headers,$body]=build_multipart_message($toEmail,$toName,$subject,$html,$text);
    $dir=dirname(__DIR__).'/storage/mail_spool';
    $name=date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.eml';
    $payload='To: '.($toName!==''?mail_header_encode($toName).' ':'').'<'.$toEmail.'>'."\r\n".'Subject: '.mail_header_encode($subject)."\r\n".implode("\r\n",$headers)."\r\n\r\n".$body;
    if(file_put_contents($dir.'/'.$name,$payload)===false)throw new RuntimeException('Falha ao gravar e-mail no spool local.');
}

function send_transactional_message(string $toEmail,string $toName,string $subject,string $html,string $text=''): void
{
    match(email_transport()){
        'smtp'=>smtp_send_message($toEmail,$toName,$subject,$html,$text),
        'spool'=>spool_send_message($toEmail,$toName,$subject,$html,$text),
        default=>php_mail_send_message($toEmail,$toName,$subject,$html,$text),
    };
}
