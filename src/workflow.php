<?php
declare(strict_types=1);

/*
 * Fluxo integrado: contrato → pacote → entregas do mês → produção → aprovação do cliente → publicação.
 * - Cada ciclo mensal de um contrato ativo gera as entregas previstas no pacote (posts em rascunho no calendário).
 * - A aprovação vale para uma versão do conteúdo: mudar título, legenda ou arte depois de aprovado cria uma
 *   nova versão e devolve o post para a aprovação do cliente.
 * - Tudo fica no histórico do post (post_events).
 */

/** Status que só podem ser usados depois da aprovação do cliente. */
const POST_APPROVED = ['aguardando', 'agendado', 'concluido'];
/** Status que o cliente enxerga no portal. */
const POST_CLIENT_VISIBLE = ['aprov_cli', 'ajustes', 'aguardando', 'agendado', 'concluido'];
const POST_EVENT_LABELS = ['criado' => 'Criado', 'gerado' => 'Gerado pelo pacote', 'versao' => 'Nova versão', 'status' => 'Status alterado',
    'enviado' => 'Enviado para aprovação do cliente', 'aprovado' => 'Aprovado', 'ajustes' => 'Ajustes solicitados'];

function approval_required(): bool
{
    return setting('aprovacao_cliente', '1') === '1';
}

/** Registra um evento no histórico do post. $who = [nome, tipo] (tipo: equipe, cliente ou sistema). */
function post_event(int $postId, int $versao, string $tipo, string $detalhe = '', ?array $who = null): void
{
    $u = user();
    [$nome, $kind] = $who ?? [$u['nome'] ?? 'Sistema', $u ? 'equipe' : 'sistema'];
    insert('post_events', ['post_id' => $postId, 'versao' => $versao, 'tipo' => $tipo, 'detalhe' => $detalhe === '' ? null : mb_substr($detalhe, 0, 2000),
        'autor' => mb_substr((string) $nome, 0, 120), 'autor_tipo' => $kind, 'criado_em' => now()]);
}

/** Histórico de vários posts de uma vez: [post_id => [eventos, do mais antigo ao mais novo]]. */
function post_events_for(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $out = [];
    foreach (rows('SELECT post_id, versao, tipo, detalhe, autor, autor_tipo, criado_em FROM post_events WHERE post_id IN (' . implode(',', $ids) . ') ORDER BY id') as $e) {
        $out[(int) $e['post_id']][] = $e + ['label' => POST_EVENT_LABELS[$e['tipo']] ?? $e['tipo']];
    }
    return $out;
}

/** A versão atual do post foi aprovada? */
function post_is_approved(array $p): bool
{
    return (bool) val("SELECT COUNT(*) FROM post_events WHERE post_id = ? AND versao = ? AND tipo = 'aprovado'", [$p['id'], $p['versao']]);
}

/** Bloqueia publicar ou agendar sem a aprovação da versão atual. Retorna a mensagem de erro ou null. */
function post_status_block(array $p, string $new): ?string
{
    if (!approval_required() || !in_array($new, POST_APPROVED, true) || post_is_approved($p)) return null;
    return 'Este conteúdo ainda não foi aprovado pelo cliente' . ((int) $p['versao'] > 1 ? ' (versão ' . (int) $p['versao'] . ')' : '')
        . '. Envie para "Aprovação do cliente" ou registre a aprovação recebida por outro canal.';
}

/** Muda o status registrando o histórico e avisando quem precisa saber. */
function post_set_status(array $p, string $new, string $detalhe = '', bool $log = true): void
{
    if ($p['status'] === $new) return;
    update('posts', (int) $p['id'], ['status' => $new, 'publicado_em' => $new === 'concluido' ? now() : null, 'atualizado_em' => now()]);
    if ($new === 'aprov_cli') {
        post_event((int) $p['id'], (int) $p['versao'], 'enviado', $detalhe);
        notify_approval_request($p);
    } elseif ($log) {
        post_event((int) $p['id'], (int) $p['versao'], 'status', (POST_STATUS[$p['status']] ?? $p['status']) . ' → ' . (POST_STATUS[$new] ?? $new) . ($detalhe !== '' ? " · $detalhe" : ''));
    }
}

/** Avisa os acessos ativos do portal que há conteúdo esperando aprovação. */
function notify_approval_request(array $p): void
{
    $to = array_column(rows('SELECT email FROM client_users WHERE client_id = ? AND ativo = 1', [$p['client_id']]), 'email');
    if (!$to) return;
    $titulo = $p['titulo'] ?: (POST_FORMATS[$p['formato']] ?? 'Conteúdo');
    $link = app_url() !== '' ? app_url() . '/portal.php?p=aprovacoes' : '';
    send_mail($to, 'Conteúdo aguardando sua aprovação — ' . setting('empresa_nome', 'BayApp'), mail_layout('Conteúdo para aprovar',
        '<p style="margin:0 0 8px">Há um conteúdo esperando a sua aprovação:</p><p style="margin:0;font-size:16px"><b>' . e($titulo) . '</b><br><span style="color:#767996;font-size:13px">'
        . e((POST_CHANNELS[$p['canal']][0] ?? $p['canal']) . ' · ' . (POST_FORMATS[$p['formato']] ?? $p['formato']) . ' · ' . fmt_date($p['dia'])) . '</span></p>'
        . ($link ? mail_button($link, 'Ver e aprovar') : ''), 'Você pode aprovar ou pedir ajustes direto no portal.'));
}

/** Avisa a equipe sobre a decisão do cliente (e-mails de alerta + quem criou o post). */
function notify_approval_decision(array $p, string $decisao, string $comentario, string $quem): void
{
    $to = alert_recipients();
    if ($p['criado_por'] && ($e = val('SELECT email FROM users WHERE id = ? AND ativo = 1', [$p['criado_por']]))) $to[] = $e;
    $to = array_values(array_unique($to));
    $cliente = (string) val('SELECT nome FROM clients WHERE id = ?', [$p['client_id']]);
    $titulo = $p['titulo'] ?: (POST_FORMATS[$p['formato']] ?? 'Conteúdo');
    $ok = $decisao === 'aprovado';
    send_mail($to, ($ok ? '✅ Aprovado: ' : '✏️ Ajustes pedidos: ') . $titulo . " — $cliente", mail_layout($ok ? 'Conteúdo aprovado' : 'Cliente pediu ajustes',
        '<p style="margin:0 0 8px"><b>' . e($quem) . '</b> (' . e($cliente) . ') ' . ($ok ? 'aprovou' : 'pediu ajustes em') . ':</p><p style="margin:0 0 10px;font-size:16px"><b>' . e($titulo) . '</b> · ' . fmt_date($p['dia']) . '</p>'
        . ($comentario !== '' ? '<p style="margin:0;padding:12px 14px;background:#f4f2fc;border-radius:10px">' . nl2br(e($comentario)) . '</p>' : '')
        . (app_url() !== '' ? mail_button(app_url() . '/index.php?p=cliente&id=' . (int) $p['client_id'] . '&tab=t-cal&m=' . substr($p['dia'], 0, 7), 'Abrir no calendário') : '')));
}

/* ---------------- entregas do pacote ---------------- */

/** Dias úteis do mês a partir de hoje (ou do dia 1, para meses futuros). */
function delivery_days(string $ym): array
{
    $first = "$ym-01";
    $last = date('Y-m-t', strtotime($first));
    $from = max($first, date('Y-m-d'));
    $days = [];
    for ($d = $from; $d <= $last; $d = date('Y-m-d', strtotime("$d +1 day"))) if ((int) date('N', strtotime($d)) <= 5) $days[] = $d;
    return $days ?: [$last];
}

/**
 * Cria no calendário as entregas que faltam no mês, conforme o pacote do contrato.
 * Conta o que já existe no mês (de qualquer origem), então pode ser chamada de novo sem duplicar.
 * Retorna quantos posts foram criados.
 */
function generate_deliveries(array $k, string $ym): int
{
    if (!$k['plan_id'] || $k['status'] !== 'ativo' || $k['inicio'] > date('Y-m-t', strtotime("$ym-01")) || ($k['fim'] && $k['fim'] < "$ym-01")) return 0;
    $cid = (int) $k['client_id'];
    $have = [];
    foreach (rows("SELECT formato, COUNT(*) n FROM posts WHERE client_id = ? AND status <> 'cancelado' AND dia >= ? AND dia <= ? GROUP BY formato", [$cid, "$ym-01", date('Y-m-t', strtotime("$ym-01"))]) as $r) $have[$r['formato']] = (int) $r['n'];
    $todo = [];
    foreach (plan_items((int) $k['plan_id']) as $it) {
        if (!isset(POST_FORMATS[$it['tipo']])) continue;
        $q = (int) $it['quantidade'];
        for ($n = ($have[$it['tipo']] ?? 0) + 1; $n <= $q; $n++) $todo[] = [$it, $n, $q];
    }
    if (!$todo) return 0;
    $days = delivery_days($ym);
    $u = user();
    foreach ($todo as $i => [$it, $n, $q]) {
        $dia = $days[(int) floor($i * count($days) / count($todo))];
        $id = insert('posts', ['client_id' => $cid, 'dia' => $dia, 'hora' => null, 'canal' => 'instagram', 'formato' => $it['tipo'],
            'titulo' => (POST_FORMATS[$it['tipo']] ?? $it['tipo']) . " $n de $q", 'legenda' => null, 'midia_url' => null, 'status' => 'rascunho',
            'versao' => 1, 'origem' => 'pacote', 'criado_por' => $u['id'] ?? null, 'criado_em' => now(), 'atualizado_em' => now()]);
        post_event($id, 1, 'gerado', 'Pacote ' . ($k['plano'] ?? '') . ' · ' . month_label($ym));
    }
    return count($todo);
}

function cycle_mark(int $contractId, string $ym, int $n): void
{
    if (val('SELECT COUNT(*) FROM contract_cycles WHERE contract_id = ? AND competencia = ?', [$contractId, $ym])) {
        q('UPDATE contract_cycles SET gerados = gerados + ? WHERE contract_id = ? AND competencia = ?', [$n, $contractId, $ym]);
    } else {
        insert('contract_cycles', ['contract_id' => $contractId, 'competencia' => $ym, 'gerados' => $n, 'criado_em' => now()]);
    }
}

/** Rotina do cron: abre o ciclo do mês de cada contrato ativo com pacote, uma única vez por mês. Contratos encerrados não geram ciclos. */
function run_delivery_cycles(): array
{
    $ym = date('Y-m');
    $r = ['contratos' => 0, 'entregas' => 0];
    foreach (rows("SELECT k.*, p.nome AS plano FROM contracts k JOIN plans p ON p.id = k.plan_id WHERE k.status = 'ativo' AND (k.fim IS NULL OR k.fim >= ?)", ["$ym-01"]) as $k) {
        if (val('SELECT COUNT(*) FROM contract_cycles WHERE contract_id = ? AND competencia = ?', [$k['id'], $ym])) continue;
        $n = generate_deliveries($k, $ym);
        cycle_mark((int) $k['id'], $ym, $n);
        $r['contratos']++;
        $r['entregas'] += $n;
    }
    return $r;
}

/** Entregas do mês além do que o pacote prevê (demandas extras, que podem virar orçamento adicional). */
function plan_extras(int $cid, string $ym): array
{
    $out = [];
    foreach (plan_usage($cid, $ym) as $u) if ($u['usado'] !== null && $u['usado'] > (int) $u['quantidade']) $out[] = $u['label'] . ': ' . ($u['usado'] - (int) $u['quantidade']) . ' a mais';
    return $out;
}
