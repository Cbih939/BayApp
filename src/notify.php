<?php
declare(strict_types=1);

const ALERT_MARCOS = [7, 15, 30];
/** Lembretes ao cliente: dias antes do vencimento (negativo = dias depois de vencer). */
const CLIENT_MARCOS_DEFAULT = '5,0,-3';
const WA_PROVIDERS = [
    'nenhum' => 'Desativado',
    'zapi' => 'Z-API',
    'evolution' => 'Evolution API',
    'meta' => 'WhatsApp Cloud API (Meta)',
    'webhook' => 'Webhook genérico (Make, n8n…)',
    'callmebot' => 'CallMeBot (só para a equipe)',
];

function http_request(string $url, ?string $json = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true]);
    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
    }
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, (string) $body, $err];
}

/** Registra cada envio (e-mail ou WhatsApp) para o histórico em Configurações. */
function notify_log(string $canal, string $destino, string $assunto, bool $ok, string $det): void
{
    try {
        insert('notify_log', ['canal' => $canal, 'destino' => mb_substr($destino, 0, 250), 'assunto' => mb_substr($assunto, 0, 200),
            'status' => $ok ? 'ok' : 'erro', 'detalhe' => mb_substr($det, 0, 250), 'criado_em' => now()]);
    } catch (Throwable $e) {
    }
}

/** URL pública do app (gravada ao abrir Configurações, para o cron saber montar links). */
function app_url(): string
{
    return rtrim(setting('app_url'), '/');
}

function alert_recipients(): array
{
    $list = array_filter(array_map('trim', explode(',', setting('alert_emails'))));
    if (!$list) $list = array_column(rows("SELECT email FROM users WHERE papel = 'admin' AND ativo = 1"), 'email');
    return array_values(array_filter($list, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
}

/* ---------- e-mail ---------- */

function smtp_configured(): bool
{
    return setting('smtp_host') !== '';
}

function mail_from(): string
{
    $from = setting('mail_from') ?: (smtp_configured() && filter_var(setting('smtp_user'), FILTER_VALIDATE_EMAIL) ? setting('smtp_user') : '');
    if ($from) return $from;
    $host = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? parse_url(app_url(), PHP_URL_HOST) ?: 'localhost'));
    return 'alertas@' . $host;
}

/** Último erro de envio de e-mail (para mostrar no teste e no histórico). */
function mail_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

function send_mail(array $to, string $subject, string $html): bool
{
    $to = array_values(array_filter($to, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
    if (!$to) { mail_error('sem destinatários'); return false; }
    $from = mail_from();
    $name = setting('empresa_nome', 'BayApp');
    if (smtp_configured()) {
        [$ok, $det] = smtp_send($to, $subject, $html, $from, $name);
    } else {
        $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
            . 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\nReply-To: $from\r\n";
        $ok = @mail(implode(',', $to), '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $h);
        $det = $ok ? 'enviado (mail do PHP)' : 'o servidor recusou o mail() — configure o SMTP';
    }
    mail_error($ok ? '' : $det);
    notify_log('email', implode(', ', $to), $subject, $ok, $det);
    return $ok;
}

/** Monta a mensagem MIME (texto + HTML). */
function mime_message(array $to, string $subject, string $html, string $from, string $name): string
{
    $b = 'b' . bin2hex(random_bytes(12));
    $text = trim(html_entity_decode(strip_tags(preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|tr|h\d|li)>/i'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text));
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    return 'Date: ' . date('r') . "\r\n"
        . 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\n"
        . 'To: ' . implode(', ', $to) . "\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(10)) . "@$domain>\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
        . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
        . "--$b--\r\n";
}

/** Envio por SMTP (Hostinger: smtp.hostinger.com, 465/SSL). Retorna [ok, detalhe]. */
function smtp_send(array $to, string $subject, string $html, string $from, string $name): array
{
    $host = setting('smtp_host');
    $port = (int) setting('smtp_port', '465') ?: 465;
    $sec = setting('smtp_secure', 'ssl');
    $user = setting('smtp_user');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, "sem conexão com $host:$port ($errstr)"];
    stream_set_timeout($fp, 20);
    $cmd = function (?string $line, array $expect, string $label = '') use ($fp): string {
        if ($line !== null) fwrite($fp, $line . "\r\n");
        $res = '';
        while (($l = fgets($fp, 515)) !== false) { $res .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        if (!in_array((int) substr($res, 0, 3), $expect, true)) {
            throw new RuntimeException(($label ?: ($line === null ? 'resposta' : strtok($line, ' :'))) . ': ' . trim($res ?: 'sem resposta'));
        }
        return $res;
    };
    try {
        $cmd(null, [220], 'conexão');
        $ehlo = 'EHLO ' . (parse_url(app_url(), PHP_URL_HOST) ?: 'localhost');
        $cmd($ehlo, [250]);
        if ($sec === 'tls') {
            $cmd('STARTTLS', [220]);
            $m = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!stream_socket_enable_crypto($fp, true, $m)) throw new RuntimeException('falha ao iniciar TLS');
            $cmd($ehlo, [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334], 'usuário');
            $cmd(base64_encode(decrypt_secret(setting('smtp_pass'))), [235], 'login/senha');
        }
        $cmd("MAIL FROM:<$from>", [250], 'remetente');
        foreach ($to as $t) $cmd("RCPT TO:<$t>", [250, 251], "destinatário $t");
        $cmd('DATA', [354]);
        $msg = preg_replace('/^\./m', '..', mime_message($to, $subject, $html, $from, $name));
        $cmd($msg . "\r\n.", [250], 'envio');
        try { $cmd('QUIT', [221]); } catch (RuntimeException $e) {}
        return [true, 'enviado (SMTP)'];
    } catch (RuntimeException $e) {
        return [false, $e->getMessage()];
    } finally {
        fclose($fp);
    }
}

/* ---------- WhatsApp ---------- */

/** Lista de números (separados por vírgula/linha), só dígitos; número brasileiro sem DDI ganha 55. */
function wa_numbers(?string $list): array
{
    $out = [];
    foreach (preg_split('/[,;\n]+/', (string) $list) as $n) {
        $d = only_digits($n);
        if (strlen($d) === 10 || strlen($d) === 11) $d = '55' . $d;
        if (strlen($d) >= 12) $out[] = $d;
    }
    return array_values(array_unique($out));
}

/** O provedor tem as credenciais necessárias. */
function whatsapp_ready(): bool
{
    return match (setting('wa_provider', 'nenhum')) {
        'zapi' => setting('wa_instance') !== '' && setting('wa_apikey') !== '',
        'evolution' => setting('wa_webhook_url') !== '' && setting('wa_instance') !== '' && setting('wa_apikey') !== '',
        'meta' => setting('wa_instance') !== '' && setting('wa_apikey') !== '',
        'webhook' => setting('wa_webhook_url') !== '',
        'callmebot' => setting('wa_apikey') !== '',
        default => false,
    };
}

/** Pronto para avisar a equipe (provedor + ao menos um número da equipe). */
function whatsapp_configured(): bool
{
    return whatsapp_ready() && wa_numbers(setting('wa_phone')) !== [];
}

/** CallMeBot só entrega no número de quem gerou a chave; os demais enviam para qualquer número. */
function whatsapp_reaches_clients(): bool
{
    return whatsapp_ready() && setting('wa_provider') !== 'callmebot';
}

/**
 * Envia para os números da equipe ($to = null) ou para os números informados.
 * Retorna [ok, detalhe] — ok só se todos os envios deram certo.
 */
function send_whatsapp(string $text, ?string $to = null): array
{
    if (!whatsapp_ready()) return [false, 'WhatsApp não configurado'];
    $nums = wa_numbers($to ?? setting('wa_phone'));
    if (!$nums) return [false, $to === null ? 'nenhum número da equipe' : 'número inválido'];
    $ok = true;
    $errs = [];
    foreach ($nums as $n) {
        [$sent, $det] = wa_send_one($n, $text);
        notify_log('whatsapp', $n, mb_substr(strtok($text, "\n") ?: $text, 0, 200), $sent, $det);
        if (!$sent) { $ok = false; $errs[] = "$n: $det"; }
    }
    return [$ok, $ok ? 'enviado' : implode('; ', $errs)];
}

function wa_send_one(string $phone, string $text): array
{
    $key = setting('wa_apikey');
    $inst = setting('wa_instance');
    switch (setting('wa_provider')) {
        case 'callmebot':
            [$code, $body, $err] = http_request('https://api.callmebot.com/whatsapp.php?phone=' . $phone . '&text=' . rawurlencode($text) . '&apikey=' . rawurlencode($key));
            break;
        case 'zapi':
            $hdr = setting('wa_client_token') !== '' ? ['Client-Token: ' . setting('wa_client_token')] : [];
            [$code, $body, $err] = http_request('https://api.z-api.io/instances/' . rawurlencode($inst) . '/token/' . rawurlencode($key) . '/send-text',
                json_encode(['phone' => $phone, 'message' => $text]), $hdr);
            break;
        case 'evolution':
            [$code, $body, $err] = http_request(rtrim(setting('wa_webhook_url'), '/') . '/message/sendText/' . rawurlencode($inst),
                json_encode(['number' => $phone, 'text' => $text]), ['apikey: ' . $key]);
            break;
        case 'meta':
            // Fora da janela de 24h a Meta só entrega modelos aprovados: o texto vai como variável {{1}} do modelo.
            $tpl = setting('wa_meta_template');
            $payload = ['messaging_product' => 'whatsapp', 'to' => $phone];
            $payload += $tpl !== ''
                ? ['type' => 'template', 'template' => ['name' => $tpl, 'language' => ['code' => setting('wa_meta_lang', 'pt_BR') ?: 'pt_BR'],
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => wa_flatten($text)]]]]]]
                : ['type' => 'text', 'text' => ['body' => $text]];
            [$code, $body, $err] = http_request('https://graph.facebook.com/v21.0/' . rawurlencode($inst) . '/messages', json_encode($payload), ['Authorization: Bearer ' . $key]);
            break;
        default: // webhook genérico — recebe {"phone","message"}
            $hdr = $key !== '' ? ['Authorization: Bearer ' . $key] : [];
            [$code, $body, $err] = http_request(setting('wa_webhook_url'), json_encode(['phone' => $phone, 'number' => $phone, 'message' => $text, 'text' => $text]), $hdr);
    }
    $ok = $code >= 200 && $code < 300;
    return [$ok, $ok ? 'enviado' : ($err ?: "HTTP $code" . ($body !== '' ? ' — ' . mb_substr(trim(strip_tags($body)), 0, 140) : ''))];
}

/** Variáveis de modelo da Meta não aceitam quebra de linha. */
function wa_flatten(string $text): string
{
    return mb_substr(preg_replace('/\s{2,}/', ' ', str_replace(["\r", "\n", "\t"], [' ', ' · ', ' '], str_replace('*', '', $text))), 0, 1000);
}

/* ---------- marcos e modelos de e-mail ---------- */

/** Menor marco que contém os dias restantes (marcos em ordem crescente); null se ainda falta mais que o maior. */
function marco_for(int $days, array $marcos = ALERT_MARCOS): ?int
{
    foreach ($marcos as $m) if ($days <= $m) return $m;
    return null;
}

/** Marcos dos lembretes ao cliente, em ordem crescente (ex.: [-3, 0, 5]). */
function client_marcos(): array
{
    return parse_marcos(setting('cli_marcos', CLIENT_MARCOS_DEFAULT));
}

function parse_marcos(string $list): array
{
    $m = [];
    foreach (preg_split('/[,;\s]+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $v) {
        if (preg_match('/^-?\d+$/', $v) && abs((int) $v) <= 60) $m[] = (int) $v;
    }
    $m = array_values(array_unique($m ?: array_map('intval', explode(',', CLIENT_MARCOS_DEFAULT))));
    sort($m);
    return $m;
}

function when_label(int $d): string
{
    return $d < 0 ? 'venceu há ' . abs($d) . ' dia(s)' : ($d === 0 ? 'vence hoje' : "vence em $d dia(s)");
}

/** Moldura dos e-mails no visual do app (claro, roxo e rosa). */
function mail_layout(string $title, string $inner, string $foot = ''): string
{
    $name = e(setting('empresa_nome', 'BayApp'));
    return '<div style="background:#efeef8;padding:28px 12px;font-family:Inter,Arial,sans-serif;color:#1f2142">'
        . '<div style="max-width:560px;margin:auto;background:#ffffff;border:1px solid #e7e5f2;border-radius:18px;overflow:hidden">'
        . '<div style="background:#8b5cf6;background:linear-gradient(135deg,#8b5cf6,#ec4899);padding:18px 28px;color:#ffffff;font-weight:700;letter-spacing:.08em;font-size:13px;text-transform:uppercase">' . $name . '</div>'
        . '<div style="padding:26px 28px 28px"><h2 style="margin:0 0 16px;font-size:21px;color:#1f2142">' . e($title) . '</h2>' . $inner
        . ($foot !== '' ? '<p style="color:#767996;font-size:12px;margin:22px 0 0">' . $foot . '</p>' : '') . '</div></div>'
        . '<p style="text-align:center;color:#9a9cb5;font-size:11px;margin:14px 0 0">Mensagem automática de ' . $name . '</p></div>';
}

function mail_button(string $url, string $label): string
{
    return '<p style="margin:22px 0 0"><a href="' . e($url) . '" style="display:inline-block;background:#8b5cf6;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#ffffff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px">' . e($label) . '</a></p>';
}

function mail_rows(array $items, bool $showClient = true): string
{
    $rows = '';
    foreach ($items as $b) {
        $d = $b['dias'];
        $color = $d < 0 ? '#e11d48' : ($d <= 7 ? '#ec4899' : '#8b5cf6');
        $meta = array_filter([$showClient ? $b['cliente'] : null, $b['tipo_label'] ?? (BILL_TYPES[$b['tipo']] ?? $b['tipo']), fmt_money($b['valor'])]);
        $rows .= '<tr><td style="padding:14px 0;border-bottom:1px solid #eeecf6">'
            . '<div style="font-weight:700;font-size:15px;color:#1f2142">' . e($b['descricao']) . '</div>'
            . '<div style="color:#767996;font-size:13px;margin-top:2px">' . e(implode(' · ', $meta)) . '</div>'
            . '<div style="margin-top:8px"><span style="background:' . $color . ';color:#ffffff;border-radius:99px;padding:4px 11px;font-size:12px;font-weight:600">'
            . e(when_label($d)) . ' — ' . fmt_date($b['vencimento']) . '</span></div></td></tr>';
    }
    return '<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse">' . $rows . '</table>';
}

function mail_template(string $title, array $items): string
{
    return mail_layout($title, mail_rows($items), 'Programe o pagamento com antecedência para evitar suspensões.');
}

/* ---------- rotinas ---------- */

/**
 * Verifica vencimentos e envia alertas (30/15/7 dias) por e-mail e WhatsApp.
 * Cobre os vencimentos internos da Bay Groups e os pagamentos dos clientes.
 * Cada marco é enviado uma única vez por vencimento/canal.
 */
function run_alerts(bool $dry = false): array
{
    $report = ['verificados' => 0, 'alertas' => 0, 'email' => null, 'whatsapp' => null, 'itens' => []];
    // Fontes: [tabela de alertas, coluna do vencimento na tabela de alertas, linhas]
    $sources = [
        ['internal_alert_log', 'i', array_map(fn($b) => $b + ['cliente' => 'Bay Groups (interno)', 'tipo_label' => INTERNAL_CATS[$b['categoria']] ?? $b['categoria']],
            rows("SELECT * FROM internal_bills WHERE status = 'pendente' ORDER BY vencimento"))],
        ['alert_log', 'c', rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.status = 'pendente' ORDER BY b.vencimento")],
    ];
    $pend = ['email' => [], 'whatsapp' => []];
    foreach ($sources as [$table, $src, $bills]) {
        foreach ($bills as $b) {
            $report['verificados']++;
            $b['dias'] = days_until($b['vencimento']);
            $m = marco_for($b['dias']);
            if ($m === null) continue;
            $col = $src === 'i' ? 'bill_id' : 'billing_id';
            foreach (['email' => 'avisar_email', 'whatsapp' => 'avisar_whatsapp'] as $canal => $flag) {
                if (!$b[$flag]) continue;
                if ($canal === 'whatsapp' && !whatsapp_configured()) continue;
                $done = val("SELECT COUNT(*) FROM $table WHERE $col = ? AND vencimento = ? AND marco = ? AND canal = ? AND status = 'ok'", [$b['id'], $b['vencimento'], $m, $canal]);
                if (!$done) $pend[$canal][] = $b + ['marco' => $m, '_src' => $src];
            }
        }
    }
    $report['itens'] = array_values(array_unique(array_merge(array_column($pend['email'], 'descricao'), array_column($pend['whatsapp'], 'descricao'))));
    $report['alertas'] = count($report['itens']);
    if ($dry) return $report;

    $log = function (array $items, string $canal, bool $ok, string $det) {
        foreach ($items as $b) {
            $internal = $b['_src'] === 'i';
            insert($internal ? 'internal_alert_log' : 'alert_log', [($internal ? 'bill_id' : 'billing_id') => $b['id'], 'vencimento' => $b['vencimento'], 'marco' => $b['marco'],
                'canal' => $canal, 'status' => $ok ? 'ok' : 'erro', 'detalhe' => mb_substr($det, 0, 250), 'enviado_em' => now()]);
        }
    };

    if ($pend['email']) {
        $ok = send_mail(alert_recipients(), '⏰ ' . count($pend['email']) . ' vencimento(s) próximos — ' . setting('empresa_nome', 'BayApp'),
            mail_template('Vencimentos para programar', $pend['email']));
        $log($pend['email'], 'email', $ok, $ok ? 'enviado' : mail_error());
        $report['email'] = $ok ? 'enviado' : 'falhou: ' . mail_error();
    }
    if ($pend['whatsapp']) {
        $lines = ["⏰ *Vencimentos próximos* — " . setting('empresa_nome', 'BayApp')];
        foreach ($pend['whatsapp'] as $b) {
            $d = $b['dias'];
            $lines[] = '• ' . $b['descricao'] . ' (' . $b['cliente'] . ') — ' . ($d < 0 ? 'VENCIDO' : ($d === 0 ? 'vence HOJE' : "em $d dia(s)")) . ' · ' . fmt_date($b['vencimento']) . ' · ' . fmt_money($b['valor']);
        }
        [$ok, $det] = send_whatsapp(implode("\n", $lines));
        $log($pend['whatsapp'], 'whatsapp', $ok, $det);
        $report['whatsapp'] = $ok ? 'enviado' : 'falhou: ' . $det;
    }
    return $report;
}

/**
 * Lembretes de pagamento para os próprios clientes (e-mail e WhatsApp cadastrados no cliente).
 * Ligado em Configurações; cada serviço pode ser excluído pelo “Lembrar o cliente”.
 * Uma mensagem por cliente/canal, agrupando os serviços do mesmo marco; cada marco sai uma única vez.
 */
function run_client_alerts(bool $dry = false): array
{
    $report = ['clientes' => 0, 'email' => 0, 'whatsapp' => 0, 'erros' => 0, 'desligado' => setting('cli_notify') !== '1'];
    if ($report['desligado']) return $report;
    $marcos = client_marcos();
    $useMail = setting('cli_email', '1') === '1';
    $useWa = setting('cli_whatsapp', '1') === '1' && whatsapp_reaches_clients();
    $by = [];
    $bills = rows("SELECT b.*, c.nome AS cliente, c.email AS c_email, c.whatsapp AS c_whats, c.telefone AS c_tel FROM billings b
        JOIN clients c ON c.id = b.client_id WHERE b.status = 'pendente' AND b.avisar_cliente = 1 AND c.status = 'ativo' ORDER BY b.vencimento");
    foreach ($bills as $b) {
        $b['dias'] = days_until($b['vencimento']);
        $m = marco_for($b['dias'], $marcos);
        // atrasos antigos não disparam lembrete (evita uma enxurrada ao ligar a função)
        if ($m === null || $b['dias'] < $marcos[0] - 7) continue;
        $chans = [];
        if ($useMail && filter_var($b['c_email'], FILTER_VALIDATE_EMAIL)) $chans['cli_email'] = $b['c_email'];
        if ($useWa && ($num = wa_numbers($b['c_whats'] ?: $b['c_tel']))) $chans['cli_whatsapp'] = $num[0];
        foreach ($chans as $canal => $dest) {
            $done = val("SELECT COUNT(*) FROM alert_log WHERE billing_id = ? AND vencimento = ? AND marco = ? AND canal = ? AND status = 'ok'", [$b['id'], $b['vencimento'], $m, $canal]);
            if (!$done) $by[$b['client_id']][$canal][] = $b + ['marco' => $m, 'dest' => $dest];
        }
    }
    $report['clientes'] = count($by);
    if ($dry) return $report;

    $empresa = setting('empresa_nome', 'BayApp');
    $portal = app_url() !== '' ? app_url() . '/portal.php?p=pagamentos' : '';
    foreach ($by as $chans) {
        foreach ($chans as $canal => $items) {
            $first = explode(' ', trim($items[0]['cliente']))[0];
            if ($canal === 'cli_email') {
                $late = min(array_column($items, 'dias')) < 0;
                $intro = '<p style="margin:0 0 6px;font-size:15px">Olá, ' . e($first) . '!</p><p style="margin:0 0 8px;color:#4b4e6d;font-size:14px">'
                    . ($late ? 'Identificamos pagamento(s) em aberto:' : 'Passando para lembrar do(s) próximo(s) vencimento(s):') . '</p>';
                $ok = send_mail([$items[0]['dest']], ($late ? 'Pagamento em aberto' : 'Lembrete de pagamento') . " — $empresa",
                    mail_layout($late ? 'Pagamento em aberto' : 'Lembrete de pagamento', $intro . mail_rows($items, false) . ($portal ? mail_button($portal, 'Ver no portal') : ''),
                        'Se o pagamento já foi feito, desconsidere esta mensagem. Dúvidas? É só responder.'));
                $det = $ok ? 'enviado' : mail_error();
            } else {
                $lines = ["Olá, $first! 👋", "Lembrete de pagamento — *$empresa*", ''];
                foreach ($items as $b) $lines[] = '• ' . $b['descricao'] . ' — ' . fmt_money($b['valor']) . ' · ' . when_label($b['dias']) . ' (' . fmt_date($b['vencimento']) . ')';
                if ($portal) { $lines[] = ''; $lines[] = "Boletos e comprovantes: $portal"; }
                $lines[] = '';
                $lines[] = 'Se já pagou, pode desconsiderar. 😉';
                [$ok, $det] = send_whatsapp(implode("\n", $lines), $items[0]['dest']);
            }
            foreach ($items as $b) {
                insert('alert_log', ['billing_id' => $b['id'], 'vencimento' => $b['vencimento'], 'marco' => $b['marco'], 'canal' => $canal,
                    'status' => $ok ? 'ok' : 'erro', 'detalhe' => mb_substr($det, 0, 250), 'enviado_em' => now()]);
            }
            $ok ? $report[$canal === 'cli_email' ? 'email' : 'whatsapp']++ : $report['erros']++;
        }
    }
    return $report;
}
