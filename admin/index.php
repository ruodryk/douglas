<?php
require __DIR__ . '/_layout.php';

if (current_user()) {
    redirect('painel.php');
}

$pdo = db();
$setup = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = post_str('usuario');
    $pass = (string)($_POST['senha'] ?? '');

    if ($setup) {
        // Primeiro acesso: cria o administrador
        $pass2 = (string)($_POST['senha2'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._@-]{3,60}$/', $user)) {
            $error = 'Usuário deve ter de 3 a 60 caracteres (letras, números, ponto, hífen, @).';
        } elseif (mb_strlen($pass) < 8) {
            $error = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif ($pass !== $pass2) {
            $error = 'As senhas não conferem.';
        } else {
            $st = $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?, ?)');
            $st->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$pdo->lastInsertId();
            flash('Administrador criado. Bem-vindo ao painel!');
            redirect('painel.php');
        }
    } else {
        // Limite de tentativas: 8 a cada 15 minutos por IP
        $ip = client_ip();
        $pdo->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - 900]);
        $c = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
        $c->execute([$ip]);
        if ((int)$c->fetchColumn() >= 8) {
            $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
        } else {
            $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $st->execute([$user]);
            $u = $st->fetch();
            if ($u && password_verify($pass, $u['password_hash'])) {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
                }
                $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                redirect('painel.php');
            }
            $pdo->prepare('INSERT INTO login_attempts(ip, ts) VALUES(?, ?)')->execute([$ip, time()]);
            $error = 'Usuário ou senha incorretos.';
        }
    }
}

admin_header($setup ? 'Primeiro acesso' : 'Entrar', '', false);
?>
<div class="login-wrap">
  <form class="login" method="post" autocomplete="on">
    <div class="logo"><img src="../assets/img/logo-cabecalho.png" alt="Douglas Souza — arquiteto &amp; urbanista"></div>
    <?= csrf_field() ?>
    <?php if ($setup): ?>
      <div class="kicker">Primeiro acesso</div>
      <h1>Criar administrador</h1>
      <p class="sub">Defina o usuário e a senha que serão usados para entrar no painel.</p>
    <?php else: ?>
      <div class="kicker">Painel administrativo</div>
      <h1>Entrar</h1>
      <p class="sub">Acesso restrito à equipe do escritório.</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash--erro" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label for="u">Usuário</label><input id="u" name="usuario" type="text" required autocomplete="username" value="<?= e(post_str('usuario')) ?>"></div>
    <div class="field"><label for="p">Senha</label><input id="p" name="senha" type="password" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" <?= $setup ? 'minlength="8"' : '' ?>></div>
    <?php if ($setup): ?>
      <div class="field"><label for="p2">Repita a senha</label><input id="p2" name="senha2" type="password" required autocomplete="new-password" minlength="8"></div>
    <?php endif; ?>
    <button type="submit" class="btn btn--lime"><?= $setup ? 'Criar e entrar' : 'Entrar' ?></button>
    <p class="muted" style="margin-top:16px"><a href="../index.php">← Voltar ao site</a></p>
  </form>
</div>
<?php admin_footer(); ?>
