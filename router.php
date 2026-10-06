<?php
/**
 * Roteador APENAS para testar no computador com o servidor embutido do PHP:
 *   php -S localhost:8000 router.php
 * Faz /sitemap.xml e /robots.txt funcionarem e bloqueia pastas internas,
 * como o .htaccess faz no Apache. Não é usado na hospedagem.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (preg_match('#^/(data|inc|seed)(/|$)#', $path) || $path === '/config.php') {
    http_response_code(404);
    exit('Não encontrado');
}
if ($path === '/sitemap.xml') { require __DIR__ . '/sitemap.php'; return true; }
if ($path === '/robots.txt') { require __DIR__ . '/robots.php'; return true; }

return false; // deixa o servidor entregar o arquivo normalmente
