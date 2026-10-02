<?php
/**
 * Rotina diária de alertas de vencimento.
 * Hostinger > hPanel > Avançado > Cron Jobs (1x ao dia, ex.: 08:00):
 *   /usr/bin/php /home/SEU_USUARIO/domains/SEU_DOMINIO/public_html/cron.php
 * Ou via URL:  https://seudominio.com/cron.php?token=SEU_TOKEN  (token em Configurações)
 */
require __DIR__ . '/src/bootstrap.php';

if (!$CONFIG) { http_response_code(503); exit("Não instalado\n"); }
if (PHP_SAPI !== 'cli') {
    $tok = setting('cron_token');
    if ($tok === '' || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
$r = run_alerts();
audit('cron', "verificados={$r['verificados']} alertas={$r['alertas']}");
echo date('c'), " | verificados: {$r['verificados']} | alertas: {$r['alertas']} | email: ", $r['email'] ?? '-', ' | whatsapp: ', $r['whatsapp'] ?? '-', "\n";
