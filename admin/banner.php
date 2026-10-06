<?php
require __DIR__ . '/_layout.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'add') {
        $title = post_str('title');
        $file = $_FILES['image'] ?? null;
        if ($title === '' || !$file) {
            flash('Informe o título e escolha uma imagem.', 'erro');
        } else {
            try {
                $rel = store_uploaded($file, 'banner');
                $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM slides')->fetchColumn();
                $pdo->prepare('INSERT INTO slides(title, tag, short, image, sort) VALUES(?, ?, ?, ?, ?)')
                    ->execute([$title, post_str('tag'), post_str('short'), $rel, $sort]);
                flash('Slide adicionado.');
            } catch (Throwable $ex) {
                flash($ex->getMessage(), 'erro');
            }
        }
        redirect('banner.php');
    }

    if ($action === 'save') {
        $upd = $pdo->prepare('UPDATE slides SET title = ?, tag = ?, short = ?, sort = ? WHERE id = ?');
        foreach ((array)($_POST['s'] ?? []) as $sid => $row) {
            if (!is_array($row)) { continue; }
            $upd->execute([trim((string)($row['title'] ?? '')), trim((string)($row['tag'] ?? '')), trim((string)($row['short'] ?? '')), (int)($row['sort'] ?? 0), (int)$sid]);
        }
        foreach ($_FILES['replace']['name'] ?? [] as $sid => $n) {
            if (($_FILES['replace']['error'][$sid] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
            $f = ['name' => $n, 'tmp_name' => $_FILES['replace']['tmp_name'][$sid], 'error' => $_FILES['replace']['error'][$sid], 'size' => $_FILES['replace']['size'][$sid], 'type' => ''];
            try {
                $old = $pdo->prepare('SELECT image FROM slides WHERE id = ?');
                $old->execute([(int)$sid]);
                $oldFile = $old->fetchColumn();
                $rel = store_uploaded($f, 'banner');
                $pdo->prepare('UPDATE slides SET image = ? WHERE id = ?')->execute([$rel, (int)$sid]);
                delete_image($oldFile ?: null);
            } catch (Throwable $ex) {
                flash($n . ': ' . $ex->getMessage(), 'erro');
            }
        }
        flash('Banner salvo.');
        redirect('banner.php');
    }

    if ($action === 'delete') {
        $sid = post_int('id');
        $old = $pdo->prepare('SELECT image FROM slides WHERE id = ?');
        $old->execute([$sid]);
        if ($f = $old->fetchColumn()) {
            $pdo->prepare('DELETE FROM slides WHERE id = ?')->execute([$sid]);
            delete_image($f);
            flash('Slide removido.');
        }
        redirect('banner.php');
    }
}

$slides = $pdo->query('SELECT * FROM slides ORDER BY sort, id')->fetchAll();
admin_header('Banner', 'banner');
?>
<div class="kicker">Topo da página</div>
<h1>Banner</h1>
<p class="sub">Slides que giram no topo do site. O “nome curto” aparece nas abas abaixo da imagem.</p>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php if (!$slides): ?><p class="muted">Nenhum slide. Adicione abaixo.</p><?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Imagem</th><th>Título</th><th>Chamada</th><th>Nome curto</th><th>Ordem</th><th>Trocar imagem</th></tr></thead>
    <tbody>
    <?php foreach ($slides as $s): $i = (int)$s['id']; ?>
      <tr>
        <td><img class="thumb" src="../<?= e(thumb_url($s['image'])) ?>" alt=""></td>
        <td><input type="text" name="s[<?= $i ?>][title]" value="<?= e($s['title']) ?>" aria-label="Título"></td>
        <td><input type="text" name="s[<?= $i ?>][tag]" value="<?= e($s['tag']) ?>" aria-label="Chamada"></td>
        <td><input type="text" name="s[<?= $i ?>][short]" value="<?= e($s['short']) ?>" aria-label="Nome curto"></td>
        <td><input type="number" name="s[<?= $i ?>][sort]" value="<?= (int)$s['sort'] ?>" aria-label="Ordem"></td>
        <td><input type="file" name="replace[<?= $i ?>]" accept="image/jpeg,image/png,image/webp" aria-label="Nova imagem"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="actions"><button class="btn btn--lime" type="submit">Salvar banner</button></div>
  <?php endif; ?>
</form>

<?php if ($slides): ?>
<div class="card">
  <h2>Remover slide</h2>
  <div class="actions">
  <?php foreach ($slides as $s): ?>
    <form method="post" data-confirm="Remover o slide “<?= e($s['title']) ?>”?">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <button class="btn btn--danger btn--sm" type="submit">× <?= e($s['short'] ?: $s['title']) ?></button>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <h2>+ Novo slide</h2>
  <div class="grid grid-3">
    <div class="field"><label for="t">Título</label><input id="t" name="title" type="text" required placeholder="Projetos Residenciais"></div>
    <div class="field"><label for="g">Chamada</label><input id="g" name="tag" type="text" placeholder="MORAR BEM"></div>
    <div class="field"><label for="sh">Nome curto</label><input id="sh" name="short" type="text" placeholder="Residencial"></div>
  </div>
  <div class="field"><label for="im">Imagem</label><input id="im" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></div>
  <button class="btn btn--lime" type="submit">Adicionar slide</button>
</form>
<?php admin_footer(); ?>
