<?php
require __DIR__ . '/inc/bootstrap.php';

$pdo = db();
$cats = site_data();
$press = $pdo->query('SELECT * FROM press ORDER BY sort, id')->fetchAll();

$S = settings();
$contactStatus = $_SESSION['contact_status'] ?? null;
unset($_SESSION['contact_status']);

$telHref = fn(string $t) => 'tel:+55' . preg_replace('/\D+/', '', $t);
$cards = array_map(fn($l) => array_map('trim', explode('|', $l, 2)) + [1 => ''], lines($S['sobre_cards'] ?? ''));
$scale = lines($S['consultoria_scale'] ?? '');
$nScale = max(1, count($scale));
$IS_HOME = true;

// Dados estruturados: empresa, página e galeria de projetos
$schema = [business_schema()];
$schema[] = [
    '@type' => 'WebPage', '@id' => abs_url('#pagina'), 'url' => abs_url(),
    'name' => $S['home_title'], 'description' => plain($S['meta_description']),
    'isPartOf' => ['@id' => abs_url('#site')], 'about' => ['@id' => abs_url('#empresa')], 'inLanguage' => 'pt-BR',
];
$items = [];
foreach ($cats as $c) {
    foreach ($c['projects'] as $p) {
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'url' => abs_url(project_url($c, $p)), 'name' => $c['name'] . ' — ' . $p['name']];
    }
}
if ($items) { $schema[] = ['@type' => 'ItemList', 'name' => 'Projetos', 'itemListElement' => $items]; }

page_head([
    'title' => $S['home_title'],
    'description' => $S['meta_description'],
    'canonical' => abs_url(),
    'schema' => $schema,
]);
site_header('home');
?>
<main id="conteudo">

<!-- HERO / BANNER -->
<section class="hero grid-bg">
  <div class="wrap">
    <div class="hero-text">
      <div class="kicker kicker--lime"><?= e($S['hero_kicker'] ?? '') ?></div>
      <h1><?= hl($S['hero_title'] ?? '') ?></h1>
      <p><?= e($S['hero_text'] ?? '') ?></p>
      <div class="actions">
        <a href="#projetos" class="btn btn--lime">Ver projetos <?= ICON_ARROW ?></a>
        <a href="#contato" class="btn btn--ghost">Solicitar orçamento</a>
      </div>
    </div>
  </div>
</section>

<!-- PROJETOS -->
<section class="section grid-bg" id="projetos">
  <div class="wrap">
    <div class="section-head">
      <div>
        <div class="kicker kicker--lime">01 — Projetos</div>
        <h2 class="h-xl"><?= hl($S['projetos_title'] ?? '') ?></h2>
      </div>
      <p><?= e($S['projetos_text'] ?? '') ?></p>
    </div>

    <?php if (!$cats): ?>
      <p class="empty">Nenhum projeto publicado ainda.</p>
    <?php else: ?>
    <div class="cat-tabs" role="tablist" aria-label="Categorias de projeto" data-tabs>
      <?php foreach ($cats as $i => $c): ?>
        <button type="button" class="cat-tab" role="tab" id="tab-<?= e($c['slug']) ?>" aria-controls="cat-<?= e($c['slug']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
          <?php if ($c['cover']): ?><img src="<?= e(thumb_url($c['cover'])) ?>" alt=""<?= img_dims($c['cover'], true) ?> loading="lazy"><?php endif; ?>
          <span>
            <span class="name"><?= e($c['name']) ?></span>
            <span class="count"><?= count($c['projects']) ?> <?= count($c['projects']) === 1 ? 'PROJETO' : 'PROJETOS' ?> · <?= (int)$c['photo_count'] ?> FOTOS</span>
          </span>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($cats as $i => $c): ?>
    <div class="cat-panel" role="tabpanel" id="cat-<?= e($c['slug']) ?>" aria-labelledby="tab-<?= e($c['slug']) ?>" <?= $i ? 'hidden' : '' ?>>
      <div class="cat-panel-head">
        <div>
          <div class="crumb">PROJETOS / <span class="hl"><?= e(mb_strtoupper($c['name'])) ?></span></div>
          <h3><?= e($c['name']) ?></h3>
        </div>
        <?php if ($c['description']): ?><p><?= e($c['description']) ?></p><?php endif; ?>
      </div>

      <?php foreach ($c['projects'] as $pi => $p): $gid = 'p' . $p['id']; ?>
      <article class="gallery-card">
        <div class="gallery-head">
          <span class="num"><?= sprintf('%02d', $pi + 1) ?></span>
          <h4><a href="<?= e(project_url($c, $p)) ?>"><?= e($p['name']) ?></a></h4>
          <span class="meta"><?= count($p['photos']) ?> FOTOS — CLIQUE PARA AMPLIAR</span>
          <a class="more" href="<?= e(project_url($c, $p)) ?>">Ver página do projeto <?= ICON_ARROW ?></a>
        </div>
        <?php if ($p['description']): ?><p class="gallery-desc"><?= e($p['description']) ?></p><?php endif; ?>
        <?php if (!$p['photos']): ?>
          <p class="empty">Fotos em breve.</p>
        <?php else: ?>
        <div class="gallery">
          <?php foreach ($p['photos'] as $k => $ph):
            $cap = $ph['caption'] ?: ($c['name'] . ' — ' . $p['name']); $alt = photo_alt($c, $p, $ph, $k + 1); ?>
            <button type="button" class="<?= $k === 0 ? 'big' : '' ?>" data-lb="<?= $gid ?>" data-src="<?= e(upload_url($ph['file'])) ?>" data-caption="<?= e($cap) ?>" aria-label="Ampliar foto <?= $k + 1 ?>: <?= e($cap) ?>">
              <img src="<?= e($k === 0 ? upload_url($ph['file']) : thumb_url($ph['file'])) ?>" alt="<?= e($alt) ?>"<?= img_dims($ph['file'], $k !== 0) ?> loading="lazy" decoding="async">
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>

      <a href="#contato" class="btn btn--lime" style="align-self:flex-start">Quero um projeto assim <?= ICON_ARROW ?></a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- SOBRE -->
<section class="section sobre" id="sobre">
  <div class="wrap">
    <div>
      <div class="kicker kicker--gray">02 — Sobre</div>
      <h2 class="h-xl"><?= str_replace('class="hl"', 'class="hl-box"', hl($S['sobre_title'] ?? '')) ?></h2>
      <?php if (!empty($S['sobre_image'])): ?>
        <img class="photo" src="<?= e(upload_url($S['sobre_image'])) ?>" alt="Projeto residencial de <?= e($S['seo_founder']) ?>, arquiteto em <?= e($S['seo_city']) ?>/<?= e($S['seo_region']) ?>"<?= img_dims($S['sobre_image']) ?> loading="lazy" decoding="async">
      <?php endif; ?>
    </div>
    <div class="text">
      <p class="lead"><?= e($S['sobre_p1'] ?? '') ?></p>
      <?php foreach (paragraphs($S['sobre_p2'] ?? '') as $para): ?>
        <p class="body"><?= e($para) ?></p>
      <?php endforeach; ?>
      <?php if ($cards): ?>
      <div class="facts">
        <?php foreach ($cards as [$label, $value]): ?>
          <div class="fact"><div class="label"><?= e(mb_strtoupper($label)) ?></div><div class="value"><?= e($value) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- CONSULTORIA -->
<section class="section consultoria" id="consultoria">
  <div class="wrap">
    <div>
      <div class="kicker">03 — Consultoria</div>
      <h2 class="h-xl"><?= hl($S['consultoria_title'] ?? '') ?></h2>
      <p class="lead"><?= e($S['consultoria_text'] ?? '') ?></p>
      <a href="#contato" class="btn btn--dark">Agendar consultoria <?= ICON_ARROW ?></a>
    </div>
    <?php if ($scale): ?>
    <div class="scale-box">
      <div class="kicker">ESCALA DA INTERVENÇÃO</div>
      <?php foreach ($scale as $i => $label): $on = (int)ceil(($i + 1) * 4 / $nScale); ?>
        <div class="scale-row">
          <div class="n"><?= chr(65 + $i) ?></div>
          <div class="label"><?= e($label) ?></div>
          <div class="bars" aria-hidden="true"><?php for ($b = 0; $b < 4; $b++): ?><i class="<?= $b < $on ? 'on' : '' ?>"></i><?php endfor; ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!empty($S['consultoria_note'])): ?><p class="note"><?= e($S['consultoria_note']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- PORTO FELIZ -->
<section class="section pf" id="porto-feliz">
  <div class="wrap">
    <div class="pf-card">
      <div class="media">
        <?php if (!empty($S['pf_image'])): ?><img src="<?= e(upload_url($S['pf_image'])) ?>" alt="Projeto em Porto Feliz/SP — Douglas Souza Arquitetura"<?= img_dims($S['pf_image']) ?> loading="lazy" decoding="async"><?php endif; ?>
      </div>
      <div class="body">
        <div>
          <div class="kicker kicker--lime">04 — Porto Feliz</div>
          <h2 class="h-xl"><?= hl($S['pf_title'] ?? '') ?></h2>
          <p><?= e($S['pf_lead'] ?? '') ?></p>
        </div>
        <?php if (paragraphs($S['pf_article_body'] ?? '')): ?>
          <a href="#pf-materia" class="btn btn--ghost">Leia a matéria completa</a>
        <?php endif; ?>
      </div>
    </div>

    <?php $body = paragraphs($S['pf_article_body'] ?? ''); if ($body): ?>
    <article class="article" id="pf-materia">
      <div class="article-head">
        <div class="kicker kicker--gray"><?= e($S['pf_article_kicker'] ?? '') ?></div>
        <h3><?= str_replace('class="hl"', 'class="hl-box"', hl($S['pf_article_title'] ?? '')) ?></h3>
      </div>
      <div class="article-body">
        <?php foreach ($body as $para): ?><p><?= e($para) ?></p><?php endforeach; ?>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($press): ?>
    <div class="press">
      <div class="press-head">
        <div>
          <div class="kicker kicker--gray">PORTO FELIZ NA IMPRENSA</div>
          <h3><?= e($S['pf_press_title'] ?? 'Galeria') ?></h3>
        </div>
        <p>Clique em uma imagem para ampliar.</p>
      </div>
      <div class="press-grid">
        <?php foreach ($press as $pr): ?>
          <button type="button" data-lb="imprensa" data-src="<?= e(upload_url($pr['file'])) ?>" data-caption="<?= e($pr['caption']) ?>" aria-label="Ampliar: <?= e($pr['caption']) ?>">
            <img src="<?= e(thumb_url($pr['file'])) ?>" alt="<?= e($pr['caption']) ?>"<?= img_dims($pr['file'], true) ?> loading="lazy" decoding="async">
            <span><?= e($pr['caption']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- CONTATO -->
<section class="section grid-bg contato" id="contato">
  <div class="wrap">
    <div class="kicker kicker--lime">05 — Contato</div>
    <h2><?= hl($S['contato_title'] ?? '') ?></h2>
    <div class="contato-grid">
      <div class="info">
        <div class="info-box">
          <div class="kicker">TELEFONES</div>
          <?php foreach (['phone1', 'phone2'] as $k): if (!empty($S[$k])): ?>
            <a class="phone" href="<?= e($telHref($S[$k])) ?>"><?= e($S[$k]) ?></a>
          <?php endif; endforeach; ?>
        </div>
        <?php if (!empty($S['email'])): ?>
        <div class="info-box">
          <div class="kicker">E-MAIL</div>
          <a class="mail" href="mailto:<?= e($S['email']) ?>"><?= e($S['email']) ?></a>
        </div>
        <?php endif; ?>
        <div class="info-box">
          <div class="kicker">ENDEREÇO</div>
          <div class="addr"><?= e($S['address1'] ?? '') ?><br><?= e($S['address2'] ?? '') ?></div>
        </div>
        <?php if (!empty($S['map_query'])): ?>
          <iframe class="map" title="Mapa: <?= e($S['map_query']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps?q=<?= e(rawurlencode($S['map_query'])) ?>&amp;output=embed"></iframe>
        <?php endif; ?>
      </div>

      <form class="form" method="post" action="contato.php">
        <?= csrf_field() ?>
        <div class="title">Envie sua mensagem</div>
        <div class="field"><label for="f-nome">Nome</label><input id="f-nome" name="nome" type="text" autocomplete="name" required maxlength="120"></div>
        <div class="row-2">
          <div class="field"><label for="f-email">E-mail</label><input id="f-email" name="email" type="email" autocomplete="email" required maxlength="160"></div>
          <div class="field"><label for="f-tel">Telefone</label><input id="f-tel" name="telefone" type="tel" autocomplete="tel" maxlength="40"></div>
        </div>
        <div class="field"><label for="f-msg">Mensagem</label><textarea id="f-msg" name="mensagem" rows="5" required maxlength="5000"></textarea></div>
        <div class="hp" aria-hidden="true"><label for="f-site">Não preencha</label><input id="f-site" name="site" type="text" tabindex="-1" autocomplete="off"></div>
        <?php if ($contactStatus === 'ok'): ?>
          <div class="alert alert--ok" role="status">Mensagem enviada! Retornaremos em breve.</div>
        <?php elseif ($contactStatus === 'erro'): ?>
          <div class="alert alert--err" role="alert">Preencha nome, e-mail válido e mensagem.</div>
        <?php endif; ?>
        <button type="submit" class="btn btn--dark">Enviar mensagem</button>
      </form>
    </div>
  </div>
</section>

</main>
<?php site_footer(); ?>
