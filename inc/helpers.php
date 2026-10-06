<?php
declare(strict_types=1);

/** Escapa texto para HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escapa e aplica destaque: trechos entre *asteriscos* recebem a cor de destaque.
 * Quebras de linha viram <br>.
 */
function hl(?string $s, string $class = 'hl'): string
{
    $out = e($s);
    $out = preg_replace('/\*(.+?)\*/u', '<span class="' . $class . '">$1</span>', $out);
    return nl2br($out, false);
}

/** Divide um texto em parágrafos (separados por linha em branco). */
function paragraphs(?string $s): array
{
    $parts = preg_split('/\R\s*\R/u', trim((string)$s)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

/** Divide um texto em linhas não vazias. */
function lines(?string $s): array
{
    $parts = preg_split('/\R/u', trim((string)$s)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

function slugify(string $s): string
{
    $s = trim($s);
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t === false) { $t = $s; }
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    $t = trim((string)$t, '-');
    return $t !== '' ? $t : 'item';
}

function upload_url(?string $rel): string
{
    global $CONFIG;
    if (!$rel) { return ''; }
    return $CONFIG['upload_url'] . '/' . str_replace('%2F', '/', rawurlencode($rel));
}

function thumb_url(?string $rel): string
{
    if (!$rel) { return ''; }
    $dir = dirname($rel);
    $thumb = ($dir === '.' ? '' : $dir . '/') . 'thumbs/' . basename($rel);
    global $CONFIG;
    return is_file($CONFIG['upload_dir'] . '/' . $thumb) ? upload_url($thumb) : upload_url($rel);
}

/* ---------- Sessão, CSRF e autenticação ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) { return null; }
    $st = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: index.php');
        exit;
    }
    return $u;
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ---------- Configurações (textos das seções) ---------- */

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        // valores padrão para chaves novas (bancos criados por versões anteriores)
        $cache = default_settings();
        foreach (db()->query('SELECT key, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    return settings()[$key] ?? $default;
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
}

function post_str(string $k): string
{
    $v = $_POST[$k] ?? '';
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : '';
}

function post_int(string $k, int $default = 0): int
{
    $v = $_POST[$k] ?? null;
    return is_numeric($v) ? (int)$v : $default;
}
