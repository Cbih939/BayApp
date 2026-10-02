<?php
declare(strict_types=1);

const ALERT_MARCOS = [7, 15, 30];

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

function alert_recipients(): array
{
    $list = array_filter(array_map('trim', explode(',', setting('alert_emails'))));
    if (!$list) $list = array_column(rows("SELECT email FROM users WHERE papel = 'admin' AND ativo = 1"), 'email');
    return array_values(array_filter($list, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
}

function send_mail(array $to, string $subject, string $html): bool
{
    if (!$to) return false;
    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = setting('mail_from') ?: 'alertas@' . $host;
    $name = setting('empresa_nome', 'BayApp');
    $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
        . 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\nReply-To: $from\r\n";
    return @mail(implode(',', $to), '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $h);
}

function whatsapp_configured(): bool
{
    return setting('wa_provider', 'nenhum') !== 'nenhum' && only_digits(setting('wa_phone')) !== '';
}

/** Retorna [ok, detalhe]. */
function send_whatsapp(string $text): array
{
    $prov = setting('wa_provider', 'nenhum');
    $phone = only_digits(setting('wa_phone'));
    if ($prov === 'nenhum' || $phone === '') return [false, 'WhatsApp não configurado'];

    if ($prov === 'callmebot') {
        $u = 'https://api.callmebot.com/whatsapp.php?phone=' . $phone . '&text=' . rawurlencode($text) . '&apikey=' . rawurlencode(setting('wa_apikey'));
        [$code, , $err] = http_request($u);
        return [$code >= 200 && $code < 300, $err ?: "HTTP $code"];
    }
    // webhook genérico (Z-API, Evolution API, Make, n8n...) — recebe {"phone","message"}
    $hook = setting('wa_webhook_url');
    if ($hook === '') return [false, 'URL do webhook vazia'];
    $hdr = setting('wa_apikey') !== '' ? ['Authorization: Bearer ' . setting('wa_apikey')] : [];
    [$code, , $err] = http_request($hook, json_encode(['phone' => $phone, 'number' => $phone, 'message' => $text, 'text' => $text]), $hdr);
    return [$code >= 200 && $code < 300, $err ?: "HTTP $code"];
}

/** Menor marco (7/15/30) que contém os dias restantes; null se faltam mais de 30. */
function marco_for(int $days): ?int
{
    foreach (ALERT_MARCOS as $m) if ($days <= $m) return $m;
    return null;
}

function mail_template(string $title, array $items): string
{
    $rows = '';
    foreach ($items as $b) {
        $d = $b['dias'];
        $when = $d < 0 ? 'VENCIDO há ' . abs($d) . ' dia(s)' : ($d === 0 ? 'vence HOJE' : "vence em $d dia(s)");
        $rows .= '<tr><td style="padding:14px 0;border-bottom:1px solid #2a2a35">'
            . '<div style="font-weight:700;color:#fff;font-size:15px">' . e($b['descricao']) . '</div>'
            . '<div style="color:#a1a1b5;font-size:13px">' . e($b['cliente']) . ' · ' . e(BILL_TYPES[$b['tipo']] ?? $b['tipo']) . ' · ' . fmt_money($b['valor']) . '</div>'
            . '<div style="margin-top:6px"><span style="background:#7c3aed;color:#fff;border-radius:99px;padding:3px 10px;font-size:12px">'
            . e($when) . ' — ' . fmt_date($b['vencimento']) . '</span></div></td></tr>';
    }
    return '<div style="background:#09090b;padding:28px;font-family:Arial,sans-serif"><div style="max-width:560px;margin:auto;background:#121217;border:1px solid #2a2a35;border-radius:16px;padding:28px">'
        . '<div style="color:#a78bfa;font-weight:700;letter-spacing:.1em;font-size:12px">' . e(setting('empresa_nome', 'BAYAPP')) . '</div>'
        . '<h2 style="color:#fff;margin:6px 0 16px">' . e($title) . '</h2><table width="100%" cellspacing="0">' . $rows . '</table>'
        . '<p style="color:#7d7d92;font-size:12px;margin-top:20px">Programe o pagamento com antecedência para evitar suspensões.</p></div></div>';
}

/**
 * Verifica vencimentos e envia alertas (30/15/7 dias) por e-mail e WhatsApp.
 * Cada marco é enviado uma única vez por vencimento/canal.
 */
function run_alerts(bool $dry = false): array
{
    $report = ['verificados' => 0, 'alertas' => 0, 'email' => null, 'whatsapp' => null, 'itens' => []];
    $bills = rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id
                   WHERE b.status = 'pendente' ORDER BY b.vencimento");
    $pend = ['email' => [], 'whatsapp' => []];
    foreach ($bills as $b) {
        $report['verificados']++;
        $b['dias'] = days_until($b['vencimento']);
        $m = marco_for($b['dias']);
        if ($m === null) continue;
        foreach (['email' => 'avisar_email', 'whatsapp' => 'avisar_whatsapp'] as $canal => $flag) {
            if (!$b[$flag]) continue;
            if ($canal === 'whatsapp' && !whatsapp_configured()) continue;
            $done = val("SELECT COUNT(*) FROM alert_log WHERE billing_id = ? AND vencimento = ? AND marco = ? AND canal = ? AND status = 'ok'",
                [$b['id'], $b['vencimento'], $m, $canal]);
            if (!$done) $pend[$canal][] = $b + ['marco' => $m];
        }
    }
    $report['itens'] = array_values(array_unique(array_merge(array_column($pend['email'], 'descricao'), array_column($pend['whatsapp'], 'descricao'))));
    $report['alertas'] = count($report['itens']);
    if ($dry) return $report;

    $log = function (array $items, string $canal, bool $ok, string $det) {
        foreach ($items as $b) {
            insert('alert_log', ['billing_id' => $b['id'], 'vencimento' => $b['vencimento'], 'marco' => $b['marco'],
                'canal' => $canal, 'status' => $ok ? 'ok' : 'erro', 'detalhe' => $det, 'enviado_em' => now()]);
        }
    };

    if ($pend['email']) {
        $ok = send_mail(alert_recipients(), '⏰ ' . count($pend['email']) . ' vencimento(s) próximos — ' . setting('empresa_nome', 'BayApp'),
            mail_template('Vencimentos para programar', $pend['email']));
        $log($pend['email'], 'email', $ok, $ok ? 'enviado' : 'falha no envio / sem destinatários');
        $report['email'] = $ok ? 'enviado' : 'falhou';
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
