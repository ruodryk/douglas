<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    flash('Categoria não encontrada.', 'erro');
    redirect('projetos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post_str('action') === 'delete') {
        $files = $pdo->prepare('SELECT ph.file FROM photos ph JOIN projects p ON p.id = ph.project_id WHERE p.category_id = ?');
        $files->execute([$id]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) { delete_image($f); }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        flash('Categoria “' . $c['name'] . '” excluída.');
        redirect('projetos.php');
    }
    $name = post_str('name');
    if ($name === '') {
        flash('O nome não pode ficar vazio.', 'erro');
    } else {
        $pdo->prepare('UPDATE categories SET name = ?, description = ?, sort = ? WHERE id = ?')
            ->execute([$name, post_str('description'), post_int('sort'), $id]);
        flash('Categoria salva.');
    }
    redirect('categoria.php?id=' . $id);
}

$n = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE category_id = ?');
$n->execute([$id]);
$nProj = (int)$n->fetchColumn();

admin_header($c['name'], 'projetos');
?>
<p class="muted"><a href="projetos.php">← Projetos</a></p>
<div class="kicker">Categoria</div>
<h1><?= e($c['name']) ?></h1>
<p class="sub">Aparece como aba na seção Projetos do site.</p>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="field"><label for="name">Nome</label><input id="name" name="name" type="text" required value="<?= e($c['name']) ?>"></div>
  <div class="field"><label for="desc">Descrição</label><textarea id="desc" name="description" rows="3"><?= e($c['description']) ?></textarea></div>
  <div class="field"><label for="sort">Ordem das abas</label><input id="sort" name="sort" type="number" value="<?= (int)$c['sort'] ?>"></div>
  <button class="btn btn--lime" type="submit">Salvar</button>
</form>

<form method="post" class="card" data-confirm="Excluir a categoria, os <?= $nProj ?> projeto(s) dela e todas as fotos? Não dá para desfazer.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete">
  <h2>Excluir categoria</h2>
  <p class="muted">Remove também os <?= $nProj ?> projeto(s) desta categoria e as fotos deles.</p>
  <button class="btn btn--danger" type="submit">Excluir categoria</button>
</form>
<?php admin_footer(); ?>
