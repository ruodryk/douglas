<?php
declare(strict_types=1);
/**
 * Partes compartilhadas do site público: dados, SEO (meta tags, Open Graph,
 * dados estruturados), cabeçalho e rodapé com mapa do site.
 */

const ICON_ARROW = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
const ICON_CHEV_L = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';
const ICON_CHEV_R = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';

/** Categoria > projetos publicados > fotos (só categorias com projetos). */
function site_data(): array
{
    static $data = null;
    if ($data !== null) { return $data; }
    $pdo = db();
    $cats = $pdo->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
    $projects = $pdo->query('SELECT * FROM projects WHERE published = 1 ORDER BY sort, id')->fetchAll();
    $byProject = [];
    foreach ($pdo->query('SELECT * FROM photos ORDER BY sort, id') as $ph) { $byProject[$ph['project_id']][] = $ph; }
    $byCat = [];
    foreach ($projects as $p) {
        $p['photos'] = $byProject[$p['id']] ?? [];
        $byCat[$p['category_id']][] = $p;
    }
    $out = [];
    foreach ($cats as $c) {
        $c['projects'] = $byCat[$c['id']] ?? [];
        if (!$c['projects']) { continue; }
        $c['photo_count'] = array_sum(array_map(fn($p) => count($p['photos']), $c['projects']));
        $c['cover'] = null;
        foreach ($c['projects'] as $p) { if ($p['photos']) { $c['cover'] = $p['photos'][0]['file']; break; } }
        $out[] = $c;
    }
    return $data = $out;
}

/* ---------- URLs ---------- */

/** URL absoluta (para canonical, Open Graph e sitemap). Usa config base_url se definido. */
function abs_url(string $path = ''): string
{
    global $CONFIG;
    $base = rtrim((string)($CONFIG['base_url'] ?? ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        $dir = rtrim($dir === '.' ? '' : $dir, '/');
        $base = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

function project_url(array $cat, array $proj): string
{
    return 'projeto.php?c=' . rawurlencode($cat['slug']) . '&p=' . rawurlencode($proj['slug']);
}

/** Link para uma âncora da página inicial (relativo quando já está nela). */
function home_link(string $anchor = ''): string
{
    global $IS_HOME;
    $a = $anchor !== '' ? '#' . $anchor : '';
    return !empty($IS_HOME) ? ($a ?: '#home') : 'index.php' . $a;
}

/** Largura/altura reais da imagem (evita “pulos” de layout e ajuda no SEO de imagens). */
function img_dims(?string $rel, bool $thumb = false): string
{
    global $CONFIG;
    if (!$rel) { return ''; }
    $file = $rel;
    if ($thumb) {
        $t = (dirname($rel) === '.' ? '' : dirname($rel) . '/') . 'thumbs/' . basename($rel);
        if (is_file($CONFIG['upload_dir'] . '/' . $t)) { $file = $t; }
    }
    $info = @getimagesize($CONFIG['upload_dir'] . '/' . $file);
    return $info ? ' width="' . (int)$info[0] . '" height="' . (int)$info[1] . '"' : '';
}

function photo_alt(array $cat, array $proj, array $ph, int $n): string
{
    if (!empty($ph['caption'])) { return $ph['caption']; }
    $city = setting('seo_city', 'Porto Feliz') . '/' . setting('seo_region', 'SP');
    return "Projeto {$cat['name']} — {$proj['name']}, foto {$n} — Douglas Souza Arquiteto, {$city}";
}

function plain(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', str_replace('*', '', $s)));
}

function cut(string $s, int $max = 160): string
{
    $s = plain($s);
    return mb_strlen($s) <= $max ? $s : rtrim(mb_substr($s, 0, $max - 1), " ,.;:—-") . '…';
}

/* ---------- Dados estruturados (schema.org) ---------- */

function business_schema(): array
{
    $S = settings();
    $tel = array_values(array_filter([$S['phone1'] ?? '', $S['phone2'] ?? '']));
    $areas = array_values(array_filter(array_map('trim', explode(',', $S['seo_area'] ?? ''))));
    $schema = [
        '@type' => ['LocalBusiness', 'ProfessionalService'],
        '@id' => abs_url('#empresa'),
        'name' => $S['site_title'] ?? 'Douglas Souza Arquitetura',
        'description' => plain($S['meta_description'] ?? ''),
        'url' => abs_url(),
        'logo' => abs_url('assets/img/logo-original.png'),
        'image' => abs_url(!empty($S['og_image']) ? upload_url($S['og_image']) : 'assets/img/logo-original.png'),
        'email' => $S['email'] ?? '',
        'telephone' => $tel ? '+55 ' . $tel[0] : '',
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $S['address1'] ?? '',
            'addressLocality' => $S['seo_city'] ?? '',
            'addressRegion' => $S['seo_region'] ?? '',
            'postalCode' => $S['seo_postal'] ?? '',
            'addressCountry' => 'BR',
        ]),
        'areaServed' => array_map(fn($c) => ['@type' => 'City', 'name' => $c], $areas),
        'founder' => [
            '@type' => 'Person',
            'name' => $S['seo_founder'] ?? 'Douglas Souza',
            'jobTitle' => 'Arquiteto e Urbanista',
        ],
        'knowsAbout' => ['Arquitetura', 'Urbanismo', 'Projetos residenciais', 'Projetos comerciais', 'Paisagismo', 'Maquete eletrônica 3D', 'Projeto de interiores', 'Projeto luminotécnico', 'Gerenciamento de obras', 'Consultoria para obras em condomínios'],
    ];
    if (count($tel) > 1) {
        $schema['contactPoint'] = array_map(fn($t) => ['@type' => 'ContactPoint', 'telephone' => '+55 ' . $t, 'contactType' => 'customer service', 'areaServed' => 'BR', 'availableLanguage' => 'Portuguese'], $tel);
    }
    return array_filter($schema, fn($v) => $v !== '' && $v !== []);
}

function breadcrumb_schema(array $items): array
{
    $list = [];
    foreach ($items as $i => [$name, $url]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/* ---------- <head> ---------- */

/**
 * $m: title, description, canonical, image, type (website|article), robots, schema (lista de nós JSON-LD)
 */
function page_head(array $m): void
{
    $S = settings();
    $siteName = $S['site_title'] ?? 'Douglas Souza Arquitetura';
    $title = $m['title'] ?? $siteName;
    $desc = cut($m['description'] ?? ($S['meta_description'] ?? ''), 160);
    $canonical = $m['canonical'] ?? abs_url();
    $image = $m['image'] ?? (!empty($S['og_image']) ? abs_url(upload_url($S['og_image'])) : abs_url('assets/img/logo-original.png'));
    $schema = $m['schema'] ?? [];
    $schema[] = ['@type' => 'WebSite', '@id' => abs_url('#site'), 'url' => abs_url(), 'name' => $siteName, 'inLanguage' => 'pt-BR'];
    $graph = ['@context' => 'https://schema.org', '@graph' => $schema];
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= e($m['robots'] ?? 'index, follow, max-image-preview:large') ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="author" content="<?= e($S['seo_founder'] ?? 'Douglas Souza') ?>">
<meta name="theme-color" content="#57585B">
<meta name="geo.region" content="BR-<?= e($S['seo_region'] ?? 'SP') ?>">
<meta name="geo.placename" content="<?= e($S['seo_city'] ?? 'Porto Feliz') ?>">
<?php if (!empty($S['google_verification'])): ?><meta name="google-site-verification" content="<?= e($S['google_verification']) ?>">
<?php endif; ?>
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($m['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">
<link rel="icon" type="image/png" href="assets/img/logo-original.png">
<link rel="apple-touch-icon" href="assets/img/logo-original.png">
<link rel="sitemap" type="application/xml" title="Mapa do site" href="sitemap.php">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/site.css?v=4">
<script type="application/ld+json"><?= json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<div class="page">
<?php
}

/* ---------- Cabeçalho ---------- */

function site_header(string $active = 'home'): void
{
    $S = settings();
    $items = ['home' => 'Home', 'projetos' => 'Projetos', 'sobre' => 'Sobre', 'porto-feliz' => 'Porto Feliz', 'contato' => 'Contato'];
    ?>
<header class="site-header" id="home">
  <div class="wrap">
    <a class="logo" href="<?= e(home_link('home')) ?>" aria-label="<?= e($S['site_title'] ?? '') ?> — página inicial">
      <img src="assets/img/logo-cabecalho.png" alt="Douglas Souza — arquiteto &amp; urbanista" width="220" height="172">
    </a>
    <nav class="nav" aria-label="Menu principal">
      <?php foreach ($items as $k => $label): ?>
        <a href="<?= e(home_link($k)) ?>"<?= $k === $active ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(home_link('contato')) ?>" class="btn btn--lime">Fale conosco</a>
    </nav>
  </div>
</header>
<?php
}

/* ---------- Rodapé com mapa do site ---------- */

function site_footer(): void
{
    $S = settings();
    $cats = site_data();
    $tel = fn(string $t) => 'tel:+55' . preg_replace('/\D+/', '', $t);
    ?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a href="<?= e(home_link('home')) ?>" aria-label="Página inicial"><img src="assets/img/logo-rodape.png" alt="Douglas Souza — arquiteto &amp; urbanista" width="220" height="172" loading="lazy"></a>
      <p><?= e(cut($S['meta_description'] ?? '', 170)) ?></p>
      <address>
        <?= e($S['address1'] ?? '') ?><br><?= e($S['address2'] ?? '') ?><br>
        <?php foreach (['phone1', 'phone2'] as $k): if (!empty($S[$k])): ?>
          <a href="<?= e($tel($S[$k])) ?>"><?= e($S[$k]) ?></a><br>
        <?php endif; endforeach; ?>
        <?php if (!empty($S['email'])): ?><a href="mailto:<?= e($S['email']) ?>"><?= e($S['email']) ?></a><?php endif; ?>
      </address>
    </div>

    <nav class="footer-col" aria-label="Mapa do site — páginas">
      <h2>Mapa do site</h2>
      <ul>
        <li><a href="<?= e(home_link('home')) ?>">Página inicial</a></li>
        <li><a href="<?= e(home_link('projetos')) ?>">Projetos</a></li>
        <li><a href="<?= e(home_link('sobre')) ?>">Sobre o arquiteto</a></li>
        <li><a href="<?= e(home_link('consultoria')) ?>">Consultoria para condomínios</a></li>
        <li><a href="<?= e(home_link('porto-feliz')) ?>">Porto Feliz</a></li>
        <li><a href="<?= e(home_link('contato')) ?>">Contato</a></li>
        <li><a href="mapa-do-site.php">Mapa do site completo</a></li>
      </ul>
    </nav>

    <?php foreach ($cats as $c): ?>
    <nav class="footer-col" aria-label="Projetos — <?= e($c['name']) ?>">
      <h2><a href="<?= e(home_link('cat-' . $c['slug'])) ?>"><?= e($c['name']) ?></a></h2>
      <ul>
        <?php foreach ($c['projects'] as $p): ?>
          <li><a href="<?= e(project_url($c, $p)) ?>"><?= e($p['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php endforeach; ?>
  </div>
  <div class="footer-bottom">
    <div class="wrap">
      <span>© <?= date('Y') ?> <?= e($S['site_title'] ?? '') ?> — <?= e($S['seo_founder'] ?? 'Douglas Souza') ?>, Arquiteto e Urbanista</span>
      <span><a href="mapa-do-site.php">Mapa do site</a> · <a href="sitemap.php">Sitemap XML</a></span>
    </div>
  </div>
</footer>
</div>

<div class="lightbox" role="dialog" aria-modal="true" aria-label="Visualizador de fotos" hidden data-lightbox>
  <img src="" alt="" data-lb-img>
  <div class="bar">
    <button type="button" class="icon-btn icon-btn--ghost" data-lb-prev aria-label="Imagem anterior"><?= ICON_CHEV_L ?></button>
    <span class="cap"><span data-lb-cap></span><span class="pos" data-lb-pos></span></span>
    <button type="button" class="icon-btn icon-btn--lime" data-lb-next aria-label="Próxima imagem"><?= ICON_CHEV_R ?></button>
    <button type="button" class="close" data-lb-close>Fechar</button>
  </div>
</div>
<script src="assets/js/site.js?v=2" defer></script>
</body>
</html>
<?php
}
