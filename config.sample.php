<?php
// Gerado automaticamente pelo install.php. Copie para config.php se preferir configurar manualmente.
return [
    'db' => [
        'driver' => 'mysql',            // 'mysql' (Hostinger) ou 'sqlite' (testes locais)
        'host'   => 'localhost',
        'name'   => 'u123456789_bayapp',
        'user'   => 'u123456789_bayapp',
        'pass'   => 'SUA_SENHA',
        // 'path' => __DIR__ . '/storage/bayapp.sqlite',   // apenas para sqlite
    ],
    'app_key'  => 'BASE64_32_BYTES',    // chave que criptografa as senhas dos clientes. NÃO perca!
    'timezone' => 'America/Sao_Paulo',
];
