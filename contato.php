<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contato');
}
csrf_check();

// Campo-armadilha contra robôs: se preenchido, finge sucesso e descarta.
if (post_str('site') !== '') {
    $_SESSION['contact_status'] = 'ok';
    redirect('index.php#contato');
}

$nome = mb_substr(post_str('nome'), 0, 120);
$email = mb_substr(post_str('email'), 0, 160);
$tel = mb_substr(post_str('telefone'), 0, 40);
$msg = mb_substr(post_str('mensagem'), 0, 5000);

// Limite simples: no máximo 5 mensagens por sessão a cada 10 minutos
$now = time();
$_SESSION['contact_times'] = array_values(array_filter($_SESSION['contact_times'] ?? [], fn($t) => $t > $now - 600));

if ($nome === '' || $msg === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || count($_SESSION['contact_times']) >= 5) {
    $_SESSION['contact_status'] = 'erro';
    redirect('index.php#contato');
}
$_SESSION['contact_times'][] = $now;

$st = db()->prepare('INSERT INTO messages(name, email, phone, message) VALUES(?, ?, ?, ?)');
$st->execute([$nome, $email, $tel, $msg]);

$to = trim((string)($CONFIG['contact_email'] ?? ''));
if ($to !== '') {
    $host = preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $headers = [
        'From: Site <no-reply@' . ($host ?: 'localhost') . '>',
        'Reply-To: ' . str_replace(["\r", "\n"], '', $email),
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $body = "Nova mensagem pelo site\n\nNome: $nome\nE-mail: $email\nTelefone: $tel\n\n$msg\n";
    @mail($to, '=?UTF-8?B?' . base64_encode('Contato pelo site — ' . $nome) . '?=', $body, implode("\r\n", $headers));
}

$_SESSION['contact_status'] = 'ok';
redirect('index.php#contato');
