<?php
declare(strict_types=1);

/* Gerador de QR Code (modo byte, correção L, versões 1 a 10) para ativar a verificação em duas etapas sem enviar o segredo a serviços externos. */

function qr_gf_mul(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z & 0xFF;
}

function qr_rs_remainder(array $data, int $degree): array
{
    $div = array_fill(0, $degree, 0);
    $div[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $div[$j] = qr_gf_mul($div[$j], $root);
            if ($j + 1 < $degree) $div[$j] ^= $div[$j + 1];
        }
        $root = qr_gf_mul($root, 2);
    }
    $rem = array_fill(0, $degree, 0);
    foreach ($data as $b) {
        $f = $b ^ array_shift($rem);
        $rem[] = 0;
        foreach ($div as $i => $d) $rem[$i] ^= qr_gf_mul($d, $f);
    }
    return $rem;
}

/** Matriz de módulos [y][x] (true = escuro). */
function qr_matrix(string $text): array
{
    $eccPerBlock = [1 => 7, 10, 15, 20, 26, 18, 20, 24, 30, 18];
    $numBlocks = [1 => 1, 1, 1, 1, 1, 2, 2, 2, 2, 4];
    $len = strlen($text);
    $raw = static function (int $v): int {
        $r = (16 * $v + 128) * $v + 64;
        if ($v >= 2) { $n = intdiv($v, 7) + 2; $r -= (25 * $n - 10) * $n - 55; if ($v >= 7) $r -= 36; }
        return $r;
    };
    for ($ver = 1; ; $ver++) {
        if ($ver > 10) throw new RuntimeException('Texto longo demais para o QR Code.');
        $total = intdiv($raw($ver), 8);
        $capacity = $total - $eccPerBlock[$ver] * $numBlocks[$ver];
        if (4 + ($ver < 10 ? 8 : 16) + 8 * $len <= 8 * $capacity) break;
    }
    // dados
    $bits = '0100' . str_pad(decbin($len), $ver < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    $bits .= str_repeat('0', min(4, 8 * $capacity - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $data = array_map('bindec', str_split($bits, 8));
    for ($pad = 0xEC; count($data) < $capacity; $pad ^= 0xEC ^ 0x11) $data[] = $pad;
    // blocos e correção de erros, intercalados
    $blocks = $numBlocks[$ver];
    $ecc = $eccPerBlock[$ver];
    $short = $blocks - $total % $blocks;
    $shortLen = intdiv($total, $blocks);
    $all = [];
    for ($i = 0, $k = 0; $i < $blocks; $i++) {
        $n = $shortLen - $ecc + ($i < $short ? 0 : 1);
        $d = array_slice($data, $k, $n);
        $k += $n;
        $rem = qr_rs_remainder($d, $ecc);
        if ($i < $short) $d[] = 0;   // posição vaga, pulada ao intercalar
        $all[] = array_merge($d, $rem);
    }
    $code = [];
    for ($i = 0; $i <= $shortLen; $i++) {
        foreach ($all as $j => $b) if ($i !== $shortLen - $ecc || $j >= $short) $code[] = $b[$i];
    }
    // padrões fixos
    $size = 4 * $ver + 17;
    $m = array_fill(0, $size, array_fill(0, $size, false));
    $fn = $m;
    $set = static function (int $x, int $y, bool $dark) use (&$m, &$fn): void { $m[$y][$x] = $dark; $fn[$y][$x] = true; };
    for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $x = $cx + $dx; $y = $cy + $dy;
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) { $d = max(abs($dx), abs($dy)); $set($x, $y, $d !== 2 && $d !== 4); }
        }
    }
    if ($ver > 1) {
        $n = intdiv($ver, 7) + 2;
        $step = intdiv($ver * 8 + $n * 3 + 5, $n * 4 - 4) * 2;
        $pos = [6];
        for ($i = $n - 1, $p = $size - 7; $i >= 1; $i--, $p -= $step) $pos[$i] = $p;
        ksort($pos);
        $pos = array_values($pos);
        foreach ($pos as $a => $ax) foreach ($pos as $b => $ay) {
            if (($a === 0 && $b === 0) || ($a === 0 && $b === $n - 1) || ($a === $n - 1 && $b === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
        }
    }
    // formato (correção L, máscara 0)
    $f = (1 << 3) | 0;
    $rem = $f;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    $fb = (($f << 10) | $rem) ^ 0x5412;
    $bit = static fn(int $v, int $i): bool => (($v >> $i) & 1) === 1;
    for ($i = 0; $i <= 5; $i++) $set(8, $i, $bit($fb, $i));
    $set(8, 7, $bit($fb, 6)); $set(8, 8, $bit($fb, 7)); $set(7, 8, $bit($fb, 8));
    for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, $bit($fb, $i));
    for ($i = 0; $i < 8; $i++) $set($size - 1 - $i, 8, $bit($fb, $i));
    for ($i = 8; $i < 15; $i++) $set(8, $size - 15 + $i, $bit($fb, $i));
    $set(8, $size - 8, true);
    // versão
    if ($ver >= 7) {
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $vb = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3; $b = intdiv($i, 3);
            $set($a, $b, $bit($vb, $i)); $set($b, $a, $bit($vb, $i));
        }
    }
    // dados em zigue-zague, com máscara 0
    $i = 0;
    $nBits = count($code) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($v = 0; $v < $size; $v++) for ($j = 0; $j < 2; $j++) {
            $x = $right - $j;
            $y = (($right + 1) & 2) === 0 ? $size - 1 - $v : $v;
            if ($fn[$y][$x]) continue;
            $d = $i < $nBits && (($code[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
            $i++;
            $m[$y][$x] = $d !== (($x + $y) % 2 === 0);
        }
    }
    return $m;
}

function qr_svg(string $text, int $px = 220): string
{
    $m = qr_matrix($text);
    $n = count($m) + 8;
    $path = '';
    foreach ($m as $y => $row) foreach ($row as $x => $dark) if ($dark) $path .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
    return '<svg class="qr" xmlns="http://www.w3.org/2000/svg" width="' . $px . '" height="' . $px . '" viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges" role="img" aria-label="QR Code para o aplicativo autenticador">'
        . '<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
}
