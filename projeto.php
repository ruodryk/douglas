<?php
require __DIR__ . '/inc/bootstrap.php';

$S = settings();
$cSlug = (string)($_GET['c'] ?? '');
$pSlug = (string)($_GET['p'] ?? '');

$cat = $proj = null;
foreach (site_data() as $c) {
    if ($c['slug'] !== $cSlug) { continue; }
    foreach ($c['projects'] as $i => $p) {
        if ($p['slug'] === $pSlug) {
            $cat = $c;
            $proj = $p;
            $prev = $c['projects'][$i - 1] ?? null;
            $next = $c['projects'][$i + 1] ?? null;
            break 2;
        }
    }
}

if (!$cat) {
    http_response_code(404);
    page_head([
        'title' => 'Projeto não encontrado | ' . $S['site_title'],
        'description' => 'O projeto procurado não existe ou foi removido.',
        'canonical' => abs_url('mapa-do-site.php'),
        'robots' => 'noindex, follow',
    ]);
    site_header('projetos');
    ?>
    <main id="conteudo" class="section grid-bg">
      <div class="wrap">
        <div class="kicker kicker--lime">Erro 404</div>
        <h1 class="h-xl">Projeto não encontrado</h1>
        <p class="lead-dark">O endereço pode ter mudado. Veja todos os projetos no <a href="mapa-do-site.php">mapa do site</a> ou volte para a <a href="index.php#projetos">página de projetos</a>.</p>
      </div>
    </main>
    <?php
    site_footer();
    exit;
}

$city = $S['seo_city'] . '/' . $S['seo_region'];
$title = $proj['name'] . ' — ' . $cat['name'] . ' em ' . $city . ' | ' . $S['site_title'];
$desc = $proj['description'] !== ''
    ? $proj['description']
    : "Projeto {$cat['name']} ({$proj['name']}) de {$S['seo_founder']}, arquiteto e urbanista em {$city}. " . ($cat['description'] ?: '') . ' Veja ' . count($proj['photos']) . ' fotos.';
$url = abs_url(project_url($cat, $proj));
$cover = $proj['photos'][0]['file'] ?? null;

$images = array_map(fn($ph) => abs_url(upload_url($ph['file'])), $proj['photos']);
$schema = [
    business_schema(),
    breadcrumb_schema([
        ['Início', abs_url()],
        ['Projetos', abs_url('index.php#projetos')],
        [$cat['name'], abs_url('index.php#cat-' . $cat['slug'])],
        [$proj['name'], $url],
    ]),
    array_filter([
        '@type' => 'CreativeWork',
        '@id' => $url . '#projeto',
        'name' => $cat['name'] . ' — ' . $proj['name'],
        'description' => plain($desc),
        'url' => $url,
        'genre' => 'Arquitetura',
        'about' => $cat['name'],
        'inLanguage' => 'pt-BR',
        'creator' => ['@id' => abs_url('#empresa')],
        'locationCreated' => ['@type' => 'Place', 'name' => $city],
        'image' => $images,
    ]),
];

page_head([
    'title' => $title,
    'description' => $desc,
    'canonical' => $url,
    'image' => $cover ? abs_url(upload_url($cover)) : null,
    'type' => 'article',
    'schema' => $schema,
]);
site_header('projetos');
?>
<main id="conteudo">
  <section class="section grid-bg project-page">
    <div class="wrap">
      <nav class="breadcrumb" aria-label="Você está em">
        <ol>
          <li><a href="index.php">Início</a></li>
          <li><a href="index.php#projetos">Projetos</a></li>
          <li><a href="index.php#cat-<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a></li>
          <li aria-current="page"><?= e($proj['name']) ?></li>
        </ol>
      </nav>

      <div class="section-head">
        <div>
          <div class="kicker kicker--lime">Projeto <?= e($cat['name']) ?> · <?= e($city) ?></div>
          <h1 class="h-xl"><?= e($proj['name']) ?> <span class="hl">· <?= e($cat['name']) ?></span></h1>
        </div>
        <p><?= e($proj['description'] !== '' ? $proj['description'] : ($cat['description'] ?: '')) ?></p>
      </div>

      <article class="gallery-card">
        <div class="gallery-head">
          <span class="num"><?= count($proj['photos']) ?></span>
          <h2 class="h-gallery">Galeria de fotos</h2>
          <span class="meta">CLIQUE PARA AMPLIAR</span>
        </div>
        <?php if (!$proj['photos']): ?>
          <p class="empty">Fotos em breve.</p>
        <?php else: ?>
        <div class="gallery gallery--page">
          <?php foreach ($proj['photos'] as $k => $ph):
            $cap = $ph['caption'] ?: ($cat['name'] . ' — ' . $proj['name']); $alt = photo_alt($cat, $proj, $ph, $k + 1); ?>
            <button type="button" class="<?= $k === 0 ? 'big' : '' ?>" data-lb="proj" data-src="<?= e(upload_url($ph['file'])) ?>" data-caption="<?= e($cap) ?>" aria-label="Ampliar foto <?= $k + 1 ?>: <?= e($cap) ?>">
              <img src="<?= e($k === 0 ? upload_url($ph['file']) : thumb_url($ph['file'])) ?>" alt="<?= e($alt) ?>"<?= img_dims($ph['file'], $k !== 0) ?> <?= $k === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </article>

      <div class="project-nav">
        <?php if ($prev): ?><a class="btn btn--ghost" href="<?= e(project_url($cat, $prev)) ?>"><?= ICON_CHEV_L ?> <?= e($prev['name']) ?></a><?php endif; ?>
        <a class="btn btn--lime" href="index.php#contato">Quero um projeto assim <?= ICON_ARROW ?></a>
        <?php if ($next): ?><a class="btn btn--ghost" href="<?= e(project_url($cat, $next)) ?>"><?= e($next['name']) ?> <?= ICON_CHEV_R ?></a><?php endif; ?>
      </div>

      <?php $others = array_filter(site_data(), fn($c) => $c['id'] !== $cat['id']); if ($others): ?>
      <div class="related">
        <h2 class="h-related">Outras categorias</h2>
        <div class="related-grid">
          <?php foreach ($others as $o): $op = $o['projects'][0]; ?>
            <a class="related-card" href="<?= e(project_url($o, $op)) ?>">
              <?php if ($o['cover']): ?><img src="<?= e(thumb_url($o['cover'])) ?>" alt="Projeto <?= e($o['name']) ?> — Douglas Souza Arquitetura"<?= img_dims($o['cover'], true) ?> loading="lazy" decoding="async"><?php endif; ?>
              <span><?= e($o['name']) ?> <small><?= count($o['projects']) ?> projeto(s)</small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php site_footer(); ?>
