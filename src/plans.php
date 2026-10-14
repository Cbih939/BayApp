<?php
declare(strict_types=1);

/* ---------------- Valores e datas ---------------- */

function parse_money(string $v): ?float
{
    $v = trim($v);
    if ($v === '') return null;
    return (float) str_replace(['.', ','], ['', '.'], preg_replace('/[^\d.,]/', '', $v));
}

function valid_date(string $d): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

function month_label(string $ym): string { return MONTHS_PT[(int) substr($ym, 5, 2)] . '/' . substr($ym, 0, 4); }
function month_short(string $ym): string { return substr($ym, 5, 2) . '/' . substr($ym, 0, 4); }

/* ---------------- Pacotes e contrato ---------------- */

function plan_items(int $planId): array
{
    return rows('SELECT tipo, quantidade, descricao FROM plan_items WHERE plan_id = ? ORDER BY id', [$planId]);
}

function plan_item_label(array $it): string
{
    return $it['descricao'] ?: (DELIVERY_TYPES[$it['tipo']] ?? $it['tipo']);
}

/** Contrato em vigor do cliente: ativo e dentro da vigência. */
function contract_active(int $cid): ?array
{
    return row("SELECT k.*, p.nome AS plano, p.descricao AS plano_desc FROM contracts k LEFT JOIN plans p ON p.id = k.plan_id
        WHERE k.client_id = ? AND k.status = 'ativo' AND (k.fim IS NULL OR k.fim >= ?) ORDER BY k.inicio DESC, k.id DESC LIMIT 1", [$cid, date('Y-m-d')]);
}

/** O que o pacote ativo garante no mês e quanto já foi usado (só os formatos que o calendário conta). */
function plan_usage(int $cid, string $ym): array
{
    $k = contract_active($cid);
    if (!$k || !$k['plan_id']) return [];
    $used = [];
    foreach (rows("SELECT formato, COUNT(*) n FROM posts WHERE client_id = ? AND status <> 'cancelado' AND dia >= ? AND dia <= ? GROUP BY formato",
        [$cid, "$ym-01", "$ym-31"]) as $r) $used[$r['formato']] = (int) $r['n'];
    $out = [];
    foreach (plan_items((int) $k['plan_id']) as $it) {
        $out[] = $it + ['label' => plan_item_label($it), 'usado' => isset(POST_FORMATS[$it['tipo']]) ? ($used[$it['tipo']] ?? 0) : null];
    }
    return $out;
}

/** Barras de uso do pacote (HTML). */
function render_usage(array $usage): void
{
    if (!$usage) return;
    echo '<div class="usage">';
    foreach ($usage as $u) {
        $q = max(1, (int) $u['quantidade']);
        if ($u['usado'] === null) { echo '<div class="usage-i"><b>' . e($u['label']) . '</b><span class="chip">' . $q . '/mês</span></div>'; continue; }
        $pct = min(100, (int) round(100 * $u['usado'] / $q));
        $cls = $u['usado'] > $q ? 'over' : ($u['usado'] === $q ? 'full' : '');
        echo '<div class="usage-i ' . $cls . '" title="' . (int) $u['usado'] . ' de ' . $q . ' neste mês"><div class="usage-h"><b>' . e($u['label']) . '</b><span>' . (int) $u['usado'] . '/' . $q . '</span></div>'
            . '<span class="bar"><i style="width:' . $pct . '%"></i></span></div>';
    }
    echo '</div>';
}

/* ---------------- Pagamentos (histórico) ---------------- */

/** Registra um pagamento; a competência é o mês do vencimento quitado. */
function record_payment(array $b, ?int $uid): void
{
    insert('payments', ['billing_id' => $b['id'], 'client_id' => $b['client_id'], 'competencia' => substr($b['vencimento'], 0, 7),
        'vencimento' => $b['vencimento'], 'valor' => $b['valor'], 'pago_em' => now(), 'user_id' => $uid]);
}

/** Datas de vencimento de um serviço pendente dentro de [from, to], projetando as recorrências. */
function bill_occurrences(array $b, string $from, string $to): array
{
    $out = [];
    $d = $b['vencimento'];
    $step = REC_MONTHS[$b['recorrencia']] ?? 0;
    for ($i = 0; $i < 400 && $d <= $to; $i++) {
        if ($d >= $from) $out[] = $d;
        if (!$step) break;
        $d = add_months($d, $step);
    }
    return $out;
}

/** Instalações antigas: reconstrói o histórico a partir do que já estava pago. */
function backfill_payments(): void
{
    foreach (rows('SELECT * FROM billings WHERE pago_em IS NOT NULL') as $b) {
        if (val('SELECT COUNT(*) FROM payments WHERE billing_id = ?', [$b['id']])) continue;
        $step = REC_MONTHS[$b['recorrencia']] ?? 0;
        $comp = $step ? add_months($b['vencimento'], -$step) : $b['vencimento'];
        insert('payments', ['billing_id' => $b['id'], 'client_id' => $b['client_id'], 'competencia' => substr($comp, 0, 7), 'vencimento' => $comp,
            'valor' => $b['valor'], 'pago_em' => $b['pago_em'], 'user_id' => null]);
    }
}

/** "1 ano e 3 meses", "5 meses", "12 dias"… desde o início do contrato. */
function contract_age(string $start): string
{
    $d = (new DateTime($start))->diff(new DateTime('today'));
    if ($d->invert) return 'começa em ' . fmt_date($start);
    $parts = [];
    if ($d->y) $parts[] = $d->y . ($d->y > 1 ? ' anos' : ' ano');
    if ($d->m) $parts[] = $d->m . ($d->m > 1 ? ' meses' : ' mês');
    if (!$parts) return $d->d . ($d->d === 1 ? ' dia' : ' dias');
    return implode(' e ', $parts);
}

/** Pagamentos antigos sem a data do vencimento: usa o dia do vencimento do serviço dentro da competência. */
function backfill_payment_dates(): void
{
    foreach (rows('SELECT p.id, p.competencia, b.vencimento FROM payments p JOIN billings b ON b.id = p.billing_id WHERE p.vencimento IS NULL') as $r) {
        $dim = (int) date('t', strtotime($r['competencia'] . '-01'));
        update('payments', (int) $r['id'], ['vencimento' => $r['competencia'] . '-' . sprintf('%02d', min((int) substr($r['vencimento'], 8, 2), $dim))]);
    }
}
