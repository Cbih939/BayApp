<?php
declare(strict_types=1);

const REPORT_PRESETS = ['mes' => 'Este mês', '30' => 'Próximos 30 dias', '90' => 'Últimos 90 dias', 'ano' => 'Este ano'];
const REPORT_KINDS = ['financeiro' => 'Financeiro', 'tarefas' => 'Tarefas'];

/** Lê tipo de relatório, período e cliente da query string. Retorna ['kind','from','to','client','preset']. */
function report_filters(array $src): array
{
    $today = date('Y-m-d');
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $kind = (string) ($src['t'] ?? '');
    if (!isset(REPORT_KINDS[$kind]) || ($kind === 'financeiro' && !can('billing.view'))) $kind = can('billing.view') ? 'financeiro' : 'tarefas';
    $preset = (string) ($src['per'] ?? 'mes');
    if ($valid($src['de'] ?? null) && $valid($src['ate'] ?? null)) {
        [$from, $to, $preset] = [$src['de'], $src['ate'], 'custom'];
        if ($from > $to) [$from, $to] = [$to, $from];
    } else {
        [$from, $to] = match ($preset) {
            '30' => [$today, date('Y-m-d', strtotime('+30 day'))],
            '90' => [date('Y-m-d', strtotime('-90 day')), $today],
            'ano' => [date('Y-01-01'), date('Y-12-31')],
            default => [date('Y-m-01'), date('Y-m-t')],
        };
        if (!isset(REPORT_PRESETS[$preset])) $preset = 'mes';
    }
    return ['kind' => $kind, 'from' => $from, 'to' => $to, 'client' => (int) ($src['cliente'] ?? 0), 'preset' => $preset];
}

function report_query(array $f): string
{
    return http_build_query(['t' => $f['kind'], 'de' => $f['from'], 'ate' => $f['to'], 'cliente' => $f['client'] ?: '']);
}

/** Meses (YYYY-MM) do período, no máximo 36, todos presentes (mesmo sem movimento). */
function report_months(string $from, string $to): array
{
    $out = [];
    for ($m = substr($from, 0, 7); $m <= substr($to, 0, 7) && count($out) < 36; $m = date('Y-m', strtotime($m . '-01 +1 month'))) $out[] = $m;
    return $out;
}

/** Monta o relatório do tipo pedido: KPIs, gráficos de barras e tabelas (alimenta tela, PDF, CSV e WhatsApp). */
function report_build(array $f): array
{
    $cname = $f['client'] ? (string) val('SELECT nome FROM clients WHERE id = ?', [$f['client']]) : '';
    $r = ['titulo' => 'Relatório ' . ($f['kind'] === 'tarefas' ? 'de tarefas' : 'financeiro') . ($cname ? " — $cname" : ''),
        'periodo' => fmt_date($f['from']) . ' a ' . fmt_date($f['to']), 'kpis' => [], 'charts' => [], 'tables' => [], 'gerado' => date('d/m/Y H:i')];
    if ($f['kind'] === 'tarefas') report_tasks($f, $r); elseif (can('billing.view')) report_finance($f, $r);
    return $r;
}

function report_finance(array $f, array &$r): void
{
    $today = date('Y-m-d');
    [$from, $to, $cid] = [$f['from'], $f['to'], $f['client']];
    $fromT = $from . ' 00:00:00';
    $toT = $to . ' 23:59:59';
    $months = report_months($from, $to);
    $bp = $cid ? [$cid] : [];

    // Serviços pendentes, com as recorrências projetadas dentro do período
    $pending = rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.status = 'pendente'" . ($cid ? ' AND b.client_id = ?' : ''), $bp);
    $occ = [];   // [serviço, data]
    foreach ($pending as $b) foreach (bill_occurrences($b, $from, $to) as $d) $occ[] = [$b, $d];
    $due = array_sum(array_map(fn($o) => (float) $o[0]['valor'], $occ));
    $paid = rows('SELECT p.*, c.nome AS cliente, b.descricao FROM payments p JOIN clients c ON c.id = p.client_id JOIN billings b ON b.id = p.billing_id
        WHERE p.pago_em BETWEEN ? AND ?' . ($cid ? ' AND p.client_id = ?' : '') . ' ORDER BY p.pago_em', [$fromT, $toT, ...$bp]);
    $overdue = array_values(array_filter($pending, fn($b) => $b['vencimento'] < $today));
    $kp = rows("SELECT valor, periodicidade FROM contracts WHERE status = 'ativo' AND (fim IS NULL OR fim >= ?)" . ($cid ? ' AND client_id = ?' : ''), [$today, ...$bp]);
    $mrr = array_sum(array_map(fn($k) => (float) $k['valor'] / (REC_MONTHS[$k['periodicidade']] ?? 1), $kp));

    $r['kpis'][] = ['Clientes: a vencer no período', fmt_money($due), count($occ) . ' vencimento(s)'];
    $r['kpis'][] = ['Clientes: pago no período', fmt_money(array_sum(array_map(fn($p) => (float) $p['valor'], $paid))), count($paid) . ' pagamento(s)'];
    $r['kpis'][] = ['Clientes: em atraso (hoje)', fmt_money(array_sum(array_map(fn($b) => (float) $b['valor'], $overdue))), count($overdue) . ' serviço(s)'];
    $r['kpis'][] = ['Contratos ativos', (string) count($kp), 'valor mensal: ' . fmt_money($mrr)];
    if (!$cid) $r['kpis'][] = ['Clientes ativos', (string) (int) val("SELECT COUNT(*) FROM clients WHERE status = 'ativo'"), (int) val('SELECT COUNT(*) FROM clients WHERE criado_em BETWEEN ? AND ?', [$fromT, $toT]) . ' novo(s) no período'];

    $byM = array_fill_keys($months, 0.0);
    foreach ($paid as $p) { $m = substr($p['pago_em'], 0, 7); if (isset($byM[$m])) $byM[$m] += (float) $p['valor']; }
    $r['charts'][] = ['Pagamentos de clientes — pagos por mês', 'cols', array_combine(array_map('month_short', array_keys($byM)), array_values($byM)), true];
    $dueM = array_fill_keys($months, 0.0);
    foreach ($occ as [$b, $d]) { $m = substr($d, 0, 7); if (isset($dueM[$m])) $dueM[$m] += (float) $b['valor']; }
    $r['charts'][] = ['Pagamentos de clientes — em aberto por mês (previsto)', 'cols', array_combine(array_map('month_short', array_keys($dueM)), array_values($dueM)), true];

    $byType = [];
    foreach ($occ as [$b]) { $k = BILL_TYPES[$b['tipo']] ?? $b['tipo']; $byType[$k] = ($byType[$k] ?? 0) + (float) $b['valor']; }
    arsort($byType);
    $r['charts'][] = ['Em aberto por tipo de serviço', 'bar', $byType, true];
    if (!$cid) {
        $top = [];
        foreach ($occ as [$b]) $top[$b['cliente']] = ($top[$b['cliente']] ?? 0) + (float) $b['valor'];
        arsort($top);
        $r['charts'][] = ['Clientes com maior valor em aberto', 'bar', array_slice($top, 0, 8, true), true];
    }

    // Vencimentos internos da Bay Groups (só para quem gerencia): contas que a empresa paga
    if (!$cid && can('billing.edit')) {
        $ib = rows("SELECT * FROM internal_bills WHERE status = 'pendente'");
        $iocc = [];
        foreach ($ib as $b) foreach (bill_occurrences($b, $from, $to) as $d) $iocc[] = [$b, $d];
        $ipaid = rows('SELECT p.*, b.descricao, b.categoria FROM internal_payments p JOIN internal_bills b ON b.id = p.bill_id WHERE p.pago_em BETWEEN ? AND ? ORDER BY p.pago_em', [$fromT, $toT]);
        $r['kpis'][] = ['Internos: a pagar no período', fmt_money(array_sum(array_map(fn($o) => (float) $o[0]['valor'], $iocc))), count($iocc) . ' vencimento(s)'];
        $r['kpis'][] = ['Internos: pago no período', fmt_money(array_sum(array_map(fn($p) => (float) $p['valor'], $ipaid))), count($ipaid) . ' pagamento(s)'];
        $iM = array_fill_keys($months, 0.0);
        foreach ($iocc as [$b, $d]) { $m = substr($d, 0, 7); if (isset($iM[$m])) $iM[$m] += (float) $b['valor']; }
        $r['charts'][] = ['Vencimentos internos da Bay Groups — a pagar por mês', 'cols', array_combine(array_map('month_short', array_keys($iM)), array_values($iM)), true];
        usort($iocc, fn($a, $b) => strcmp($a[1], $b[1]));
        $r['tables'][] = ['Vencimentos internos a pagar no período', ['Vencimento', 'Categoria', 'Descrição', 'Valor', 'Situação'],
            array_map(fn($o) => [fmt_date($o[1]), INTERNAL_CATS[$o[0]['categoria']] ?? $o[0]['categoria'], $o[0]['descricao'], fmt_money($o[0]['valor']), $o[1] < $today ? 'Em atraso' : 'Pendente'], $iocc), [58, 120, 170, 70, 80]];
    }

    // Situação por cliente: início do contrato, último mês pago e próximo vencimento
    $sit = [];
    foreach (rows('SELECT id, nome FROM clients' . ($cid ? ' WHERE id = ?' : '') . ' ORDER BY nome', $bp) as $c) {
        $k = contract_active((int) $c['id']);
        $last = val('SELECT MAX(competencia) FROM payments WHERE client_id = ?', [$c['id']]);
        $nextD = val("SELECT MIN(vencimento) FROM billings WHERE client_id = ? AND status = 'pendente'", [$c['id']]);
        if (!$k && !$last && !$nextD) continue;
        $sit[] = [$c['nome'], $k ? fmt_date($k['inicio']) : '—', $k['plano'] ?? '—', $last ? month_label($last) : '—',
            $nextD ? month_label(substr($nextD, 0, 7)) . ' · ' . substr(fmt_date($nextD), 0, 5) : '—',
            !$nextD ? 'Sem pendências' : ($nextD < $today ? 'Em atraso' : 'Em dia')];
    }
    $r['tables'][] = ['Contrato e situação dos pagamentos por cliente', ['Cliente', 'Contrato desde', 'Pacote', 'Último mês pago', 'Próximo vencimento', 'Situação'], $sit, [105, 62, 80, 82, 100, 68]];

    $r['tables'][] = ['Pagamentos de clientes recebidos no período', ['Pago em', 'Cliente', 'Serviço', 'Competência', 'Valor'],
        array_map(fn($p) => [fmt_date(substr($p['pago_em'], 0, 10)), $p['cliente'], $p['descricao'], month_label($p['competencia']), fmt_money($p['valor'])], $paid), [58, 110, 150, 85, 70]];

    usort($occ, fn($a, $b) => strcmp($a[1], $b[1]));
    $r['tables'][] = ['Pagamentos de clientes a vencer no período', ['Vencimento', 'Cliente', 'Serviço', 'Tipo', 'Valor', 'Situação'],
        array_map(fn($o) => [fmt_date($o[1]), $o[0]['cliente'], $o[0]['descricao'], BILL_TYPES[$o[0]['tipo']] ?? $o[0]['tipo'], fmt_money($o[0]['valor']),
            $o[1] < $today ? 'Em atraso' : 'Pendente'], $occ), [58, 90, 150, 70, 62, 85]];
}

function report_tasks(array $f, array &$r): void
{
    $today = date('Y-m-d');
    [$from, $to, $cid] = [$f['from'], $f['to'], $f['client']];
    $fromT = $from . ' 00:00:00';
    $toT = $to . ' 23:59:59';
    $tc = $cid ? ' AND t.client_id = ?' : '';
    $tp = $cid ? [$cid] : [];

    $open = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito'$tc", $tp);
    $over = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito' AND t.prazo IS NOT NULL AND t.prazo < ?$tc", [$today, ...$tp]);
    $done = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status = 'feito' AND t.concluida_em BETWEEN ? AND ?$tc", [$fromT, $toT, ...$tp]);
    $noOwner = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito' AND t.assignee_id IS NULL$tc", $tp);
    $ck = row("SELECT COUNT(*) total, COALESCE(SUM(i.feito), 0) feitos FROM task_items i JOIN tasks t ON t.id = i.task_id WHERE t.status <> 'feito'$tc", $tp);
    $pct = (int) $ck['total'] ? (int) round(100 * $ck['feitos'] / $ck['total']) : 0;
    $r['kpis'][] = ['Tarefas em aberto', (string) $open, $over . ' em atraso'];
    $r['kpis'][] = ['Concluídas no período', (string) $done, ''];
    $r['kpis'][] = ['Checklist das tarefas abertas', $ck['total'] ? "$pct%" : '—', (int) $ck['feitos'] . ' de ' . (int) $ck['total'] . ' itens feitos'];
    $r['kpis'][] = ['Sem responsável', (string) $noOwner, 'em aberto'];

    $byStatus = [];
    foreach (TASK_STATUS as $k => $label) $byStatus[$label] = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status = ?$tc", [$k, ...$tp]);
    $r['charts'][] = ['Tarefas por status', 'bar', $byStatus, false];

    $byUser = [];
    foreach (rows("SELECT COALESCE(u.nome, 'Sem responsável') n, COUNT(*) c FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id WHERE t.status <> 'feito'$tc GROUP BY u.id, u.nome ORDER BY c DESC", $tp) as $x) $byUser[$x['n']] = (int) $x['c'];
    $r['charts'][] = ['Em aberto por responsável', 'bar', $byUser, false];

    $byM = array_fill_keys(report_months($from, $to), 0);
    foreach (rows("SELECT t.concluida_em d FROM tasks t WHERE t.status = 'feito' AND t.concluida_em BETWEEN ? AND ?$tc", [$fromT, $toT, ...$tp]) as $x) { $m = substr($x['d'], 0, 7); if (isset($byM[$m])) $byM[$m]++; }
    $r['charts'][] = ['Concluídas por mês', 'cols', array_combine(array_map('month_short', array_keys($byM)), array_values($byM)), false];

    $byPrio = [];
    foreach (TASK_PRIO as $k => $label) $byPrio[$label] = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito' AND t.prioridade = ?$tc", [$k, ...$tp]);
    $r['charts'][] = ['Em aberto por prioridade', 'bar', $byPrio, false];

    if (!$cid) {
        $byC = [];
        foreach (rows("SELECT COALESCE(c.nome, 'Sem cliente') n, COUNT(*) c FROM tasks t LEFT JOIN clients c ON c.id = t.client_id WHERE t.status <> 'feito' GROUP BY c.id, c.nome ORDER BY c DESC LIMIT 8") as $x) $byC[$x['n']] = (int) $x['c'];
        $r['charts'][] = ['Em aberto por cliente', 'bar', $byC, false];
    }

    $late = rows(TASK_SELECT . " WHERE t.status <> 'feito' AND t.prazo IS NOT NULL AND t.prazo < ?" . $tc . ' ORDER BY t.prazo', [$today, ...$tp]);
    $r['tables'][] = ['Tarefas em atraso', ['Prazo', 'Tarefa', 'Cliente', 'Responsável', 'Prioridade'],
        array_map(fn($t) => [fmt_date($t['prazo']), $t['titulo'], (string) $t['cliente'], (string) $t['responsavel'], TASK_PRIO[$t['prioridade']] ?? ''], $late), [55, 190, 100, 100, 70]];
}

function report_num(float $v, bool $money): string { return $money ? fmt_money($v) : (string) (int) $v; }

/** Texto para WhatsApp (negrito com *). */
function report_text(array $r): string
{
    $l = ['📊 *' . $r['titulo'] . '* — ' . setting('empresa_nome', 'BayApp'), 'Período: ' . $r['periodo'], ''];
    foreach ($r['kpis'] as [$k, $v, $s]) $l[] = "• $k: *$v*" . ($s !== '' ? " ($s)" : '');
    foreach ($r['charts'] as [$t, , $d, $money]) {
        if (!array_filter($d)) continue;
        $l[] = '';
        $l[] = "*$t*";
        foreach (array_slice($d, 0, 6, true) as $k => $v) $l[] = "– $k: " . report_num((float) $v, $money);
    }
    $l[] = '';
    $l[] = 'Gerado em ' . $r['gerado'];
    return implode("\n", $l);
}

/* ---------------- Gráficos em SVG (sem bibliotecas externas) ---------------- */
const REPORT_COLORS = ['#8b5cf6', '#34d399', '#fbbf24', '#fb923c', '#38bdf8', '#fb7185', '#a78bfa', '#94a3b8'];

function chart_empty(): string { return '<p class="muted rep-empty">Sem dados no período.</p>'; }

function chart_bar_svg(array $data, bool $money): string
{
    if (!array_filter($data)) return chart_empty();
    $max = max($data) ?: 1;
    $h = 30 * count($data);
    $o = '<svg class="rep-svg" viewBox="0 0 520 ' . $h . '" role="img">';
    $y = 0;
    foreach ($data as $k => $v) {
        $w = $v > 0 ? max(2, (int) round(250 * $v / $max)) : 0;
        $o .= '<text x="0" y="' . ($y + 19) . '" class="rep-t">' . e(mb_strimwidth((string) $k, 0, 22, '…')) . '</text>'
            . ($w ? '<rect x="150" y="' . ($y + 6) . '" width="' . $w . '" height="16" rx="5" fill="' . REPORT_COLORS[0] . '"/>' : '')
            . '<text x="' . (156 + $w) . '" y="' . ($y + 19) . '" class="rep-t rep-v">' . e(report_num((float) $v, $money)) . '</text>';
        $y += 30;
    }
    return $o . '</svg>';
}

/** Barras verticais para séries por mês (uma série só, sem legenda; valor no rótulo e na dica). */
function chart_columns_svg(array $data, bool $money): string
{
    if (!array_filter($data)) return chart_empty();
    $n = count($data);
    $max = max($data) ?: 1;
    [$x0, $x1, $base, $top] = [8, 512, 150, 18];
    $slot = ($x1 - $x0) / $n;
    $bw = (int) min(34, max(6, $slot * 0.62));
    $o = '<svg class="rep-svg" viewBox="0 0 520 176" role="img">'
        . '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $base . '" y2="' . $base . '" class="rep-axis"/>'
        . '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . ($top + ($base - $top) / 2) . '" y2="' . ($top + ($base - $top) / 2) . '" class="rep-grid"/>';
    $i = 0;
    foreach ($data as $k => $v) {
        $h = $v > 0 ? max(3, (int) round(($base - $top) * $v / $max)) : 0;
        $x = (int) round($x0 + $slot * $i + ($slot - $bw) / 2);
        $tip = e($k) . ': ' . e(report_num((float) $v, $money));
        if ($h) $o .= '<rect class="rep-col" x="' . $x . '" y="' . ($base - $h) . '" width="' . $bw . '" height="' . $h . '" rx="4" fill="' . REPORT_COLORS[0] . '"><title>' . $tip . '</title></rect>';
        else $o .= '<rect x="' . $x . '" y="' . ($base - 1) . '" width="' . $bw . '" height="1" fill="currentColor" class="rep-v"><title>' . $tip . '</title></rect>';
        if (($n <= 8 || $v == $max) && $v > 0) $o .= '<text x="' . ($x + $bw / 2) . '" y="' . ($base - $h - 5) . '" text-anchor="middle" class="rep-t rep-v rep-s">' . e(report_num((float) $v, $money)) . '</text>';
        $lab = ($n > 6 && preg_match('~^(\d\d)/\d\d(\d\d)$~', (string) $k, $mm)) ? $mm[1] . '/' . $mm[2] : (string) $k;   // 10/26: cabe em 12 colunas
        if ($n <= 12 || $i % 2 === 0) $o .= '<text x="' . ($x + $bw / 2) . '" y="168" text-anchor="middle" class="rep-t rep-v rep-s">' . e($lab) . '</text>';
        $i++;
    }
    return $o . '</svg>';
}

/* ---------------- CSV (abre direto no Excel pt-BR) ---------------- */
function report_csv(array $r): string
{
    $esc = function ($c): string {
        $c = (string) $c;
        if ($c !== '' && strpbrk($c[0], "=+-@\t\r") !== false) $c = "'" . $c;
        return '"' . str_replace('"', '""', $c) . '"';
    };
    $line = fn(array $cells) => implode(';', array_map($esc, $cells)) . "\r\n";
    $o = "\xEF\xBB\xBF" . $line([$r['titulo']]) . $line(['Período', $r['periodo']]) . $line(['Gerado em', $r['gerado']]) . "\r\n";
    $o .= $line(['Indicadores']) . $line(['Indicador', 'Valor', 'Detalhe']);
    foreach ($r['kpis'] as $k) $o .= $line($k);
    foreach ($r['charts'] as [$t, , $d, $money]) {
        $o .= "\r\n" . $line([$t]);
        foreach ($d as $k => $v) $o .= $line([$k, $money ? number_format((float) $v, 2, ',', '') : (string) (int) $v]);
    }
    foreach ($r['tables'] as [$t, $head, $rows]) {
        $o .= "\r\n" . $line([$t]) . $line($head);
        foreach ($rows as $row) $o .= $line($row);
    }
    return $o;
}

/* ---------------- PDF (gerador mínimo, sem dependências) ---------------- */
final class MiniPdf
{
    private array $pages = [];
    private string $cur = '';
    public float $y = 0;
    public const W = 595, H = 842, M = 40;

    public function __construct() { $this->newPage(); }

    public function newPage(): void
    {
        if ($this->cur !== '') $this->pages[] = $this->cur;
        $this->cur = '';
        $this->y = self::H - self::M;
    }

    public function ensure(float $h): void { if ($this->y - $h < self::M + 20) $this->newPage(); }

    private static function enc(string $s): string
    {
        $s = (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    public static function width(string $s, float $size, bool $bold = false): float { return mb_strlen($s) * $size * ($bold ? 0.56 : 0.52); }

    public function fit(string $s, float $max, float $size, bool $bold = false): string
    {
        $n = max(1, (int) floor($max / ($size * ($bold ? 0.56 : 0.52))));
        return mb_strlen($s) > $n ? mb_substr($s, 0, max(1, $n - 1)) . '…' : $s;
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0.1, 0.1, 0.15]): void
    {
        $this->cur .= sprintf("BT /%s %.1f Tf %.3f %.3f %.3f rg %.1f %.1f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, self::enc($s));
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf("%.3f %.3f %.3f rg %.1f %.1f %.1f %.1f re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    public function output(): string
    {
        $this->pages[] = $this->cur;
        $this->cur = '';
        $n = count($this->pages);
        $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($this->pages as $i => $c) {
            $c .= sprintf("BT /F1 8 Tf 0.5 0.5 0.55 rg %.1f 24 Td (%s) Tj ET\n", self::M, self::enc('Página ' . ($i + 1) . ' de ' . $n));
            $pid = 5 + $i * 2;
            $kids[] = "$pid 0 R";
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pid + 1) . ' 0 R >>';
            $objs[$pid + 1] = "<< /Length " . strlen($c) . " >>\nstream\n$c\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $id => $body) { $off[$id] = strlen($out); $out .= "$id 0 obj\n$body\nendobj\n"; }
        $x = strlen($out);
        $out .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $out .= sprintf("%010d 00000 n \n", $o);
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    }
}

function report_pdf(array $r): string
{
    $p = new MiniPdf();
    $purple = [0.545, 0.361, 0.965];
    $W = MiniPdf::W; $M = MiniPdf::M; $cw = $W - 2 * $M;
    $MH = MiniPdf::H - 90;
    $p->rect(0, $MH, $W, 90, [0.07, 0.07, 0.1]);
    $p->text($M, MiniPdf::H - 38, mb_strtoupper(setting('empresa_nome', 'BayApp')), 9, true, $purple);
    $p->text($M, MiniPdf::H - 62, $p->fit($r['titulo'], $cw, 20, true), 20, true, [1, 1, 1]);
    $p->text($M, MiniPdf::H - 80, 'Período: ' . $r['periodo'] . '   ·   Gerado em ' . $r['gerado'], 9, false, [0.7, 0.7, 0.78]);
    $p->y = $MH - 28;

    // KPIs em 2 colunas
    $bw = ($cw - 12) / 2;
    foreach (array_chunk($r['kpis'], 2) as $pair) {
        $p->ensure(54);
        foreach ($pair as $i => [$k, $v, $s]) {
            $x = $M + $i * ($bw + 12);
            $p->rect($x, $p->y - 46, $bw, 46, [0.95, 0.94, 0.99]);
            $p->rect($x, $p->y - 46, 3, 46, $purple);
            $p->text($x + 12, $p->y - 15, $p->fit($k, $bw - 20, 8.5), 8.5, false, [0.4, 0.4, 0.5]);
            $p->text($x + 12, $p->y - 33, $p->fit($v, $bw - 20, 15, true), 15, true);
            if ($s !== '') $p->text($x + $bw - 12 - MiniPdf::width($s, 8), $p->y - 33, $s, 8, false, [0.4, 0.4, 0.5]);
        }
        $p->y -= 58;
    }

    // Gráficos de barras (rosca vira barras no PDF)
    foreach ($r['charts'] as [$t, , $d, $money]) {
        $d = array_slice(array_filter($d), 0, 8, true);
        $p->ensure(40 + 18 * max(1, count($d)));
        $p->text($M, $p->y - 8, $t, 11, true);
        $p->y -= 22;
        if (!$d) { $p->text($M, $p->y - 8, 'Sem dados no período.', 9, false, [0.5, 0.5, 0.55]); $p->y -= 22; continue; }
        $max = max($d);
        $total = array_sum($d);
        foreach ($d as $k => $v) {
            $bwid = max(2, 230 * $v / $max);
            $p->text($M, $p->y - 10, $p->fit((string) $k, 150, 9), 9);
            $p->rect($M + 160, $p->y - 12, $bwid, 12, $purple);
            $lbl = report_num((float) $v, $money) . ' (' . round(100 * $v / $total) . '%)';
            $p->text($M + 166 + $bwid, $p->y - 10, $lbl, 9, false, [0.3, 0.3, 0.4]);
            $p->y -= 18;
        }
        $p->y -= 14;
    }

    // Tabelas
    foreach ($r['tables'] as $tbl) {
        [$t, $head, $rows] = $tbl;
        $p->ensure(60);
        $p->text($M, $p->y - 8, $t . ' (' . count($rows) . ')', 11, true);
        $p->y -= 22;
        if (!$rows) { $p->text($M, $p->y - 8, 'Nada a listar.', 9, false, [0.5, 0.5, 0.55]); $p->y -= 24; continue; }
        $n = count($head);
        $wts = $tbl[3] ?? array_fill(0, $n, 1);
        $scale = $cw / array_sum($wts);
        $wts = array_map(fn($w) => $w * $scale, $wts);
        $header = function () use ($p, $head, $wts, $M, $cw) {
            $p->rect($M, $p->y - 16, $cw, 16, [0.07, 0.07, 0.1]);
            $x = $M + 4;
            foreach ($head as $i => $h) { $p->text($x, $p->y - 11, $p->fit($h, $wts[$i] - 6, 8, true), 8, true, [1, 1, 1]); $x += $wts[$i]; }
            $p->y -= 16;
        };
        $header();
        foreach ($rows as $ri => $row) {
            if ($p->y - 16 < MiniPdf::M + 20) { $p->newPage(); $header(); }
            if ($ri % 2) $p->rect($M, $p->y - 15, $cw, 15, [0.96, 0.96, 0.98]);
            $x = $M + 4;
            foreach ($row as $i => $c) { $p->text($x, $p->y - 11, $p->fit((string) $c, $wts[$i] - 6, 8), 8); $x += $wts[$i]; }
            $p->y -= 15;
        }
        $p->y -= 18;
    }
    return $p->output();
}
