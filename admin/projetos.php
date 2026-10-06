<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

function unique_project_slug(PDO $pdo, int $catId, string $name, int $exceptId = 0): string
{
    $base = slugify($name);
    $slug = $base;
    $i = 2;
    $st = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE category_id = ? AND slug = ? AND id <> ?');
    while (true) {
        $st->execute([$catId, $slug, $exceptId]);
        if ((int)$st->fetchColumn() === 0) { return $slug; }
        $slug = $base . '-' . $i++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'add_category') {
        $name = post_str('name');
        if ($name === '') {
            flash('Informe o nome da categoria.', 'erro');
        } else {
            $slug = slugify($name);
            $exists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ?');
            $i = 2; $base = $slug;
            while (true) { $exists->execute([$slug]); if (!(int)$exists->fetchColumn()) break; $slug = $base . '-' . $i++; }
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM categories')->fetchColumn();
            $pdo->prepare('INSERT INTO categories(name, slug, description, sort) VALUES(?, ?, ?, ?)')
                ->execute([$name, $slug, post_str('description'), $sort]);
            flash('Categoria “' . $name . '” criada.');
        }
        redirect('projetos.php');
    }

    if ($action === 'add_project') {
        $catId = post_int('category_id');
        $cat = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $cat->execute([$catId]);
        $cat = $cat->fetch();
        $name = post_str('name');
        if (!$cat || $name === '') {
            flash('Escolha a categoria e informe o nome do projeto.', 'erro');
            redirect('projetos.php#novo-projeto');
        }
        $slug = unique_project_slug($pdo, $catId, $name);
        $sortSt = $pdo->prepare('SELECT COALESCE(MAX(sort), 0) + 1 FROM projects WHERE category_id = ?');
        $sortSt->execute([$catId]);
        $pdo->prepare('INSERT INTO projects(category_id, name, slug, description, published, sort) VALUES(?, ?, ?, ?, ?, ?)')
            ->execute([$catId, $name, $slug, post_str('description'), isset($_POST['published']) ? 1 : 0, (int)$sortSt->fetchColumn()]);
        $pid = (int)$pdo->lastInsertId();

        $ok = 0; $fail = [];
        $ins = $pdo->prepare('INSERT INTO photos(project_id, file, sort) VALUES(?, ?, ?)');
        foreach (files_list('photos') as $n => $f) {
            try {
                $rel = store_uploaded($f, 'projetos/' . $cat['slug'] . '/' . $slug);
                $ins->execute([$pid, $rel, $n + 1]);
                $ok++;
            } catch (Throwable $ex) {
                $fail[] = $f['name'] . ': ' . $ex->getMessage();
            }
        }
        flash('Projeto “' . $name . '” criado' . ($ok ? " com $ok foto(s)." : '.'));
        if ($fail) { flash('Algumas fotos não foram enviadas — ' . implode(' | ', $fail), 'erro'); }
        redirect('projeto.php?id=' . $pid);
    }
}

$cats = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
$projects = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM photos WHERE project_id = p.id) AS n FROM projects p ORDER BY sort, id')->fetchAll();
$covers = [];
foreach ($pdo->query('SELECT project_id, file FROM photos ORDER BY sort, id') as $r) {
    if (count($covers[$r['project_id']] ?? []) < 3) { $covers[$r['project_id']][] = $r['file']; }
}
$byCat = [];
foreach ($projects as $p) { $byCat[$p['category_id']][] = $p; }

admin_header('Projetos', 'projetos');
?>
<div class="kicker">Galerias</div>
<h1>Projetos</h1>
<p class="sub">Organização igual à do site: <strong>categoria → projeto → fotos</strong>. Cada projeto aparece como uma galeria.</p>

<?php foreach ($cats as $c): ?>
<section class="cat-block">
  <header>
    <h2><?= e($c['name']) ?> <span class="muted" style="color:#ccc;font-weight:600;text-transform:none">· <?= count($byCat[$c['id']] ?? []) ?> projeto(s)</span></h2>
    <a class="btn btn--ghost btn--sm" href="categoria.php?id=<?= (int)$c['id'] ?>">Editar categoria</a>
  </header>
  <?php if (empty($byCat[$c['id']])): ?>
    <div class="proj-row"><span class="muted">Nenhum projeto nesta categoria. Categorias sem projetos publicados não aparecem no site.</span></div>
  <?php else: foreach ($byCat[$c['id']] as $p): ?>
    <div class="proj-row">
      <div class="covers">
        <?php foreach ($covers[$p['id']] ?? [] as $cv): ?><img class="thumb" src="../<?= e(thumb_url($cv)) ?>" alt=""><?php endforeach; ?>
      </div>
      <div class="name"><?= e($p['name']) ?></div>
      <span class="meta"><?= (int)$p['n'] ?> fotos · ordem <?= (int)$p['sort'] ?></span>
      <span class="pill <?= $p['published'] ? 'pill--on' : '' ?>"><?= $p['published'] ? 'Publicado' : 'Oculto' ?></span>
      <a class="btn btn--sm" href="projeto.php?id=<?= (int)$p['id'] ?>">Editar / fotos</a>
    </div>
  <?php endforeach; endif; ?>
</section>
<?php endforeach; ?>

<div class="grid grid-2">
  <form class="card" method="post" enctype="multipart/form-data" id="novo-projeto">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_project">
    <h2>+ Novo projeto</h2>
    <?php if (!$cats): ?><p class="muted">Crie uma categoria primeiro.</p><?php else: ?>
    <div class="field"><label for="np-cat">Categoria</label>
      <select id="np-cat" name="category_id" required>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="np-name">Nome do projeto</label><input id="np-name" name="name" type="text" required placeholder="Ex.: Projeto 3 ou Residência Jardim Europa"></div>
    <div class="field"><label for="np-desc">Descrição (opcional)</label><textarea id="np-desc" name="description" rows="3"></textarea></div>
    <div class="field dropzone" data-dropzone>
      <label for="np-photos">Fotos</label>
      <input id="np-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-preview>
      <p class="help">Selecione várias de uma vez. As imagens são redimensionadas automaticamente.</p>
      <div class="preview" data-preview-out></div>
    </div>
    <div class="field"><label class="check"><input type="checkbox" name="published" value="1" checked> Publicar no site</label></div>
    <button class="btn btn--lime" type="submit">Criar projeto</button>
    <?php endif; ?>
  </form>

  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_category">
    <h2>+ Nova categoria</h2>
    <div class="field"><label for="nc-name">Nome</label><input id="nc-name" name="name" type="text" required placeholder="Ex.: Industrial"></div>
    <div class="field"><label for="nc-desc">Descrição</label><textarea id="nc-desc" name="description" rows="3"></textarea></div>
    <button class="btn" type="submit">Criar categoria</button>
  </form>
</div>
<?php admin_footer(); ?>
