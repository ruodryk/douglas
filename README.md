# Site Douglas Souza Arquitetura — PHP + SQLite

Site público, com o design aprovado, e um painel administrativo com login para:

- **Projetos**: criar, editar e excluir categorias e projetos e enviar várias fotos de uma vez. As fotos são redimensionadas e ganham miniatura automaticamente. Também dá para pôr legenda, mudar a ordem, ocultar ou publicar.
- **Seções**: editar os textos e as imagens das seções Início, Sobre, Consultoria, Porto Feliz e Contato.
- **Banner**: gerenciar os slides do topo.
- **Imprensa**: gerenciar a galeria "Porto Feliz na imprensa".
- **Mensagens**: ver o que chega pelo formulário de contato, que também é enviado por e-mail.
- **Minha conta**: trocar a senha e criar outros usuários.

## Requisitos

- PHP 8.1 ou superior, com as extensões `pdo_sqlite` e `gd` (vêm ativas na maioria das hospedagens).
- Servidor Apache, para os arquivos `.htaccess` de proteção funcionarem. Em Nginx, veja a seção "Nginx" no fim.

## Como publicar

1. Envie **todo o conteúdo desta pasta** por FTP para a pasta pública da hospedagem (`public_html`, `www` ou similar), incluindo os arquivos ocultos `.htaccess`.
2. Dê permissão de escrita às pastas `data/` e `uploads/` (geralmente 755; se der erro, use 775).
3. Abra o site no navegador. No primeiro acesso, o sistema:
   - cria o banco de dados em `data/site.sqlite`;
   - importa os textos e as fotos da pasta `seed/fotos`, seguindo a mesma hierarquia de pastas (categoria → projeto → fotos).
4. Acesse **`/admin`** (ex.: `https://www.arquitetodouglas.com.br/admin`). Na primeira vez, aparece a tela **"Criar administrador"**: escolha o usuário e uma senha de pelo menos 8 caracteres.
5. Depois que tudo estiver funcionando, você pode apagar a pasta `seed/` do servidor. Ela só é usada na primeira importação.

> Para testar no seu computador: com o PHP instalado, rode `php -S localhost:8000 router.php` nesta pasta e abra `http://localhost:8000`. (O `router.php` faz o /sitemap.xml e o /robots.txt funcionarem localmente; na hospedagem quem faz isso é o `.htaccess`.)

## Estrutura

```
index.php           Página inicial (montada a partir do banco)
projeto.php         Página de cada projeto (galeria + SEO)
mapa-do-site.php    Mapa do site para visitantes
sitemap.php         Sitemap XML para buscadores (também /sitemap.xml)
robots.php          robots.txt (também /robots.txt)
router.php          Só para testar localmente com php -S
contato.php         Recebe o formulário de contato
config.php          Configurações (e-mail de contato, tamanho das imagens…)
admin/              Painel administrativo (login em admin/index.php)
inc/                Código interno (banco, imagens, utilidades) — bloqueado ao público
assets/             CSS, JavaScript e logos
uploads/            Fotos enviadas: uploads/projetos/<categoria>/<projeto>/…
data/               Banco SQLite — bloqueado ao público
seed/fotos/         Fotos iniciais (usadas só no primeiro acesso)
```

## Dicas de uso

- **Destaque em verde-limão:** nos títulos, coloque a palavra entre asteriscos, como `Projetos com estilo *único*`.
- **Parágrafos:** nos textos longos, deixe uma linha em branco entre um parágrafo e outro.
- **Foto em destaque de cada galeria:** é a de menor número na coluna "ordem".
- **Categoria sem projetos publicados:** não aparece no site.
- **Fotos grandes:** se o envio falhar, peça à hospedagem para aumentar `upload_max_filesize` e `post_max_size` (sugestão: 20M e 100M) e `max_file_uploads` (sugestão: 50).

## SEO

O que o site já faz automaticamente:

- **Título e descrição** de cada página, editáveis em **Painel › Seções › SEO / Google** (com contador de caracteres).
- **Uma página própria para cada projeto** (`projeto.php?c=…&p=…`), com título, descrição, trilha de navegação (breadcrumb) e galeria. O nome e a descrição do projeto, editados no painel, viram o título e a descrição no Google.
- **Dados estruturados (schema.org)**: empresa local (endereço, telefones, cidades atendidas, arquiteto responsável), site, lista de projetos, breadcrumbs e cada projeto como obra criativa.
- **Open Graph / Twitter**: imagem, título e descrição ao compartilhar no WhatsApp, Facebook e LinkedIn.
- **Imagens**: textos alternativos (alt) automáticos com tipo de projeto e cidade, largura e altura definidas, carregamento sob demanda.
- **Sitemap XML com imagens** em `/sitemap.xml` (ou `/sitemap.php`) e **robots.txt** em `/robots.txt` (ou `/robots.php`).
- **Mapa do site** no rodapé de todas as páginas e na página `mapa-do-site.php`.
- URL canônica, idioma pt-BR, região geográfica, página 404 sem indexação, compressão e cache no `.htaccess`.

Depois de publicar:

1. Cadastre o site no **Google Search Console** (search.google.com/search-console), cole o código de verificação em **Seções › SEO / Google** e envie o sitemap: `https://seudominio/sitemap.xml`.
2. Crie ou atualize o **Perfil da Empresa no Google** (Google Maps) com o mesmo nome, endereço e telefone do site.
3. Dê nomes descritivos aos projetos (ex.: “Residência Jardim Europa”) e escreva uma descrição curta com o tipo de projeto e a cidade.
4. Preencha o **CEP** e envie uma **imagem de compartilhamento** (1200 × 630 px).
5. Se quiser forçar o domínio exato nas URLs (ex.: sempre com https e www), defina `base_url` em `config.php`.

## E-mail do formulário

As mensagens ficam sempre guardadas no painel. O envio por e-mail usa a função `mail()` do PHP, e o destino é configurado em `config.php` (`contact_email`). Se a hospedagem bloquear o `mail()`, as mensagens continuam disponíveis no painel.

## Segurança

- As senhas são guardadas com `password_hash`.
- Os formulários são protegidos contra CSRF.
- O login é bloqueado por 15 minutos depois de 8 tentativas erradas no mesmo IP.
- Os uploads são validados como imagem e regravados com o GD, e scripts não são executados dentro de `uploads/`.
- O banco e o código interno ficam bloqueados para acesso direto.
- Use sempre HTTPS (o cookie de sessão fica marcado como seguro automaticamente).

## Backup

Basta copiar a pasta `data/` (o banco) e a pasta `uploads/` (as fotos).

## Nginx

Se o servidor for Nginx em vez de Apache, adicione ao bloco do site:

```
location ~ ^/(data|inc|seed)/ { deny all; }
location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
location = /config.php { deny all; }
```
