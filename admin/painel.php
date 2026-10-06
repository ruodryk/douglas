<?php
require __DIR__ . '/_layout.php';
$user = require_login();
$pdo = db();

$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
    ['projetos.php', $count('SELECT COUNT(*) FROM projects'), 'Projetos'],
    ['projetos.php', $count('SELECT COUNT(*) FROM photos'), 'Fotos'],
    ['imprensa.php', $count('SELECT COUNT(*) FROM press'), 'Imprensa'],
    ['mensagens.php', $count('SELECT COUNT(*) FROM messages WHERE is_read = 0'), 'Mensagens novas'],
];
$recent = $pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 3')->fetchAll();

admin_header('Painel', 'painel');
?>
<div class="kicker">Olá, <?= e($user['username']) ?></div>
<h1>Painel</h1>
<p class="sub">Gerencie os projetos, os textos das seções e as mensagens do site.</p>

<div class="stats">
  <?php foreach ($stats as [$href, $n, $label]): ?>
    <a class="stat" href="<?= $href ?>"><b><?= $n ?></b><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Atalhos</h2>
    <div class="actions">
      <a class="btn btn--lime" href="projetos.php#novo-projeto">+ Novo projeto</a>
      <a class="btn btn--ghost" href="secoes.php">Editar textos</a>
    </div>
  </div>
  <div class="card">
    <h2>Últimas mensagens</h2>
    <?php if (!$recent): ?>
      <p class="muted">Nenhuma mensagem ainda.</p>
    <?php else: foreach ($recent as $m): ?>
      <p style="margin:0 0 10px"><strong><?= e($m['name']) ?></strong> <span class="muted">— <?= e(date('d/m/Y H:i', strtotime($m['created_at'] . ' UTC'))) ?></span><br>
      <span class="muted"><?= e(mb_strimwidth($m['message'], 0, 110, '…')) ?></span></p>
    <?php endforeach; ?>
      <a href="mensagens.php">Ver todas →</a>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
