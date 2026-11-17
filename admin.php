<?php require 'includes/config.php';$u=need('admin');csrf();$ok='';
// Admin landing redirect: sends administrators to their dashboard.
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??''))die('Invalid request.');
 if(($_POST['action']??'')==='delete'){q('DELETE FROM announcements WHERE id=?',[(int)$_POST['id']]);$ok='Announcement deleted.';audit_log('Announcement Deleted','Deleted announcement ID '.(int)$_POST['id']);}
 elseif(trim($_POST['title']??'')!==''){q("INSERT INTO announcements(lgu_id,title,body,source) VALUES(?,?,?,'Official')",[(int)$_POST['lgu']?:null,trim($_POST['title']),trim($_POST['body'])]);$ok='Announcement posted.';audit_log('Announcement Posted',trim($_POST['title']));}
}
$lg=q('SELECT id,name FROM lgus ORDER BY name')->fetchAll();
$rows=q('SELECT a.id,a.title,a.source,a.posted,a.url,l.name town FROM announcements a LEFT JOIN lgus l ON l.id=a.lgu_id ORDER BY a.posted DESC LIMIT 60')->fetchAll();
$title='Manage announcements';require 'includes/header.php';?>
<div class="layout" style="grid-template-columns:1fr 1.3fr"><form method="post" class="card glass"><h1>Post an announcement</h1><?php if($ok)echo'<p>'.e($ok).'</p>';?>
<input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><label>Place<select name="lgu"><option value="0">All of Pampanga (Capitol)</option><?php foreach($lg as $l)echo'<option value="'.$l['id'].'">'.e($l['name']).'</option>';?></select></label>
<label>Headline<input name="title" required></label><label>Details<textarea name="body" rows="6"></textarea></label><button class="btn">Post</button></form>
<section class="card glass"><h2>Latest announcements</h2><table><tr><th>Headline</th><th>Place</th><th>Source</th><th></th></tr>
<?php foreach($rows as $r):?><tr><td><?=e($r['title'])?></td><td><?=e($r['town']??'Pampanga')?></td><td><?=e($r['source']?:'Official')?></td><td><form method="post" onsubmit="return confirm('Delete this announcement?')"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn sm danger">Delete</button></form></td></tr><?php endforeach;?></table></section></div>
<?php require 'includes/footer.php';
