<?php
declare(strict_types=1);

function app_key(): string
{
    $k = base64_decode((string) cfg('app_key', ''), true);
    if ($k === false || strlen($k) !== 32) throw new RuntimeException('app_key inválida em config.php');
    return $k;
}

/** AES-256-GCM. Formato: base64(iv[12] . tag[16] . cipher) */
function encrypt_secret(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $c);
}

function decrypt_secret(?string $blob): string
{
    if (!$blob) return '';
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) return '';
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}
