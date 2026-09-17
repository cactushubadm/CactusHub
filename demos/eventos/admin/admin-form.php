<?php require __DIR__.'/../lib/bootstrap.php';require_admin();$id=(int)($_GET['id']??$_POST['id']??0);$row=null;$error='';
if($id){$st=db()->prepare('SELECT * FROM admins WHERE id=?');$st->execute([$id]);$row=$st->fetch();if(!$row){http_response_code(404);exit('Usuário não encontrado.');}}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$name=trim($_POST['name']??'');$email=trim($_POST['email']??'');$password=(string)($_POST['password']??'');$active=isset($_POST['active'])?1:0;
 if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Informe nome e e-mail válido.';
 elseif(!$id&&strlen($password)<8)$error='A senha inicial deve ter pelo menos 8 caracteres.';
 elseif($id===(int)(admin_user()['id']??0)&&!$active)$error='Você não pode desativar seu próprio usuário.';
 else{try{
   if($id){db()->prepare('UPDATE admins SET name=?,email=?,active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$name,$email,$active,$id]);if($password!==''){if(strlen($password)<8)throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');db()->prepare('UPDATE admins SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);}}
   else{db()->prepare('INSERT INTO admins(name,email,password_hash,active,role) VALUES(?,?,?,?,"admin")')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$active]);$id=(int)db()->lastInsertId();}
   audit_log('admin.saved','admin',$id);flash('success','Usuário salvo.');redirect('admins.php');
 }catch(Throwable $e){$error='Erro ao salvar: '.$e->getMessage();}}
}
$pageTitle=$id?'Editar usuário':'Novo usuário';require '_header.php';?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><div class="admin-card narrow-card"><div class="card-head"><div><h2><?=e($pageTitle)?></h2><p>Senha mínima de 8 caracteres.</p></div></div><form method="post" class="form-grid one"><?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>"><label>Nome<input name="name" required value="<?=e($row['name']??'')?>"></label><label>E-mail<input type="email" name="email" required value="<?=e($row['email']??'')?>"></label><label><?=$id?'Nova senha (opcional)':'Senha'?><input type="password" name="password" <?=$id?'':'required'?> minlength="8"></label><label class="check-line"><input type="checkbox" name="active" <?=($row['active']??1)?'checked':''?>> Usuário ativo</label><div><button class="btn btn-primary">Salvar</button> <a class="btn btn-ghost dark-outline" href="admins.php">Cancelar</a></div></form></div><?php require '_footer.php';?>
