<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'upload') {
        $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort), 0) FROM press')->fetchColumn();
        $ins = $pdo->prepare('INSERT INTO press(file, caption, sort) VALUES(?, ?, ?)');
        $ok = 0;
        foreach (files_list('images') as $f) {
            try {
                $rel = store_uploaded($f, 'imprensa');
                $ins->execute([$rel, pathinfo((string)$f['name'], PATHINFO_FILENAME), ++$sort]);
                $ok++;
            } catch (Throwable $ex) {
                flash($f['name'] . ': ' . $ex->getMessage(), 'erro');
            }
        }
        if ($ok) { flash("$ok imagem(ns) adicionada(s). Ajuste as legendas abaixo."); }
        redirect('imprensa.php');
    }

    if ($action === 'save') {
        $upd = $pdo->prepare('UPDATE press SET caption = ?, sort = ? WHERE id = ?');
        foreach ((array)($_POST['caption'] ?? []) as $id => $cap) {
            $upd->execute([trim((string)$cap), (int)($_POST['psort'][$id] ?? 0), (int)$id]);
        }
        $get = $pdo->prepare('SELECT file FROM press WHERE id = ?');
        $del = $pdo->prepare('DELETE FROM press WHERE id = ?');
        foreach (array_map('intval', (array)($_POST['delete'] ?? [])) as $id) {
            $get->execute([$id]);
            if ($f = $get->fetchColumn()) { $del->execute([$id]); delete_image($f); }
        }
        flash('Galeria de imprensa salva.');
        redirect('imprensa.php');
    }
}

$items = $pdo->query('SELECT * FROM press ORDER BY sort, id')->fetchAll();
admin_header('Imprensa', 'imprensa');
?>
<div class="kicker">Seção Porto Feliz</div>
<h1>Porto Feliz na imprensa</h1>
<p class="sub">Recortes de jornal e fotos exibidos na galeria da seção Porto Feliz.</p>

<form method="post" enctype="multipart/form-data" class="card dropzone" data-dropzone>
  <?= csrf_field() ?><input type="hidden" name="action" value="upload">
  <h2>Adicionar imagens</h2>
  <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-preview aria-label="Escolher imagens">
  <div class="preview" data-preview-out></div>
  <div class="actions"><button class="btn btn--lime" type="submit">Enviar</button></div>
</form>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <h2>Imagens (<?= count($items) ?>)</h2>
  <?php if (!$items): ?><p class="muted">Nenhuma imagem.</p><?php else: ?>
  <div class="photo-grid">
    <?php foreach ($items as $it): ?>
      <div class="photo-item">
        <a href="../<?= e(upload_url($it['file'])) ?>" target="_blank" rel="noopener"><img src="../<?= e(thumb_url($it['file'])) ?>" alt="" loading="lazy" style="object-position:top"></a>
        <div class="body">
          <input type="text" name="caption[<?= (int)$it['id'] ?>]" value="<?= e($it['caption']) ?>" placeholder="Legenda" aria-label="Legenda">
          <div class="row">
            <input type="number" name="psort[<?= (int)$it['id'] ?>]" value="<?= (int)$it['sort'] ?>" aria-label="Ordem">
            <label class="del"><input type="checkbox" name="delete[]" value="<?= (int)$it['id'] ?>"> Excluir</label>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="actions"><button class="btn btn--lime" type="submit">Salvar</button></div>
  <?php endif; ?>
</form>
<?php admin_footer(); ?>
