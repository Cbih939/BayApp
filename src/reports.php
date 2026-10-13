<?php
declare(strict_types=1);

const REPORT_PRESETS = ['mes' => 'Este mês', '30' => 'Próximos 30 dias', '90' => 'Últimos 90 dias', 'ano' => 'Este ano'];

/** Lê período e cliente da query string. Retorna ['from','to','client','preset']. */
function report_filters(array $src): array
{
    $today = date('Y-m-d');
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
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
    return ['from' => $from, 'to' => $to, 'client' => (int) ($src['cliente'] ?? 0), 'preset' => $preset];
}

function report_query(array $f): string
{
    return http_build_query(['de' => $f['from'], 'ate' => $f['to'], 'cliente' => $f['client'] ?: '']);
}

/**
 * Monta o relatório: KPIs, gráficos e tabelas (mesma estrutura alimenta tela, PDF, CSV e WhatsApp).
 * Dados financeiros só entram para quem tem permissão billing.view.
 */
function report_build(array $f): array
{
    $today = date('Y-m-d');
    [$from, $to, $cid] = [$f['from'], $f['to'], $f['client']];
    $fromT = $from . ' 00:00:00';
    $toT = $to . ' 23:59:59';
    $cname = $cid ? (string) val('SELECT nome FROM clients WHERE id = ?', [$cid]) : '';
    $r = ['titulo' => 'Relatório' . ($cname ? " — $cname" : ''), 'periodo' => fmt_date($from) . ' a ' . fmt_date($to),
        'kpis' => [], 'charts' => [], 'tables' => [], 'gerado' => date('d/m/Y H:i')];

    $bc = $cid ? ' AND b.client_id = ?' : '';
    $bp = $cid ? [$cid] : [];
    $tc = $cid ? ' AND t.client_id = ?' : '';

    if (can('billing.view')) {
        $sum = fn(string $w, array $p) => (float) val("SELECT COALESCE(SUM(b.valor),0) FROM billings b WHERE $w$bc", [...$p, ...$bp]);
        $cnt = fn(string $w, array $p) => (int) val("SELECT COUNT(*) FROM billings b WHERE $w$bc", [...$p, ...$bp]);
        $r['kpis'][] = ['A vencer no período', fmt_money($sum("b.status='pendente' AND b.vencimento BETWEEN ? AND ?", [$from, $to])), $cnt("b.status='pendente' AND b.vencimento BETWEEN ? AND ?", [$from, $to]) . ' vencimento(s)'];
        $r['kpis'][] = ['Pago no período', fmt_money($sum("b.status='pago' AND b.pago_em BETWEEN ? AND ?", [$fromT, $toT])), $cnt("b.status='pago' AND b.pago_em BETWEEN ? AND ?", [$fromT, $toT]) . ' pagamento(s)'];
        $r['kpis'][] = ['Em atraso (hoje)', fmt_money($sum("b.status='pendente' AND b.vencimento < ?", [$today])), $cnt("b.status='pendente' AND b.vencimento < ?", [$today]) . ' vencimento(s)'];

        $byType = [];
        foreach (rows("SELECT b.tipo, COALESCE(SUM(b.valor),0) v FROM billings b WHERE b.vencimento BETWEEN ? AND ?$bc GROUP BY b.tipo ORDER BY v DESC", [$from, $to, ...$bp]) as $x) {
            $byType[BILL_TYPES[$x['tipo']] ?? $x['tipo']] = (float) $x['v'];
        }
        $r['charts'][] = ['Valor por tipo de vencimento', 'donut', $byType, true];

        $byMonth = [];
        foreach (rows("SELECT SUBSTR(b.vencimento,1,7) m, COALESCE(SUM(b.valor),0) v FROM billings b WHERE b.vencimento BETWEEN ? AND ?$bc GROUP BY m ORDER BY m", [$from, $to, ...$bp]) as $x) {
            $byMonth[substr($x['m'], 5, 2) . '/' . substr($x['m'], 0, 4)] = (float) $x['v'];
        }
        $r['charts'][] = ['Valor dos vencimentos por mês', 'bar', $byMonth, true];

        if (!$cid) {
            $top = [];
            foreach (rows("SELECT c.nome, COALESCE(SUM(b.valor),0) v FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.vencimento BETWEEN ? AND ? GROUP BY c.id, c.nome ORDER BY v DESC LIMIT 8", [$from, $to]) as $x) {
                $top[$x['nome']] = (float) $x['v'];
            }
            $r['charts'][] = ['Clientes com maior valor no período', 'bar', $top, true];
        }

        $list = rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.vencimento BETWEEN ? AND ?$bc ORDER BY b.vencimento", [$from, $to, ...$bp]);
        $r['tables'][] = ['Vencimentos do período', ['Vencimento', 'Cliente', 'Descrição', 'Tipo', 'Valor', 'Situação'],
            array_map(fn($b) => [fmt_date($b['vencimento']), $b['cliente'], $b['descricao'], BILL_TYPES[$b['tipo']] ?? $b['tipo'], fmt_money($b['valor']),
                $b['status'] === 'pago' ? 'Pago' : ($b['vencimento'] < $today ? 'Em atraso' : 'Pendente')], $list)];
    }

    // Tarefas
    $tp = $cid ? [$cid] : [];
    $open = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito'$tc", $tp);
    $over = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status <> 'feito' AND t.prazo IS NOT NULL AND t.prazo < ?$tc", [$today, ...$tp]);
    $done = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status = 'feito' AND t.concluida_em BETWEEN ? AND ?$tc", [$fromT, $toT, ...$tp]);
    $r['kpis'][] = ['Tarefas em aberto', (string) $open, $over . ' em atraso'];
    $r['kpis'][] = ['Tarefas concluídas no período', (string) $done, ''];

    $byStatus = [];
    foreach (TASK_STATUS as $k => $label) $byStatus[$label] = (int) val("SELECT COUNT(*) FROM tasks t WHERE t.status = ?$tc", [$k, ...$tp]);
    $r['charts'][] = ['Tarefas por status', 'donut', $byStatus, false];
    $byUser = [];
    foreach (rows("SELECT COALESCE(u.nome, 'Sem responsável') n, COUNT(*) c FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id WHERE t.status <> 'feito'$tc GROUP BY u.id, u.nome ORDER BY c DESC", $tp) as $x) $byUser[$x['n']] = (int) $x['c'];
    $r['charts'][] = ['Tarefas em aberto por responsável', 'bar', $byUser, false];

    $late = rows(TASK_SELECT . " WHERE t.status <> 'feito' AND t.prazo IS NOT NULL AND t.prazo < ?" . $tc . ' ORDER BY t.prazo', [$today, ...$tp]);
    $r['tables'][] = ['Tarefas em atraso', ['Prazo', 'Tarefa', 'Cliente', 'Responsável', 'Prioridade'],
        array_map(fn($t) => [fmt_date($t['prazo']), $t['titulo'], (string) $t['cliente'], (string) $t['responsavel'], TASK_PRIO[$t['prioridade']] ?? ''], $late)];

    // Clientes
    if (!$cid) {
        $r['kpis'][] = ['Clientes ativos', (string) (int) val("SELECT COUNT(*) FROM clients WHERE status = 'ativo'"), (int) val('SELECT COUNT(*) FROM clients WHERE criado_em BETWEEN ? AND ?', [$fromT, $toT]) . ' novo(s) no período'];
    }
    return $r;
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
        $w = max(2, (int) round(250 * $v / $max));
        $o .= '<text x="0" y="' . ($y + 19) . '" class="rep-t">' . e(mb_strimwidth((string) $k, 0, 22, '…')) . '</text>'
            . '<rect x="150" y="' . ($y + 6) . '" width="' . $w . '" height="16" rx="5" fill="' . REPORT_COLORS[0] . '"/>'
            . '<text x="' . (156 + $w) . '" y="' . ($y + 19) . '" class="rep-t rep-v">' . e(report_num((float) $v, $money)) . '</text>';
        $y += 30;
    }
    return $o . '</svg>';
}

function chart_donut_svg(array $data, bool $money): string
{
    $total = array_sum($data);
    if ($total <= 0) return chart_empty();
    $o = '<svg class="rep-svg" viewBox="0 0 520 ' . max(160, 26 * count($data) + 10) . '" role="img"><g transform="rotate(-90 80 80)">';
    $circ = 2 * M_PI * 55;
    $off = 0.0;
    $i = 0;
    foreach ($data as $v) {
        $len = $circ * $v / $total;
        if ($len > 0) $o .= '<circle cx="80" cy="80" r="55" fill="none" stroke="' . REPORT_COLORS[$i % 8] . '" stroke-width="26" stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '"/>';
        $off += $len;
        $i++;
    }
    $o .= '</g>';
    $i = 0;
    $y = 20;
    foreach ($data as $k => $v) {
        $pct = round(100 * $v / $total);
        $o .= '<rect x="180" y="' . ($y - 11) . '" width="12" height="12" rx="3" fill="' . REPORT_COLORS[$i % 8] . '"/>'
            . '<text x="200" y="' . $y . '" class="rep-t">' . e(mb_strimwidth((string) $k, 0, 24, '…')) . ' — ' . e(report_num((float) $v, $money)) . ' (' . $pct . '%)</text>';
        $y += 26;
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
    foreach ($r['tables'] as [$t, $head, $rows]) {
        $p->ensure(60);
        $p->text($M, $p->y - 8, $t . ' (' . count($rows) . ')', 11, true);
        $p->y -= 22;
        if (!$rows) { $p->text($M, $p->y - 8, 'Nada a listar.', 9, false, [0.5, 0.5, 0.55]); $p->y -= 24; continue; }
        $n = count($head);
        $wts = $n === 6 ? [58, 90, 150, 70, 62, 85] : [55, 190, 100, 100, 70];
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
