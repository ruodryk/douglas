<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
// Avisos de funções obsoletas em versões futuras do PHP não devem aparecer na página
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
$CONFIG = require ROOT . '/config.php';
date_default_timezone_set($CONFIG['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('ds_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require ROOT . '/inc/helpers.php';
require ROOT . '/inc/images.php';
require ROOT . '/inc/db.php';
require ROOT . '/inc/site.php';

db(); // garante schema + conteúdo inicial
