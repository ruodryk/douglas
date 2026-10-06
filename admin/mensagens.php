<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = post_int('id');
    $action = post_str('action');
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]);
        flash('Mensagem excluída.');
    } elseif ($action === 'read_all') {
        $pdo->exec('UPDATE messages SET is_read = 1');
        flash('Todas marcadas como lidas.');
    }
    redirect('mensagens.php');
}

$msgs = $pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 300')->fetchAll();
admin_header('Mensagens', 'mensagens');
?>
<div class="kicker">Formulário de contato</div>
<h1>Mensagens</h1>
<p class="sub">Tudo o que é enviado pelo formulário do site fica guardado aqui<?= $CONFIG['contact_email'] ? ' e também é enviado para ' . e($CONFIG['contact_email']) : '' ?>.</p>

<?php if ($msgs): ?>
<form method="post" class="actions" style="margin-bottom:16px"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn--ghost btn--sm" type="submit">Marcar todas como lidas</button></form>
<?php endif; ?>

<?php if (!$msgs): ?>
  <div class="card"><p class="muted">Nenhuma mensagem recebida ainda.</p></div>
<?php endif; ?>
<?php foreach ($msgs as $m): ?>
  <article class="card msg <?= $m['is_read'] ? '' : 'unread' ?>">
    <div class="head">
      <div>
        <strong style="font-size:18px"><?= e($m['name']) ?></strong><?php if (!$m['is_read']): ?> <span class="pill pill--on">Nova</span><?php endif; ?><br>
        <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?php if ($m['phone']): ?> · <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $m['phone'])) ?>"><?= e($m['phone']) ?></a><?php endif; ?>
      </div>
      <span class="muted"><?= e(date('d/m/Y H:i', strtotime($m['created_at'] . ' UTC'))) ?></span>
    </div>
    <div class="body"><?= e($m['message']) ?></div>
    <div class="actions">
      <a class="btn btn--sm" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: contato pelo site')) ?>">Responder</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn--ghost btn--sm" type="submit"><?= $m['is_read'] ? 'Marcar como nova' : 'Marcar como lida' ?></button></form>
      <form method="post" data-confirm="Excluir esta mensagem?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn--danger btn--sm" type="submit">Excluir</button></form>
    </div>
  </article>
<?php endforeach; ?>
<?php admin_footer(); ?>
