<?php
require __DIR__ . '/_layout.php';
require_login();

$HL = 'Use *asteriscos* para destacar uma palavra em verde-limão. Enter quebra a linha.';
$TABS = [
    'inicio' => ['Início', [
        ['hero_kicker', 'Chamada acima do título', 'text'],
        ['hero_title', 'Título principal', 'textarea', $HL],
        ['hero_text', 'Texto de apoio', 'textarea'],
        ['projetos_title', 'Título da seção Projetos', 'textarea', $HL],
        ['projetos_text', 'Texto da seção Projetos', 'textarea'],
    ]],
    'seo' => ['SEO / Google', [
        ['home_title', 'Título da página inicial no Google', 'text', 'Ideal: até 60 caracteres, com a cidade e o serviço principal. Ex.: Arquiteto em Porto Feliz/SP | Douglas Souza Arquitetura'],
        ['meta_description', 'Descrição no Google', 'textarea', 'Ideal: 120 a 160 caracteres. É o texto que aparece abaixo do título nos resultados de busca.'],
        ['site_title', 'Nome do escritório', 'text', 'Usado no fim do título das páginas de projeto e no rodapé.'],
        ['og_image', 'Imagem de compartilhamento (WhatsApp, Facebook, LinkedIn)', 'image', 'Recomendado: 1200 × 630 px. Sem imagem, é usado o logo.'],
        ['seo_founder', 'Nome do arquiteto', 'text'],
        ['seo_city', 'Cidade', 'text'],
        ['seo_region', 'Estado (sigla)', 'text'],
        ['seo_postal', 'CEP', 'text', 'Ajuda o Google a localizar o escritório.'],
        ['seo_area', 'Cidades atendidas', 'textarea', 'Separe por vírgula. Entram nos dados estruturados para buscas locais.'],
        ['google_verification', 'Código de verificação do Google Search Console', 'text', 'Só o código do content="…" da meta tag de verificação.'],
    ]],
    'sobre' => ['Sobre', [
        ['sobre_title', 'Título', 'textarea', $HL],
        ['sobre_p1', 'Parágrafo de abertura (maior)', 'textarea'],
        ['sobre_p2', 'Demais parágrafos', 'tall', 'Separe parágrafos com uma linha em branco.'],
        ['sobre_cards', 'Quadros de destaque', 'textarea', 'Um por linha, no formato RÓTULO | texto. O último fica escuro.'],
        ['sobre_image', 'Foto', 'image'],
    ]],
    'consultoria' => ['Consultoria', [
        ['consultoria_title', 'Título', 'textarea', $HL],
        ['consultoria_text', 'Texto', 'textarea'],
        ['consultoria_scale', 'Escala da intervenção', 'textarea', 'Um item por linha, do mais simples ao mais complexo.'],
        ['consultoria_note', 'Observação abaixo da escala', 'textarea'],
    ]],
    'portofeliz' => ['Porto Feliz', [
        ['pf_title', 'Título', 'text', $HL],
        ['pf_lead', 'Texto do card (ao lado da foto)', 'tall'],
        ['pf_image', 'Foto do card', 'image'],
        ['pf_article_kicker', 'Matéria — chamada', 'text'],
        ['pf_article_title', 'Matéria — título', 'text', $HL],
        ['pf_article_body', 'Matéria — texto', 'tall', 'Separe parágrafos com uma linha em branco. Deixe vazio para esconder a matéria.'],
        ['pf_press_title', 'Título da galeria de imprensa', 'text', 'As imagens da galeria são gerenciadas em “Imprensa”.'],
    ]],
    'contato' => ['Contato', [
        ['contato_title', 'Título', 'textarea', $HL],
        ['phone1', 'Telefone 1', 'text'],
        ['phone2', 'Telefone 2', 'text'],
        ['email', 'E-mail', 'text'],
        ['address1', 'Endereço — linha 1', 'text'],
        ['address2', 'Endereço — linha 2', 'text'],
        ['map_query', 'Endereço para o mapa', 'text', 'Usado no mapa do Google. Deixe vazio para esconder o mapa.'],
    ]],
];

$tab = (string)($_GET['aba'] ?? 'inicio');
if (!isset($TABS[$tab])) { $tab = 'inicio'; }
[$tabLabel, $fields] = $TABS[$tab];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $errors = [];
    foreach ($fields as $f) {
        [$key, $label, $type] = $f;
        if ($type === 'image') {
            if (!empty($_POST['remove_' . $key])) {
                delete_image(setting($key));
                save_setting($key, '');
            }
            $file = $_FILES[$key] ?? null;
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $rel = store_uploaded($file, 'secoes');
                    delete_image(setting($key));
                    save_setting($key, $rel);
                } catch (Throwable $ex) {
                    $errors[] = $label . ': ' . $ex->getMessage();
                }
            }
            continue;
        }
        save_setting($key, post_str($key));
    }
    flash('Seção “' . $tabLabel . '” salva.');
    foreach ($errors as $er) { flash($er, 'erro'); }
    redirect('secoes.php?aba=' . $tab);
}

$S = settings();
admin_header('Seções', 'secoes');
?>
<div class="kicker">Textos e imagens</div>
<h1>Seções do site</h1>
<p class="sub">Edite o conteúdo de cada parte da página inicial.</p>

<nav class="tabs" aria-label="Seções">
  <?php foreach ($TABS as $k => [$lbl]): ?>
    <a href="?aba=<?= $k ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</nav>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <h2><?= e($tabLabel) ?></h2>
  <?php foreach ($fields as $f):
    [$key, $label, $type] = $f; $help = $f[3] ?? ''; $val = $S[$key] ?? ''; ?>
    <div class="field">
      <label for="f-<?= $key ?>"><?= e($label) ?></label>
      <?php if ($type === 'text'): ?>
        <input id="f-<?= $key ?>" name="<?= $key ?>" type="text" value="<?= e($val) ?>"<?= in_array($key, ['home_title', 'meta_description'], true) ? ' data-count' : '' ?>>
      <?php elseif ($type === 'image'): ?>
        <?php if ($val): ?>
          <img class="img-current" src="../<?= e(thumb_url($val)) ?>" alt="Imagem atual">
          <label class="check"><input type="checkbox" name="remove_<?= $key ?>" value="1"> Remover imagem atual</label>
        <?php endif; ?>
        <input id="f-<?= $key ?>" name="<?= $key ?>" type="file" accept="image/jpeg,image/png,image/webp">
      <?php else: ?>
        <textarea id="f-<?= $key ?>" name="<?= $key ?>" class="<?= $type === 'tall' ? 'tall' : '' ?>"<?= $key === 'meta_description' ? ' data-count' : '' ?>><?= e($val) ?></textarea>
      <?php endif; ?>
      <?php if ($help): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="actions">
    <button class="btn btn--lime" type="submit">Salvar</button>
    <a class="btn btn--ghost" href="../index.php#<?= ['inicio' => 'home', 'sobre' => 'sobre', 'consultoria' => 'consultoria', 'portofeliz' => 'porto-feliz', 'contato' => 'contato', 'seo' => 'home'][$tab] ?>" target="_blank" rel="noopener">Ver no site ↗</a>
  </div>
</form>
<?php admin_footer(); ?>
