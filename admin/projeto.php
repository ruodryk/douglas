<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT p.*, c.slug AS cat_slug, c.name AS cat_name FROM projects p JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    flash('Projeto não encontrado.', 'erro');
    redirect('projetos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'save') {
        $name = post_str('name');
        if ($name === '') {
            flash('O nome do projeto não pode ficar vazio.', 'erro');
            redirect('projeto.php?id=' . $id);
        }
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE projects SET name = ?, description = ?, category_id = ?, published = ?, sort = ? WHERE id = ?')
            ->execute([$name, post_str('description'), post_int('category_id', (int)$p['category_id']), isset($_POST['published']) ? 1 : 0, post_int('sort'), $id]);

        $captions = $_POST['caption'] ?? [];
        $sorts = $_POST['psort'] ?? [];
        $delete = array_map('intval', (array)($_POST['delete'] ?? []));
        $upd = $pdo->prepare('UPDATE photos SET caption = ?, sort = ? WHERE id = ? AND project_id = ?');
        foreach ((array)$captions as $pid => $cap) {
            $upd->execute([trim((string)$cap), (int)($sorts[$pid] ?? 0), (int)$pid, $id]);
        }
        if ($delete) {
            $get = $pdo->prepare('SELECT file FROM photos WHERE id = ? AND project_id = ?');
            $del = $pdo->prepare('DELETE FROM photos WHERE id = ? AND project_id = ?');
            foreach ($delete as $pid) {
                $get->execute([$pid, $id]);
                if ($f = $get->fetchColumn()) {
                    $del->execute([$pid, $id]);
                    delete_image($f);
                }
            }
        }
        $pdo->commit();
        flash('Projeto salvo.' . ($delete ? ' ' . count($delete) . ' foto(s) removida(s).' : ''));
        redirect('projeto.php?id=' . $id);
    }

    if ($action === 'upload') {
        $next = $pdo->prepare('SELECT COALESCE(MAX(sort), 0) FROM photos WHERE project_id = ?');
        $next->execute([$id]);
        $sort = (int)$next->fetchColumn();
        $ins = $pdo->prepare('INSERT INTO photos(project_id, file, sort) VALUES(?, ?, ?)');
        $ok = 0; $fail = [];
        foreach (files_list('photos') as $f) {
            try {
                $rel = store_uploaded($f, 'projetos/' . $p['cat_slug'] . '/' . $p['slug']);
                $ins->execute([$id, $rel, ++$sort]);
                $ok++;
            } catch (Throwable $ex) {
                $fail[] = $f['name'] . ': ' . $ex->getMessage();
            }
        }
        if ($ok) { flash("$ok foto(s) adicionada(s)."); }
        if ($fail) { flash('Falhas: ' . implode(' | ', $fail), 'erro'); }
        if (!$ok && !$fail) { flash('Selecione ao menos uma foto.', 'erro'); }
        redirect('projeto.php?id=' . $id . '#fotos');
    }

    if ($action === 'delete_project') {
        $files = $pdo->prepare('SELECT file FROM photos WHERE project_id = ?');
        $files->execute([$id]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) { delete_image($f); }
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
        flash('Projeto “' . $p['name'] . '” excluído.');
        redirect('projetos.php');
    }
}

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
$ph = $pdo->prepare('SELECT * FROM photos WHERE project_id = ? ORDER BY sort, id');
$ph->execute([$id]);
$photos = $ph->fetchAll();

admin_header($p['name'], 'projetos');
?>
<p class="muted"><a href="projetos.php">← Projetos</a> / <?= e($p['cat_name']) ?></p>
<div class="kicker"><?= e($p['cat_name']) ?></div>
<h1><?= e($p['name']) ?></h1>
<p class="sub">A primeira foto (menor número de ordem) aparece em destaque na galeria do site.
  <?php if ($p['published']): ?><a href="../projeto.php?c=<?= e(rawurlencode($p['cat_slug'])) ?>&amp;p=<?= e(rawurlencode($p['slug'])) ?>" target="_blank" rel="noopener">Ver página do projeto ↗</a><?php endif; ?></p>
<p class="help" style="margin-top:-18px;margin-bottom:24px">Dica de SEO: use nomes descritivos (ex.: “Residência Jardim das Flores”) e escreva uma descrição de 1 a 3 frases com o tipo de projeto e a cidade.</p>

<form method="post" enctype="multipart/form-data" class="card dropzone" data-dropzone id="enviar">
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <h2>Adicionar fotos</h2>
  <input name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-preview aria-label="Escolher fotos">
  <p class="help">Arraste as fotos para cá ou clique para escolher. Várias de uma vez.</p>
  <div class="preview" data-preview-out></div>
  <div class="actions"><button class="btn btn--lime" type="submit">Enviar fotos</button></div>
</form>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="card">
    <h2>Dados do projeto</h2>
    <div class="grid grid-2">
      <div class="field"><label for="name">Nome</label><input id="name" name="name" type="text" required value="<?= e($p['name']) ?>"></div>
      <div class="field"><label for="cat">Categoria</label>
        <select id="cat" name="category_id">
          <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$p['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="field"><label for="desc">Descrição (aparece na página do projeto e no Google)</label><textarea id="desc" name="description" rows="3"><?= e($p['description']) ?></textarea></div>
    <div class="actions">
      <div class="field" style="margin:0"><label for="sort">Ordem</label><input id="sort" name="sort" type="number" value="<?= (int)$p['sort'] ?>"></div>
      <label class="check" style="margin:22px 0 0"><input type="checkbox" name="published" value="1" <?= $p['published'] ? 'checked' : '' ?>> Publicado no site</label>
    </div>
  </div>

  <div class="card" id="fotos">
    <h2>Fotos (<?= count($photos) ?>)</h2>
    <?php if (!$photos): ?>
      <p class="muted">Nenhuma foto ainda. Use “Adicionar fotos” acima.</p>
    <?php else: ?>
    <div class="photo-grid">
      <?php foreach ($photos as $f): ?>
        <div class="photo-item">
          <a href="../<?= e(upload_url($f['file'])) ?>" target="_blank" rel="noopener"><img src="../<?= e(thumb_url($f['file'])) ?>" alt="" loading="lazy"></a>
          <div class="body">
            <input type="text" name="caption[<?= (int)$f['id'] ?>]" value="<?= e($f['caption']) ?>" placeholder="Legenda (opcional)" aria-label="Legenda">
            <div class="row">
              <input type="number" name="psort[<?= (int)$f['id'] ?>]" value="<?= (int)$f['sort'] ?>" aria-label="Ordem">
              <label class="del"><input type="checkbox" name="delete[]" value="<?= (int)$f['id'] ?>"> Excluir</label>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="actions"><button class="btn btn--lime" type="submit">Salvar alterações</button></div>
</form>

<form method="post" class="card" style="margin-top:32px" data-confirm="Excluir este projeto e todas as suas fotos? Não dá para desfazer.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_project">
  <h2>Excluir projeto</h2>
  <p class="muted">Remove o projeto e apaga as <?= count($photos) ?> fotos do servidor.</p>
  <button class="btn btn--danger" type="submit">Excluir projeto</button>
</form>
<?php admin_footer(); ?>
