<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

function admin_header(string $title, string $active = '', bool $nav = true): void
{
    $user = current_user();
    $unread = 0;
    if ($user) {
        $unread = (int)db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
    }
    $items = [
        'painel' => ['painel.php', 'Painel'],
        'projetos' => ['projetos.php', 'Projetos'],
        'secoes' => ['secoes.php', 'Seções'],
        'imprensa' => ['imprensa.php', 'Imprensa'],
        'mensagens' => ['mensagens.php', 'Mensagens'],
        'conta' => ['conta.php', 'Minha conta'],
    ];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — Painel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css?v=1">
</head>
<body>
<?php if ($nav && $user): ?>
<header class="topbar">
  <a class="brand" href="painel.php"><img src="../assets/img/logo-cabecalho.png" alt=""><span>Painel</span></a>
  <nav aria-label="Painel">
    <?php foreach ($items as $key => [$href, $label]): ?>
      <a href="<?= $href ?>" class="<?= $key === $active ? 'on' : '' ?>"><?= e($label) ?><?php if ($key === 'mensagens' && $unread): ?> <span class="badge"><?= $unread ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="user">
    <a href="../index.php" target="_blank" rel="noopener">Ver site ↗</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="link">Sair</button></form>
  </div>
</header>
<?php endif; ?>
<main class="main<?= ($nav && $user) ? '' : ' main--bare' ?>">
<?php foreach (take_flashes() as $f): ?>
  <div class="flash flash--<?= e($f['type']) ?>" role="<?= $f['type'] === 'erro' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
</main>
<script src="admin.js?v=1" defer></script>
</body>
</html>
<?php
}
