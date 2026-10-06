<?php
require __DIR__ . '/_layout.php';
$me = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'password') {
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$me['id']]);
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), (string)$st->fetchColumn())) {
            flash('A senha atual está incorreta.', 'erro');
        } elseif (mb_strlen($new) < 8) {
            flash('A nova senha precisa ter pelo menos 8 caracteres.', 'erro');
        } elseif ($new !== (string)($_POST['new2'] ?? '')) {
            flash('As novas senhas não conferem.', 'erro');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('Senha alterada.');
        }
        redirect('conta.php');
    }

    if ($action === 'add_user') {
        $u = post_str('username');
        $pw = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._@-]{3,60}$/', $u) || mb_strlen($pw) < 8) {
            flash('Usuário inválido ou senha com menos de 8 caracteres.', 'erro');
        } else {
            try {
                $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?, ?)')->execute([$u, password_hash($pw, PASSWORD_DEFAULT)]);
                flash('Usuário “' . $u . '” criado.');
            } catch (PDOException) {
                flash('Esse usuário já existe.', 'erro');
            }
        }
        redirect('conta.php');
    }

    if ($action === 'delete_user') {
        $id = post_int('id');
        if ($id === (int)$me['id']) {
            flash('Você não pode excluir o próprio usuário.', 'erro');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('Usuário excluído.');
        }
        redirect('conta.php');
    }
}

$users = $pdo->query('SELECT id, username, created_at FROM users ORDER BY id')->fetchAll();
admin_header('Minha conta', 'conta');
?>
<div class="kicker">Acesso</div>
<h1>Minha conta</h1>
<p class="sub">Conectado como <strong><?= e($me['username']) ?></strong>.</p>

<div class="grid grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Trocar senha</h2>
    <div class="field"><label for="c">Senha atual</label><input id="c" name="current" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="n">Nova senha</label><input id="n" name="new" type="password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label for="n2">Repita a nova senha</label><input id="n2" name="new2" type="password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn--lime" type="submit">Salvar senha</button>
  </form>

  <div class="card">
    <h2>Usuários do painel</h2>
    <table>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['username']) ?></strong><?= (int)$u['id'] === (int)$me['id'] ? ' <span class="pill">você</span>' : '' ?></td>
          <td style="text-align:right">
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post" data-confirm="Excluir o usuário <?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn--danger btn--sm" type="submit">Excluir</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <form method="post" style="margin-top:20px">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_user">
      <h2>+ Novo usuário</h2>
      <div class="field"><label for="nu">Usuário</label><input id="nu" name="username" type="text" required autocomplete="off"></div>
      <div class="field"><label for="np">Senha (mín. 8)</label><input id="np" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <button class="btn" type="submit">Criar usuário</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
