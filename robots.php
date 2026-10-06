<?php
/** robots.txt dinâmico (no Apache também responde em /robots.txt — ver .htaccess). */
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /contato.php
Disallow: /data/
Disallow: /inc/
Disallow: /seed/

Sitemap: <?= abs_url('sitemap.php') ?>

