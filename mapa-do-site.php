<?php
require __DIR__ . '/inc/bootstrap.php';

$S = settings();
$cats = site_data();
$url = abs_url('mapa-do-site.php');

page_head([
    'title' => 'Mapa do site | ' . $S['site_title'],
    'description' => 'Todas as páginas e projetos do site de ' . $S['seo_founder'] . ', arquiteto e urbanista em ' . $S['seo_city'] . '/' . $S['seo_region'] . '.',
    'canonical' => $url,
    'schema' => [breadcrumb_schema([['Início', abs_url()], ['Mapa do site', $url]])],
]);
site_header('');
?>
<main id="conteudo">
  <section class="section sitemap-page">
    <div class="wrap">
      <nav class="breadcrumb breadcrumb--light" aria-label="Você está em">
        <ol><li><a href="index.php">Início</a></li><li aria-current="page">Mapa do site</li></ol>
      </nav>
      <div class="kicker kicker--gray">Navegação</div>
      <h1 class="h-xl">Mapa do site</h1>

      <div class="sitemap-grid">
        <section>
          <h2>Páginas</h2>
          <ul>
            <li><a href="index.php">Página inicial</a></li>
            <li><a href="index.php#projetos">Projetos</a></li>
            <li><a href="index.php#sobre">Sobre o arquiteto</a></li>
            <li><a href="index.php#consultoria">Consultoria para reformas e obras em condomínios</a></li>
            <li><a href="index.php#porto-feliz">Porto Feliz</a>
              <ul>
                <li><a href="index.php#pf-materia">Matéria: <?= e(plain($S['pf_article_title'])) ?></a></li>
                <li><a href="index.php#porto-feliz">Porto Feliz na imprensa</a></li>
              </ul>
            </li>
            <li><a href="index.php#contato">Contato e endereço</a></li>
          </ul>
        </section>

        <?php foreach ($cats as $c): ?>
        <section>
          <h2><a href="index.php#cat-<?= e($c['slug']) ?>">Projetos — <?= e($c['name']) ?></a></h2>
          <?php if ($c['description']): ?><p><?= e($c['description']) ?></p><?php endif; ?>
          <ul>
            <?php foreach ($c['projects'] as $p): ?>
              <li><a href="<?= e(project_url($c, $p)) ?>"><?= e($p['name']) ?></a> <span class="muted">· <?= count($p['photos']) ?> fotos</span></li>
            <?php endforeach; ?>
          </ul>
        </section>
        <?php endforeach; ?>

        <section>
          <h2>Para buscadores</h2>
          <ul>
            <li><a href="sitemap.php">Sitemap XML</a></li>
            <li><a href="robots.php">robots.txt</a></li>
          </ul>
        </section>
      </div>
    </div>
  </section>
</main>
<?php site_footer(); ?>
