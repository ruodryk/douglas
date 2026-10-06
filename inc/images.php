<?php
declare(strict_types=1);

const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

/**
 * Lê uma imagem, redimensiona (mantendo proporção), corrige a orientação da câmera
 * e grava em JPEG (ou PNG, se tiver transparência) + miniatura em /thumbs.
 * Retorna o caminho relativo dentro de uploads/.
 */
function store_image(string $srcPath, string $relDir, string $baseName): string
{
    global $CONFIG;
    $info = @getimagesize($srcPath);
    if (!$info || !isset(ALLOWED_IMAGE_TYPES[$info[2]])) {
        throw new RuntimeException('Arquivo não é uma imagem válida (use JPG, PNG, WEBP ou GIF).');
    }
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($srcPath),
        IMAGETYPE_GIF => @imagecreatefromgif($srcPath),
    };
    if (!$img) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($srcPath);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) { $img = imagerotate($img, 180, 0); }
        elseif ($o === 6) { $img = imagerotate($img, -90, 0); }
        elseif ($o === 8) { $img = imagerotate($img, 90, 0); }
    }
    $keepPng = $info[2] === IMAGETYPE_PNG;
    $ext = $keepPng ? 'png' : 'jpg';

    $relDir = trim($relDir, '/');
    $absDir = $CONFIG['upload_dir'] . '/' . $relDir;
    if (!is_dir($absDir . '/thumbs') && !mkdir($absDir . '/thumbs', 0775, true) && !is_dir($absDir . '/thumbs')) {
        throw new RuntimeException('Não foi possível criar a pasta de uploads.');
    }
    $base = slugify($baseName);
    $name = $base . '.' . $ext;
    $i = 2;
    while (is_file($absDir . '/' . $name)) {
        $name = $base . '-' . $i++ . '.' . $ext;
    }

    write_resized($img, $absDir . '/' . $name, (int)$CONFIG['image_max'], $keepPng);
    write_resized($img, $absDir . '/thumbs/' . $name, (int)$CONFIG['thumb_max'], $keepPng);

    return ($relDir !== '' ? $relDir . '/' : '') . $name;
}

function write_resized(GdImage $img, string $dest, int $max, bool $png): void
{
    global $CONFIG;
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    if ($png) {
        imagealphablending($out, false);
        imagesavealpha($out, true);
    } else {
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    }
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $png ? imagepng($out, $dest, 7) : imagejpeg($out, $dest, (int)$CONFIG['jpeg_quality']);
}

/** Processa um arquivo enviado por formulário ($_FILES[...] de um único arquivo). */
function store_uploaded(array $file, string $relDir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ($file['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo grande demais para o servidor.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            default => 'Falha no envio do arquivo.',
        };
        throw new RuntimeException($msg);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Envio inválido.');
    }
    $base = pathinfo((string)$file['name'], PATHINFO_FILENAME);
    return store_image($file['tmp_name'], $relDir, $base);
}

/** Normaliza $_FILES['campo'] com múltiplos arquivos em uma lista. */
function files_list(string $field): array
{
    if (empty($_FILES[$field])) { return []; }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) { return [$f]; }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { continue; }
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function delete_image(?string $rel): void
{
    global $CONFIG;
    if (!$rel || str_contains($rel, '..')) { return; }
    $abs = $CONFIG['upload_dir'] . '/' . $rel;
    $thumb = dirname($abs) . '/thumbs/' . basename($abs);
    if (is_file($abs)) { @unlink($abs); }
    if (is_file($thumb)) { @unlink($thumb); }
}
