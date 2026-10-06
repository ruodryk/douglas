<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) { return $pdo; }
    global $CONFIG;
    $dir = dirname($CONFIG['db_path']);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $pdo = new PDO('sqlite:' . $CONFIG['db_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT NOT NULL,
        ts INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS slides (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        tag TEXT NOT NULL DEFAULT '',
        short TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL,
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        slug TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        published INTEGER NOT NULL DEFAULT 1,
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS photos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        file TEXT NOT NULL,
        caption TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS press (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file TEXT NOT NULL,
        caption TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    SQL);

    $has = (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    if ($has === 0) {
        seed($pdo);
    } else {
        upgrade($pdo);
    }
}

/** Ajustes em bancos criados por versões anteriores. */
function upgrade(PDO $pdo): void
{
    $old = 'Douglas Souza — Arquiteto e Urbanista em Porto Feliz/SP. Projetos residenciais, comerciais, paisagismo, maquetes 3D e consultoria para obras em condomínios.';
    $st = $pdo->prepare('UPDATE settings SET value = ? WHERE key = ? AND value = ?');
    $st->execute([default_settings()['meta_description'], 'meta_description', $old]);
}

/** Conteúdo inicial: textos e fotos do design aprovado. */
function seed(PDO $pdo): void
{
    $pdo->beginTransaction();
    $s = $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach (default_settings() as $k => $v) {
        $s->execute([$k, $v]);
    }

    $seedDir = ROOT . '/seed/fotos';
    $hasSeed = is_dir($seedDir);
    $img = function (string $rel, string $toDir) use ($seedDir, $hasSeed): ?string {
        $src = $seedDir . '/' . $rel;
        if (!$hasSeed || !is_file($src)) { return null; }
        return store_image($src, $toDir, pathinfo($src, PATHINFO_FILENAME));
    };

    // Categorias e projetos (segue a hierarquia fotos/<categoria>/<projeto>)
    $cats = [
        ['Residencial', 'residencial', 'Fachadas, interiores, iluminação e marcenaria planejada para residências.'],
        ['Comercial', 'comercial', 'Clínicas, escritórios e espaços comerciais — da fachada aos interiores.'],
        ['Paisagismo', 'paisagismo', 'Jardins, caminhos, espelhos d’água e áreas de piscina.'],
        ['Maquetes', 'maquetes', 'Maquetes eletrônicas em 3D para visualizar o projeto antes da obra.'],
    ];
    $ic = $pdo->prepare('INSERT INTO categories(name, slug, description, sort) VALUES(?, ?, ?, ?)');
    $ip = $pdo->prepare('INSERT INTO projects(category_id, name, slug, sort) VALUES(?, ?, ?, ?)');
    $if = $pdo->prepare('INSERT INTO photos(project_id, file, sort) VALUES(?, ?, ?)');
    foreach ($cats as $ci => [$name, $slug, $desc]) {
        $ic->execute([$name, $slug, $desc, $ci + 1]);
        $catId = (int)$pdo->lastInsertId();
        if (!$hasSeed || !is_dir("$seedDir/$slug")) { continue; }
        $projDirs = array_filter(scandir("$seedDir/$slug") ?: [], fn($d) => $d[0] !== '.' && is_dir("$seedDir/$slug/$d"));
        natsort($projDirs);
        $pi = 0;
        foreach ($projDirs as $pd) {
            $pi++;
            $pname = ucfirst(preg_replace('/(\d+)/', ' $1', $pd));
            $ip->execute([$catId, $pname, slugify($pd), $pi]);
            $projId = (int)$pdo->lastInsertId();
            $files = array_filter(scandir("$seedDir/$slug/$pd") ?: [], fn($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f));
            natsort($files);
            $fi = 0;
            foreach ($files as $f) {
                $rel = $img("$slug/$pd/$f", "projetos/$slug/" . slugify($pd));
                if ($rel) { $if->execute([$projId, $rel, ++$fi]); }
            }
        }
    }

    // Banner
    $slides = [
        ['Projetos Residenciais', 'MORAR BEM', 'Residencial', 'residencial/projeto1/residencial01.jpg'],
        ['Projetos Comerciais', 'NEGÓCIOS', 'Comercial', 'comercial/projeto1/comercial06.jpg'],
        ['Paisagismo', 'ÁREAS EXTERNAS', 'Paisagismo', 'paisagismo/projeto1/paisagismo07.jpg'],
        ['Maquetes 3D', 'ANTES DA OBRA', 'Maquetes', 'maquetes/projeto1/maquete03.jpg'],
    ];
    $is = $pdo->prepare('INSERT INTO slides(title, tag, short, image, sort) VALUES(?, ?, ?, ?, ?)');
    foreach ($slides as $i => [$t, $tag, $short, $src]) {
        $rel = $img($src, 'banner');
        if ($rel) { $is->execute([$t, $tag, $short, $rel, $i + 1]); }
    }

    // Imagens das seções
    if ($rel = $img('residencial/projeto1/residencial02.jpg', 'secoes')) { $s->execute(['sobre_image', $rel]); }
    if ($rel = $img('comercial/projeto1/comercial03.jpg', 'secoes')) { $s->execute(['pf_image', $rel]); }

    // Porto Feliz na imprensa
    $press = [
        ['boom-ec0.jpg', 'O boom econômico de Porto Feliz — Especial Porto Feliz'],
        ['boom-ec1.jpg', 'Especial Porto Feliz — continuação da reportagem'],
        ['MULT2001.jpg', '40 milhões de reais: investimento da Cooper Power Systems'],
        ['lanx1.jpg', 'Porto Feliz: polo químico especializado em produtos de alta qualidade'],
        ['lanx0.jpg', 'Unidade da Lanxess em Porto Feliz'],
    ];
    $ipr = $pdo->prepare('INSERT INTO press(file, caption, sort) VALUES(?, ?, ?)');
    foreach ($press as $i => [$f, $cap]) {
        $rel = $img("porto feliz/$f", 'imprensa');
        if ($rel) { $ipr->execute([$rel, $cap, $i + 1]); }
    }
    $pdo->commit();
}

function default_settings(): array
{
    return [
        'site_title' => 'Douglas Souza Arquitetura',
        'meta_description' => 'Arquiteto e urbanista em Porto Feliz/SP: projetos residenciais, comerciais, paisagismo, maquetes 3D e consultoria para obras em condomínios.',
        'home_title' => 'Arquiteto em Porto Feliz/SP | Douglas Souza Arquitetura',
        'og_image' => '',
        'seo_city' => 'Porto Feliz',
        'seo_region' => 'SP',
        'seo_postal' => '',
        'seo_area' => 'Porto Feliz, Sorocaba, Itu, Salto, Boituva, Cerquilho, Indaiatuba',
        'seo_founder' => 'Douglas Souza',
        'google_verification' => '',

        'hero_kicker' => 'ARQUITETURA & URBANISMO — PORTO FELIZ/SP',
        'hero_title' => 'Projetos com estilo *único* e inovador.',
        'hero_text' => 'Residências, comércios, clubes, indústrias, lojas e paisagismo — do traço inicial à obra concluída.',

        'projetos_title' => "O que\nprojetamos",
        'projetos_text' => 'Projetos arquitetônicos e urbanísticos para cada escala — da casa à indústria.',

        'sobre_title' => "Cada projeto,\num *traço* próprio.",
        'sobre_p1' => 'Douglas Souza, Arquiteto e Urbanista, atua no desenvolvimento de projetos residenciais, comerciais, de interiores, paisagismo, luminotécnico, gerenciamento de obras, consultoria e administração. Atuou, desde 1995, como desenhista projetista arquitetônico na cidade de Porto Feliz, interior de São Paulo.',
        'sobre_p2' => 'Buscando aprimorar seus conhecimentos, em 2001 ingressou no curso de Arquitetura e Urbanismo pela CEUNSP (Salto), graduando‑se em 2005. Com seu estilo único e inovador, destacou‑se pelos projetos e ampliou seu leque de serviços atendendo cidades da região: Sorocaba, Itu, Salto, Boituva, Cerquilho e Indaiatuba.',
        'sobre_cards' => "ATUAÇÃO | Arquitetura & Urbanismo\nESCRITÓRIO | Centro — Porto Feliz/SP\nPROJETOS | Residenciais, comerciais e industriais\nCONSULTORIA | Reformas e obras em condomínios",
        'sobre_image' => '',

        'consultoria_title' => 'Consultoria para reformas ou obras em condomínios',
        'consultoria_text' => 'Da simples troca de fiação elétrica ao reforço estrutural: toda intervenção em condomínio pede coordenação e planejamento adequados para ser executada com segurança e sem transtornos aos moradores.',
        'consultoria_scale' => "Troca de fiação elétrica\nReformas de áreas comuns\nAmpliações e adequações\nReforço estrutural",
        'consultoria_note' => 'Em todas as escalas: planejamento da obra, coordenação entre equipes e acompanhamento técnico.',

        'pf_title' => 'Porto *Feliz*/SP',
        'pf_lead' => 'Investe São Paulo assessorou a empresa que irá expandir a fabricação de religadores e iniciar, no interior do Estado, a produção de capacitores, reguladores de tensão, sistemas de automação e dispositivos de proteção, como para-raios e fusíveis. A Cooper Power Systems do Brasil, fabricante de equipamentos de média e alta tensão, expandirá suas operações no País com uma nova unidade em Porto Feliz, cidade a 110 km da capital paulista.',
        'pf_image' => '',
        'pf_article_kicker' => 'PORTO FELIZ EM DESTAQUE',
        'pf_article_title' => 'Cooper Power Systems escolhe *Porto Feliz*',
        'pf_article_body' => "Antes de optar pela cidade, a empresa norte-americana considerou outros três estados brasileiros. Com o apoio da Investe São Paulo na localização de áreas e na assessoria ambiental, tributária e de infraestrutura, a Cooper decidiu se instalar em Porto Feliz. “A cidade possui um conjunto de facilidades, desde logística para o escoamento da produção até a disponibilidade de mão de obra qualificada na região. O apoio da prefeitura local também foi fundamental”, destacou o presidente da Investe São Paulo, Luciano Almeida.\n\nOs novos produtos e soluções atenderão todo o mercado brasileiro, e a unidade de Porto Feliz servirá como base de exportação para o mercado latino-americano. “A região é uma prioridade de investimento para a Cooper Industries devido ao forte crescimento econômico, ao investimento contínuo em infraestrutura e aos grandes eventos esportivos”, disse o presidente da Cooper Power Systems, Mike Stoessl. “Expandir as operações no Brasil irá consolidar a posição da empresa no apoio ao desenvolvimento do mercado de energia, que ocorre em uma das dez maiores economias do mundo”, acrescentou Stoessl.\n\nO secretário estadual de Desenvolvimento Econômico, Ciência e Tecnologia, Paulo Alexandre Barbosa, ressaltou a importância do empreendimento para o Estado. “Quando uma empresa se instala em São Paulo, estimula a geração de emprego e renda para a população local. A região de Sorocaba é um dos vetores de crescimento do País e parabenizamos a empresa pela escolha”, afirmou.\n\nA nova planta da Cooper, que já conta com operações em Itu e Sorocaba, será construída em um terreno de 90 mil m², localizado no km 125 da rodovia Marechal Cândido Rondon. O empreendimento terá 27 mil m² de área construída, entre área fabril e escritórios. “Temos o objetivo de obter a certificação LEED, com meta de alcançar o nível Platinum. Essa certificação reforça a iniciativa sustentável que a Cooper adota, seja nos seus produtos, com destaque para o óleo vegetal FR3, seja nas suas construções baseadas no respeito às boas práticas ambientais”, afirmou o vice‑presidente da Cooper Power Systems no Mercosul, Flavio Marquet.",
        'pf_press_title' => 'Galeria',

        'contato_title' => "Vamos *projetar*\njuntos?",
        'phone1' => '(15) 3262-4400',
        'phone2' => '(15) 3262-2525',
        'email' => 'contato@arquitetodouglas.com.br',
        'address1' => 'Av. José Maurino, nº 81',
        'address2' => 'Centro — Porto Feliz/SP',
        'map_query' => 'Av. José Maurino, 81, Centro, Porto Feliz - SP',
    ];
}
