<?php
/**
 * Configurações do site Douglas Souza Arquitetura.
 * Ajuste os valores abaixo se necessário.
 */
return [
    // Caminho do banco de dados SQLite (fica fora do alcance público via .htaccess)
    'db_path' => __DIR__ . '/data/site.sqlite',

    // Pasta onde as fotos enviadas pelo painel são salvas
    'upload_dir' => __DIR__ . '/uploads',
    'upload_url' => 'uploads',

    // Tamanho máximo das imagens (lado maior, em pixels) e qualidade JPEG
    'image_max' => 1920,
    'thumb_max' => 640,
    'jpeg_quality' => 85,

    // E-mail que recebe as mensagens do formulário de contato (vazio = só grava no painel)
    'contact_email' => 'contato@arquitetodouglas.com.br',

    // Fuso horário
    'timezone' => 'America/Sao_Paulo',
];
