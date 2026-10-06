<?php
/**
 * Sitemap XML (com imagens) para Google/Bing.
 * Disponível em /sitemap.php e, no Apache, também em /sitemap.xml (ver .htaccess).
 */
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$mtime = function (array $files) use ($CONFIG): string {
    $t = 0;
    foreach ($files as $f) { $t = max($t, (int)@filemtime($CONFIG['upload_dir'] . '/' . $f)); }
    if (!$t) { $t = (int)@filemtime($CONFIG['db_path']) ?: time(); }
    return date('Y-m-d', $t);
};

$cats = site_data();
$S = settings();
$allPhotos = [];
foreach ($cats as $c) { foreach ($c['projects'] as $p) { foreach ($p['photos'] as $ph) { $allPhotos[] = $ph['file']; } } }
$dbDate = date('Y-m-d', (int)@filemtime($CONFIG['db_path']) ?: time());

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc><?= $x(abs_url()) ?></loc>
    <lastmod><?= $dbDate ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
<?php foreach (array_filter([$S['sobre_image'] ?? '', $S['pf_image'] ?? '']) as $img): ?>
    <image:image><image:loc><?= $x(abs_url(upload_url($img))) ?></image:loc></image:image>
<?php endforeach; ?>
  </url>
<?php foreach ($cats as $c): foreach ($c['projects'] as $p): ?>
  <url>
    <loc><?= $x(abs_url(project_url($c, $p))) ?></loc>
    <lastmod><?= $mtime(array_column($p['photos'], 'file')) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
<?php foreach ($p['photos'] as $k => $ph): ?>
    <image:image>
      <image:loc><?= $x(abs_url(upload_url($ph['file']))) ?></image:loc>
    </image:image>
<?php endforeach; ?>
  </url>
<?php endforeach; endforeach; ?>
  <url>
    <loc><?= $x(abs_url('mapa-do-site.php')) ?></loc>
    <lastmod><?= $dbDate ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.3</priority>
  </url>
</urlset>
