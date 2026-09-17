<?php

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();
require_once __DIR__ . '/../lib/site.php';
ensure_storage();

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function flash(string $type, string $message): void { $_SESSION['flash'] = ['type'=>$type,'message'=>$message]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return is_array($f)?$f:null; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(): bool { return isset($_POST['csrf'],$_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'],(string)$_POST['csrf']); }
function redirect_self(): never { header('Location: ./'); exit; }
function optional_url(string $url): bool { return $url === '' || valid_http_url($url); }
function clean_key(string $raw, string $fallback='item'): string {
    $key=preg_replace('/[^a-zA-Z0-9_-]/','',$raw) ?: '';
    return $key !== '' ? substr($key,0,64) : $fallback.'-'.bin2hex(random_bytes(4));
}

$admin=load_admin_config();
$isSetup=!empty($admin['passwordHash']);
$logged=!empty($_SESSION['admin_logged']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');

    if($action==='setup' && !$isSetup){
        $username=trim((string)($_POST['username']??'admin')) ?: 'admin';
        $pass=(string)($_POST['password']??'');
        $confirm=(string)($_POST['confirm']??'');
        if(strlen($pass)<8) flash('error','A senha precisa ter pelo menos 8 caracteres.');
        elseif($pass!==$confirm) flash('error','As senhas não conferem.');
        else{
            save_admin_config(['username'=>$username,'passwordHash'=>password_hash($pass,PASSWORD_DEFAULT),'createdAt'=>date(DATE_ATOM)]);
            $_SESSION['admin_logged']=true; $_SESSION['admin_user']=$username; session_regenerate_id(true);
            flash('success','Administrador criado. O painel já está liberado.');
        }
        redirect_self();
    }

    if($action==='login' && $isSetup){
        $username=trim((string)($_POST['username']??''));
        $pass=(string)($_POST['password']??'');
        $ok=hash_equals((string)($admin['username']??'admin'),$username) && password_verify($pass,(string)$admin['passwordHash']);
        if($ok){ $_SESSION['admin_logged']=true; $_SESSION['admin_user']=$username; $_SESSION['login_failures']=0; session_regenerate_id(true); flash('success','Acesso liberado.'); }
        else { $_SESSION['login_failures']=(int)($_SESSION['login_failures']??0)+1; if($_SESSION['login_failures']>4) usleep(700000); flash('error','Usuário ou senha incorretos.'); }
        redirect_self();
    }

    if($action==='logout'){
        $_SESSION=[];
        if(ini_get('session.use_cookies')){ $params=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']); }
        session_destroy(); header('Location: ./'); exit;
    }

    if(!$logged || !verify_csrf()){ http_response_code(403); exit('Acesso negado.'); }

    if($action==='save'){
        $config=load_site_config();
        $errors=[]; $newUploads=[]; $oldUploadsToDelete=[];

        // 1) Marca e identidade
        $postedBrand=is_array($_POST['brand']??null)?$_POST['brand']:[];
        $brand=is_array($config['brand']??null)?$config['brand']:[];
        $textFields=[
            'businessName'=>80,'businessSubname'=>80,'categoryLabel'=>100,'heroTitle1'=>80,'heroTitle2'=>80,'heroText'=>420,
            'showcaseEyebrow'=>100,'showcaseTitle1'=>80,'showcaseTitle2'=>80,'showcaseText'=>420,
            'aboutEyebrow'=>100,'aboutTitle1'=>80,'aboutTitle2'=>80,'aboutText'=>700,
            'aboutBadge1'=>100,'aboutBadge2'=>100,'aboutBadge3'=>100,'footerText'=>260,'instagramHandle'=>80
        ];
        foreach($textFields as $field=>$max){
            if(array_key_exists($field,$postedBrand)) $brand[$field]=safe_text($postedBrand[$field],$max);
        }
        foreach(['accentColor','accentColor2'] as $field){
            $value=trim((string)($postedBrand[$field]??($brand[$field]??'')));
            if(!valid_hex_color($value)) $errors[]='Cor inválida em '.($field==='accentColor'?'cor principal':'cor secundária').'.';
            else $brand[$field]=$value;
        }
        $insta=trim((string)($postedBrand['instagramUrl']??($brand['instagramUrl']??'')));
        if(!optional_url($insta)) $errors[]='Link do Instagram inválido.'; else $brand['instagramUrl']=$insta;

        // Logo do cliente
        if(isset($_FILES['brand_logo']) && ($_FILES['brand_logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            [$ok,$path,$message]=save_uploaded_image($_FILES['brand_logo'],'logo');
            if(!$ok || !$path) $errors[]='Logo: '.($message?:'não foi possível enviar.');
            else{
                $old=(string)($brand['logo']??''); $brand['logo']=$path; $newUploads[]=$path;
                if(str_starts_with($old,'uploads/')) $oldUploadsToDelete[]=$old;
            }
        }
        $config['brand']=$brand;

        // 2) Fotos fixas do layout
        if(!$errors){
            foreach(image_slot_labels() as $slot=>$label){
                $key='image_'.$slot;
                if(!isset($_FILES[$key])) continue;
                [$ok,$path,$message]=save_uploaded_image($_FILES[$key],$slot);
                if(!$ok) $errors[]=$label.': '.$message;
                elseif($path){
                    $old=(string)($config['images'][$slot]??'');
                    $config['images'][$slot]=$path; $newUploads[]=$path;
                    if(str_starts_with($old,'uploads/')) $oldUploadsToDelete[]=$old;
                }
            }
        }

        // Legendas dos 6 destaques
        $showcase=[];
        for($i=0;$i<6;$i++){
            $showcase[]=[
                'slot'=>'showcase'.($i+1),
                'title'=>safe_text($_POST['showcase_title'][$i]??('PRODUTO '.str_pad((string)($i+1),2,'0',STR_PAD_LEFT)),90),
                'subtitle'=>safe_text($_POST['showcase_subtitle'][$i]??'Coloque seu produto aqui',120),
            ];
        }
        $config['showcase']=$showcase;

        // 3) Galeria livre
        if(!$errors){
            $currentGallery=gallery_items($config); $currentById=[];
            foreach($currentGallery as $item) $currentById[(string)$item['id']]=$item;
            $deleteFlags=is_array($_POST['gallery_delete']??null)?$_POST['gallery_delete']:[];
            $galleryTitles=is_array($_POST['gallery_title']??null)?$_POST['gallery_title']:[];
            $orderRaw=trim((string)($_POST['gallery_order']??'')); $orderIds=[];
            foreach(explode(',',$orderRaw) as $rawId){
                $id=preg_replace('/[^a-zA-Z0-9_-]/','',trim($rawId));
                if($id!=='' && isset($currentById[$id]) && !in_array($id,$orderIds,true)) $orderIds[]=$id;
            }
            if(!$orderIds) $orderIds=array_keys($currentById);
            foreach(array_keys($currentById) as $id) if(!in_array($id,$orderIds,true)) $orderIds[]=$id;
            $gallery=[];
            foreach($orderIds as $id){
                $item=$currentById[$id];
                if((string)($deleteFlags[$id]??'0')==='1'){
                    $old=(string)($item['src']??''); if(str_starts_with($old,'uploads/')) $oldUploadsToDelete[]=$old; continue;
                }
                $title=safe_text($galleryTitles[$id]??($item['title']??'Foto do produto'),90);
                $item['title']=$title!==''?$title:'Foto do produto'; $gallery[]=$item;
            }
            $newFiles=normalize_multi_upload($_FILES['gallery_new']??[]);
            $newTitles=is_array($_POST['gallery_new_title']??null)?array_values($_POST['gallery_new_title']):[];
            $realNew=0;
            foreach($newFiles as $index=>$file){
                if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
                $realNew++; if($realNew>12){$errors[]='Adicione no máximo 12 novas fotos por vez.'; break;}
                [$ok,$path,$message]=save_uploaded_image($file,'galeria');
                if(!$ok || !$path){$errors[]='Galeria: '.($message?:'não foi possível enviar uma das fotos.'); break;}
                $newUploads[]=$path;
                $raw=safe_text($newTitles[$index]??'',90);
                if($raw===''){ $raw=safe_text(str_replace(['_','-'],' ',pathinfo((string)($file['name']??''),PATHINFO_FILENAME)),90); }
                $gallery[]=['id'=>'g-'.bin2hex(random_bytes(6)),'src'=>$path,'title'=>$raw!==''?$raw:'Foto do produto'];
            }
            if(count($gallery)>40) $errors[]='A galeria pode ter no máximo 40 fotos.'; else $config['gallery']=$gallery;
        }

        // 4) Unidades totalmente administráveis
        if(!$errors){
            $orderRaw=trim((string)($_POST['unit_order']??'')); $keys=[];
            foreach(explode(',',$orderRaw) as $raw){$key=clean_key(trim($raw),'u'); if(!in_array($key,$keys,true)) $keys[]=$key;}
            $names=is_array($_POST['unit_name']??null)?$_POST['unit_name']:[];
            if(!$keys) $keys=array_map(fn($k)=>clean_key((string)$k,'u'),array_keys($names));
            if(count($keys)>20) $errors[]='Cadastre no máximo 20 unidades.';
            $units=[];
            foreach($keys as $key){
                $name=safe_text($names[$key]??'',80); if($name==='') continue;
                $address=safe_text($_POST['unit_address'][$key]??'',180);
                $phone=safe_text($_POST['unit_phone'][$key]??'',40);
                $maps=trim((string)($_POST['unit_maps'][$key]??''));
                $order=trim((string)($_POST['unit_order_url'][$key]??''));
                if(!optional_url($maps)) $errors[]='Link de mapa inválido em '.$name.'.';
                if(!optional_url($order)) $errors[]='Link de pedido inválido em '.$name.'.';
                $units[]=['id'=>$key,'name'=>$name,'address'=>$address,'phoneDisplay'=>$phone,'maps'=>$maps,'orderUrl'=>$order,'enabled'=>isset($_POST['unit_enabled'][$key])];
            }
            if(!$units) $errors[]='Cadastre pelo menos uma unidade.';
            else $config['units']=$units;
        }

        // 5) Promoções fixas de segunda a sexta
        if(!$errors){
            $existing=[]; foreach(($config['promotions']??[]) as $p) $existing[(int)($p['weekday']??0)]=$p;
            $dayAbbr=[1=>'SEG',2=>'TER',3=>'QUA',4=>'QUI',5=>'SEX'];
            $dayFull=[1=>'Segunda-feira',2=>'Terça-feira',3=>'Quarta-feira',4=>'Quinta-feira',5=>'Sexta-feira'];
            $promos=[];
            for($day=1;$day<=5;$day++){
                $old=$existing[$day]??[];
                $title=safe_text($_POST['promo_title'][$day]??($old['title']??''),120);
                $desc=safe_text($_POST['promo_description'][$day]??($old['description']??''),240);
                $price=safe_text($_POST['promo_price'][$day]??($old['price']??''),60);
                $url=trim((string)($_POST['promo_url'][$day]??($old['orderUrl']??'')));
                if(!optional_url($url)) $errors[]='Link inválido na promoção de '.$dayFull[$day].'.';
                $promos[]=['weekday'=>$day,'day'=>$dayAbbr[$day],'fullDay'=>$dayFull[$day],'title'=>$title,'description'=>$desc,'price'=>$price,'orderUrl'=>$url,'direct'=>isset($_POST['promo_direct'][$day]),'enabled'=>isset($_POST['promo_enabled'][$day])];
            }
            $config['promotions']=$promos;
        }

        if($errors){
            foreach(array_unique($newUploads) as $path){ if(str_starts_with($path,'uploads/')){ $f=SITE_ROOT.'/'.$path; if(is_file($f)) @unlink($f); } }
            flash('error',implode(' ',array_unique($errors)));
        }elseif(save_site_config($config)){
            foreach(array_unique($oldUploadsToDelete) as $path) remove_uploaded_path_if_unused($path,$config);
            flash('success','Alterações publicadas no site com sucesso.');
        }else{
            foreach(array_unique($newUploads) as $path){ if(str_starts_with($path,'uploads/')){ $f=SITE_ROOT.'/'.$path; if(is_file($f)) @unlink($f); } }
            flash('error','Não foi possível gravar data/site.json. Verifique as permissões de escrita.');
        }
        redirect_self();
    }

    if($action==='password'){
        $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['new_confirm']??'');
        $admin=load_admin_config();
        if(!password_verify($current,(string)($admin['passwordHash']??''))) flash('error','Senha atual incorreta.');
        elseif(strlen($new)<8) flash('error','A nova senha precisa ter pelo menos 8 caracteres.');
        elseif($new!==$confirm) flash('error','A confirmação da nova senha não confere.');
        else{$admin['passwordHash']=password_hash($new,PASSWORD_DEFAULT);$admin['updatedAt']=date(DATE_ATOM);save_admin_config($admin);flash('success','Senha alterada com sucesso.');}
        redirect_self();
    }
}

$flash=take_flash(); $admin=load_admin_config(); $isSetup=!empty($admin['passwordHash']); $logged=!empty($_SESSION['admin_logged']);

function page_head(string $title): void { ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= h($title) ?> | Cactus Site Manager</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="admin.css"></head><body>
<?php }

if(!$isSetup){ page_head('Criar administrador'); ?>
<main class="auth-shell"><section class="auth-card"><div class="auth-brand"><span>C</span><div><strong>CACTUS</strong><small>SITE MANAGER</small></div></div><div class="auth-tag">PRIMEIRO ACESSO</div><h1>Crie o acesso do administrador</h1><p>O cliente define a própria senha. Não existe senha padrão no produto.</p><?php if($flash): ?><div class="flash flash--<?=h($flash['type'])?>"><?=h($flash['message'])?></div><?php endif; ?><form method="post" class="auth-form"><input type="hidden" name="action" value="setup"><label>Usuário<input name="username" value="admin" autocomplete="username" required></label><label>Senha<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label>Confirmar senha<input type="password" name="confirm" minlength="8" autocomplete="new-password" required></label><button class="primary" type="submit">CRIAR ADMINISTRADOR</button></form></section></main></body></html>
<?php exit; }

if(!$logged){ page_head('Entrar'); ?>
<main class="auth-shell"><section class="auth-card"><div class="auth-brand"><span>C</span><div><strong>CACTUS</strong><small>SITE MANAGER</small></div></div><div class="auth-tag">ÁREA RESTRITA</div><h1>Administrador</h1><p>Entre para gerenciar marca, fotos, unidades, promoções e links.</p><?php if($flash): ?><div class="flash flash--<?=h($flash['type'])?>"><?=h($flash['message'])?></div><?php endif; ?><form method="post" class="auth-form"><input type="hidden" name="action" value="login"><label>Usuário<input name="username" value="<?=h($admin['username']??'admin')?>" autocomplete="username" required></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="primary" type="submit">ENTRAR NO PAINEL</button></form><a class="back-site" href="../">← Voltar para o site</a></section></main></body></html>
<?php exit; }

$config=load_site_config(); $brand=is_array($config['brand']??null)?$config['brand']:[]; $labels=image_slot_labels(); $gallery=gallery_items($config); $units=is_array($config['units']??null)?$config['units']:[]; $showcase=is_array($config['showcase']??null)?$config['showcase']:[];
$writable=is_writable(SITE_DATA)&&is_writable(UPLOAD_DIR); page_head('Painel administrativo');
?>
<header class="admin-topbar"><div class="admin-logo"><span>C</span><div><strong>CACTUS</strong><small>SITE MANAGER</small></div></div><div class="admin-topbar__actions"><a href="../" target="_blank" rel="noopener">Ver site ↗</a><form method="post"><input type="hidden" name="action" value="logout"><button class="link-button">Sair</button></form></div></header>
<main class="admin-wrap">
  <section class="admin-hero"><div><span class="kicker">PRODUTO WHITE-LABEL</span><h1>Painel do cliente</h1><p>Personalize a marca inteira sem editar código. Logo, textos, cores, fotos, galeria, unidades, promoções e links externos.</p></div><div class="status <?=$writable?'status--ok':'status--warn'?>"><i></i><?=$writable?'Sistema pronto para salvar':'Verifique permissões de data/ e uploads/'?></div></section>
  <?php if($flash): ?><div class="flash flash--<?=h($flash['type'])?>"><?=h($flash['message'])?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="admin-form" id="contentForm"><input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">

    <section class="panel-section" id="marca">
      <div class="section-title"><div><span>01</span><h2>Marca e identidade</h2></div><p>Transforme o template na identidade do cliente. A logo e as cores são aplicadas automaticamente no site.</p></div>
      <div class="brand-admin-grid">
        <article class="brand-logo-card"><div class="brand-logo-preview"><img id="logoPreview" src="../<?=h($brand['logo']??'assets/placeholders/client-logo.svg')?>" alt=""></div><label class="upload-button">TROCAR LOGO<input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp" id="logoInput"></label><small>PNG com fundo transparente é recomendado.</small></article>
        <div class="brand-fields">
          <label>Nome da marca<input name="brand[businessName]" value="<?=h($brand['businessName']??'SUA MARCA')?>" maxlength="80"></label>
          <label>Complemento da marca<input name="brand[businessSubname]" value="<?=h($brand['businessSubname']??'RESTAURANTE & DELIVERY')?>" maxlength="80"></label>
          <label>Chamada acima do título<input name="brand[categoryLabel]" value="<?=h($brand['categoryLabel']??'SEU NEGÓCIO, SUA IDENTIDADE')?>" maxlength="100"></label>
          <div class="field-row"><label>Título principal — linha 1<input name="brand[heroTitle1]" value="<?=h($brand['heroTitle1']??'SEU SABOR')?>" maxlength="80"></label><label>Título principal — linha 2<input name="brand[heroTitle2]" value="<?=h($brand['heroTitle2']??'EM DESTAQUE.')?>" maxlength="80"></label></div>
          <label>Texto principal<textarea name="brand[heroText]" maxlength="420" rows="3"><?=h($brand['heroText']??'')?></textarea></label>
          <div class="field-row"><label>Cor principal<input type="color" name="brand[accentColor]" value="<?=h($brand['accentColor']??'#ff8a00')?>"></label><label>Cor secundária<input type="color" name="brand[accentColor2]" value="<?=h($brand['accentColor2']??'#ff3b30')?>"></label></div>
          <div class="field-row"><label>Instagram — @usuário<input name="brand[instagramHandle]" value="<?=h($brand['instagramHandle']??'@seuinstagram')?>" maxlength="80"></label><label>Link do Instagram<input type="url" name="brand[instagramUrl]" value="<?=h($brand['instagramUrl']??'')?>" placeholder="https://instagram.com/suamarca"></label></div>
        </div>
      </div>
      <div class="content-copy-grid">
        <label>Chamada da vitrine<input name="brand[showcaseEyebrow]" value="<?=h($brand['showcaseEyebrow']??'COLOQUE SUAS FOTOS AQUI')?>"></label>
        <label>Título vitrine — linha 1<input name="brand[showcaseTitle1]" value="<?=h($brand['showcaseTitle1']??'MOSTRE SEUS')?>"></label>
        <label>Título vitrine — linha 2<input name="brand[showcaseTitle2]" value="<?=h($brand['showcaseTitle2']??'PRODUTOS.')?>"></label>
        <label class="wide">Texto da vitrine<textarea name="brand[showcaseText]" rows="3"><?=h($brand['showcaseText']??'')?></textarea></label>
        <label>Chamada do Sobre<input name="brand[aboutEyebrow]" value="<?=h($brand['aboutEyebrow']??'CONTE A SUA HISTÓRIA')?>"></label>
        <label>Título Sobre — linha 1<input name="brand[aboutTitle1]" value="<?=h($brand['aboutTitle1']??'SUA MARCA.')?>"></label>
        <label>Título Sobre — linha 2<input name="brand[aboutTitle2]" value="<?=h($brand['aboutTitle2']??'SEU JEITO.')?>"></label>
        <label class="wide">Texto Sobre<textarea name="brand[aboutText]" rows="4"><?=h($brand['aboutText']??'')?></textarea></label>
        <label>Badge 1<input name="brand[aboutBadge1]" value="<?=h($brand['aboutBadge1']??'✦ Destaque seu diferencial')?>"></label><label>Badge 2<input name="brand[aboutBadge2]" value="<?=h($brand['aboutBadge2']??'✦ Fale sobre sua experiência')?>"></label><label>Badge 3<input name="brand[aboutBadge3]" value="<?=h($brand['aboutBadge3']??'✦ Direcione para o pedido online')?>"></label>
        <label class="wide">Texto do rodapé<input name="brand[footerText]" value="<?=h($brand['footerText']??'')?>"></label>
      </div>
    </section>

    <section class="panel-section" id="fotos">
      <div class="section-title"><div><span>02</span><h2>Fotos principais do site</h2></div><p>Troque as imagens que ocupam posições específicas no layout. JPG, PNG ou WEBP · até 8 MB por foto.</p></div>
      <div class="photo-grid">
        <?php foreach($labels as $slot=>$label): $src=(string)($config['images'][$slot]??''); ?>
        <article class="photo-admin-card"><div class="photo-preview"><img src="<?=$src?'../'.h($src):''?>" alt="" data-preview-for="<?=h($slot)?>" <?=$src?'':'hidden'?>> <span><?=h($label)?></span></div><div class="photo-admin-card__body"><strong><?=h($label)?></strong><label class="upload-button">TROCAR FOTO<input type="file" name="image_<?=h($slot)?>" accept="image/jpeg,image/png,image/webp" data-preview-input="<?=h($slot)?>"></label><?php if(str_starts_with($slot,'showcase')): $idx=(int)substr($slot,8)-1; $item=$showcase[$idx]??[]; ?><label>Nome do produto<input type="text" name="showcase_title[<?=$idx?>]" value="<?=h($item['title']??('PRODUTO '.str_pad((string)($idx+1),2,'0',STR_PAD_LEFT)))?>" maxlength="90"></label><label>Subtítulo<input type="text" name="showcase_subtitle[<?=$idx?>]" value="<?=h($item['subtitle']??'Coloque seu produto aqui')?>" maxlength="120"></label><?php endif; ?><small>A imagem atual permanece até você selecionar outra.</small></div></article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel-section" id="galeria">
      <div class="section-title"><div><span>03</span><h2>Galeria livre</h2></div><p>Adicione, exclua e reordene as fotos. Arraste pelo ícone ⋮⋮ ou use as setas.</p></div>
      <div class="gallery-toolbar"><div><strong><?=count($gallery)?> foto<?=count($gallery)===1?'':'s'?> publicada<?=count($gallery)===1?'':'s'?></strong><span>Máximo de 40 fotos.</span></div><label class="gallery-add">+ ADICIONAR FOTOS<input id="galleryNewInput" type="file" name="gallery_new[]" accept="image/jpeg,image/png,image/webp" multiple></label></div>
      <input type="hidden" name="gallery_order" id="galleryOrder" value="<?=h(implode(',',array_column($gallery,'id')))?>">
      <div class="gallery-admin-list" id="galleryList">
        <?php foreach($gallery as $index=>$item): $gid=(string)$item['id']; ?>
        <article class="gallery-admin-item" data-gallery-id="<?=h($gid)?>" draggable="true"><button class="drag-handle" type="button" aria-label="Arrastar foto" title="Arraste para reordenar">⋮⋮</button><div class="gallery-thumb"><img src="../<?=h($item['src']??'')?>" alt=""></div><label class="gallery-caption">Legenda<input type="text" name="gallery_title[<?=h($gid)?>]" value="<?=h($item['title']??'')?>" maxlength="90"></label><div class="gallery-position"><small>POSIÇÃO</small><strong data-position><?=$index+1?></strong></div><div class="gallery-actions"><button type="button" data-move-up>↑</button><button type="button" data-move-down>↓</button><button class="gallery-remove" type="button" data-delete-gallery>REMOVER</button></div><input type="hidden" name="gallery_delete[<?=h($gid)?>]" value="0" data-delete-input></article>
        <?php endforeach; ?>
      </div>
      <div class="gallery-new-wrap" id="galleryNewWrap" hidden><div class="gallery-new-head"><strong>Novas fotos</strong><span>Entram no fim da galeria; depois podem ser reordenadas.</span></div><div class="gallery-new-grid" id="galleryNewPreview"></div></div>
    </section>

    <section class="panel-section" id="unidades">
      <div class="section-title"><div><span>04</span><h2>Unidades e links de pedido</h2></div><p>Adicione quantas unidades precisar. Cada uma pode usar um link diferente: PedAI, Anota AI, iFood, WhatsApp ou sistema próprio.</p></div>
      <div class="unit-toolbar"><div><strong id="unitCount"><?=count($units)?> unidade<?=count($units)===1?'':'s'?></strong><span>Máximo de 20 unidades.</span></div><button type="button" class="secondary" id="addUnit">+ ADICIONAR UNIDADE</button></div>
      <input type="hidden" name="unit_order" id="unitOrder" value="<?=h(implode(',',array_map(fn($u)=>(string)($u['id']??''),$units)))?>">
      <div class="unit-admin-list" id="unitList">
        <?php foreach($units as $i=>$unit): $key=clean_key((string)($unit['id']??('u-'.$i)),'u'); ?>
        <article class="unit-admin-card" data-unit-id="<?=h($key)?>" draggable="true"><div class="unit-admin-head"><button type="button" class="drag-handle unit-drag" title="Arrastar">⋮⋮</button><strong><?=h($unit['name']??'Unidade')?></strong><label class="check"><input type="checkbox" name="unit_enabled[<?=h($key)?>]" <?=!array_key_exists('enabled',$unit)||!empty($unit['enabled'])?'checked':''?>><span>Publicada</span></label><button type="button" class="unit-remove" data-remove-unit>REMOVER</button></div><div class="unit-admin-fields"><label>Nome<input name="unit_name[<?=h($key)?>]" value="<?=h($unit['name']??'')?>" maxlength="80" required></label><label>Telefone<input name="unit_phone[<?=h($key)?>]" value="<?=h($unit['phoneDisplay']??'')?>" maxlength="40"></label><label class="wide">Endereço<input name="unit_address[<?=h($key)?>]" value="<?=h($unit['address']??'')?>" maxlength="180"></label><label>Link do mapa<input type="url" name="unit_maps[<?=h($key)?>]" value="<?=h($unit['maps']??'')?>" placeholder="https://maps.google.com/..."></label><label>Link do pedido<input type="url" name="unit_order_url[<?=h($key)?>]" value="<?=h($unit['orderUrl']??'')?>" placeholder="https://..."></label></div><div class="unit-order-actions"><button type="button" data-unit-up>↑ SUBIR</button><button type="button" data-unit-down>↓ DESCER</button></div></article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel-section" id="promocoes">
      <div class="section-title"><div><span>05</span><h2>Promoções de segunda a sexta</h2></div><p>Ative só os dias desejados. O botão pode abrir o cardápio geral ou diretamente a promoção.</p></div>
      <div class="promo-admin-list">
        <?php foreach(($config['promotions']??[]) as $promo): $day=(int)($promo['weekday']??0); ?>
        <article class="promo-admin-card"><div class="promo-day"><small><?=h($promo['day']??'')?></small><strong><?=h($promo['fullDay']??'')?></strong><label class="check"><input type="checkbox" name="promo_enabled[<?=$day?>]" <?=!array_key_exists('enabled',$promo)||!empty($promo['enabled'])?'checked':''?>><span>Exibir</span></label></div><label>Nome da promoção<input name="promo_title[<?=$day?>]" value="<?=h($promo['title']??'')?>" maxlength="120"></label><label>Valor / chamada<input name="promo_price[<?=$day?>]" value="<?=h($promo['price']??'')?>" maxlength="60"></label><label>Descrição<input name="promo_description[<?=$day?>]" value="<?=h($promo['description']??'')?>" maxlength="240"></label><label class="promo-url">Link da oferta<input type="url" name="promo_url[<?=$day?>]" value="<?=h($promo['orderUrl']??'')?>" placeholder="https://..."></label><label class="check"><input type="checkbox" name="promo_direct[<?=$day?>]" <?=!empty($promo['direct'])?'checked':''?>><span>Link direto para a promoção</span></label></article>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="save-bar"><div><strong>Publicar alterações</strong><span>As mudanças aparecem no site assim que forem salvas.</span></div><button type="submit" class="primary">SALVAR E PUBLICAR</button></div>
  </form>

  <section class="panel-section security-section"><div class="section-title"><div><span>06</span><h2>Senha do administrador</h2></div><p>Altere a senha de acesso quando precisar.</p></div><form method="post" class="password-form"><input type="hidden" name="action" value="password"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>Senha atual<input type="password" name="current_password" required></label><label>Nova senha<input type="password" name="new_password" minlength="8" required></label><label>Confirmar nova senha<input type="password" name="new_confirm" minlength="8" required></label><button type="submit" class="secondary">ALTERAR SENHA</button></form></section>
</main>
<script src="admin.js"></script></body></html>
