<?php
declare(strict_types=1);

/** Filtros do calendário (mês, canal, status) lidos da query string. */
function cal_filters(array $src): array
{
    $m = (string) ($src['m'] ?? '');
    return ['m' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m'),
        'canal' => isset(POST_CHANNELS[$src['canal'] ?? '']) ? $src['canal'] : '',
        'pst' => isset(POST_STATUS[$src['pst'] ?? '']) ? $src['pst'] : ''];
}

/** Posts de um intervalo, já no formato usado pela tela (e pelo JSON do modal). */
function cal_posts(string $from, string $to, ?int $client, array $f): array
{
    $sql = 'SELECT p.*, c.nome AS cliente, u.nome AS autor FROM posts p JOIN clients c ON c.id = p.client_id LEFT JOIN users u ON u.id = p.criado_por
            WHERE p.dia >= ? AND p.dia <= ?';
    $par = [$from, $to];
    if ($client) { $sql .= ' AND p.client_id = ?'; $par[] = $client; }
    if ($f['canal']) { $sql .= ' AND p.canal = ?'; $par[] = $f['canal']; }
    if ($f['pst']) { $sql .= ' AND p.status = ?'; $par[] = $f['pst']; }
    $out = [];
    foreach (rows($sql . " ORDER BY p.dia, COALESCE(p.hora, '99:99'), p.id", $par) as $p) {
        $out[] = $p + ['canal_label' => POST_CHANNELS[$p['canal']][0] ?? $p['canal'], 'sigla' => POST_CHANNELS[$p['canal']][1] ?? '··',
            'formato_label' => POST_FORMATS[$p['formato']] ?? $p['formato'], 'status_label' => POST_STATUS[$p['status']] ?? $p['status'],
            'dia_fmt' => fmt_date($p['dia'])];
    }
    return $out;
}

/** Bolinha colorida com o ícone do status (cor em .ps-<status>). */
function post_status_icon(string $st): string
{
    return '<i class="pst ps-' . e($st) . '" title="' . e(POST_STATUS[$st] ?? $st) . '">' . icon(POST_STATUS_ICON[$st] ?? 'clock', 10) . '</i>';
}

/**
 * Calendário mensal de postagens. $pairs = parâmetros fixos da página (ex.: ['p'=>'cliente','id'=>3,'tab'=>'t-cal']).
 * Retorna a URL de retorno para os formulários.
 */
function render_calendar(?int $client, array $pairs): string
{
    $f = cal_filters($_GET);
    $link = fn(array $over = []) => 'index.php?' . http_build_query(array_filter(array_merge($pairs, $f, $over), fn($v) => $v !== ''));
    $ret = $link();
    [$y, $mo] = array_map('intval', explode('-', $f['m']));
    $first = sprintf('%04d-%02d-01', $y, $mo);
    $dim = (int) date('t', strtotime($first));
    $lead = (int) date('w', strtotime($first));                 // 0 = domingo
    $start = date('Y-m-d', strtotime("$first -$lead day"));
    $weeks = (int) ceil(($lead + $dim) / 7);
    $end = date('Y-m-d', strtotime("$start +" . ($weeks * 7 - 1) . ' day'));
    $posts = cal_posts($start, $end, $client, $f);
    $byDay = [];
    foreach ($posts as $p) $byDay[$p['dia']][] = $p;
    $prev = date('Y-m', strtotime("$first -1 month"));
    $next = date('Y-m', strtotime("$first +1 month"));
    $today = date('Y-m-d');
    $canEdit = can('posts.edit');
    $inMonth = array_filter($posts, fn($p) => substr($p['dia'], 0, 7) === $f['m']);
    $nDone = count(array_filter($inMonth, fn($p) => $p['status'] === 'concluido'));
    // faixa do fluxo de conteúdo: contagem do mês por status (respeita cliente e canal; ignora o filtro de status)
    $cnt = array_fill_keys(array_keys(POST_STATUS), 0);
    foreach (rows('SELECT status, COUNT(*) n FROM posts WHERE dia >= ? AND dia <= ?' . ($client ? ' AND client_id = ?' : '') . ($f['canal'] ? ' AND canal = ?' : '') . ' GROUP BY status',
        array_values(array_filter([$first, date('Y-m-t', strtotime($first)), $client ?: null, $f['canal'] ?: null], fn($v) => $v !== null))) as $r) {
        $cnt[$r['status']] = (int) $r['n'];
    }
    ?>
    <div class="ps-strip" role="group" aria-label="Fluxo do conteúdo">
      <?php foreach (POST_STATUS as $k => $label): if ($k === 'cancelado') continue; ?>
        <a class="ps-card ps-<?= $k ?> <?= $f['pst'] === $k ? 'on' : '' ?>" href="<?= e($link(['pst' => $f['pst'] === $k ? '' : $k])) ?>" title="<?= $f['pst'] === $k ? 'Mostrar todos' : 'Filtrar: ' . e($label) ?>">
          <span class="ps-top"><span class="ps-ico"><?= icon(POST_STATUS_ICON[$k], 16) ?></span><em class="ps-n"><?= $cnt[$k] ?></em></span>
          <span class="ps-lab"><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="cal-bar">
      <div class="cal-nav">
        <a class="icon-btn" href="<?= e($link(['m' => $prev])) ?>" title="Mês anterior"><?= icon('chevl') ?></a>
        <h3><?= e(MONTHS_PT[$mo] . ' ' . $y) ?></h3>
        <a class="icon-btn" href="<?= e($link(['m' => $next])) ?>" title="Próximo mês"><?= icon('chevr') ?></a>
        <a class="btn ghost sm" href="<?= e($link(['m' => date('Y-m')])) ?>">Hoje</a>
        <a class="btn ghost sm" href="<?= e('index.php?' . http_build_query(array_filter(['p' => 'calendario_pdf', 'm' => $f['m'], 'client' => $client, 'canal' => $f['canal']]))) ?>" title="Baixar o mês em PDF, só com as postagens prontas"><?= icon('download', 15) ?> Baixar PDF</a>
      </div>
      <form class="cal-filters" method="get">
        <?php foreach (array_merge($pairs, ['m' => $f['m']]) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <label class="fpill <?= $f['pst'] ? 'on' : '' ?>"><?= icon('tasks', 15) ?><select name="pst" onchange="this.form.submit()" aria-label="Status"><option value="">Status</option>
          <?php foreach (POST_STATUS as $k => $v): ?><option value="<?= $k ?>" <?= $f['pst'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="fpill <?= $f['canal'] ? 'on' : '' ?>"><?= icon('link', 15) ?><select name="canal" onchange="this.form.submit()" aria-label="Canais"><option value="">Canais</option>
          <?php foreach (POST_CHANNELS as $k => $v): ?><option value="<?= $k ?>" <?= $f['canal'] === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></label>
        <?php if ($f['pst'] || $f['canal']): ?><a class="fpill on" href="<?= e($link(['pst' => '', 'canal' => ''])) ?>">Limpar filtros <?= icon('x', 13) ?></a><?php endif; ?>
        <?php if ($canEdit): ?><button type="button" class="btn primary sm" data-newpost=""><?= icon('plus', 16) ?> Novo post</button><?php endif; ?>
      </form>
    </div>
    <p class="muted cal-sum"><?= count($inMonth) ?> post(s) em <?= e(MONTHS_PT[$mo]) ?> · <?= $nDone ?> concluído(s)</p>

    <div class="cal-wrap"><div class="cal" data-can-edit="<?= $canEdit ? 1 : 0 ?>">
      <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $w): ?><div class="cal-wd"><?= $w ?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $weeks * 7; $i++):
          $d = date('Y-m-d', strtotime("$start +$i day"));
          $list = $byDay[$d] ?? [];
          $cls = ($d === $today ? ' today' : '') . (substr($d, 0, 7) !== $f['m'] ? ' out' : '') . ($i % 7 === 0 || $i % 7 === 6 ? ' wk' : ''); ?>
        <div class="cal-day<?= $cls ?>">
          <div class="cal-dh">
            <?php if ($canEdit): ?><button type="button" class="cal-add" data-newpost="<?= $d ?>" title="Adicionar post em <?= fmt_date($d) ?>"><?= icon('plus', 13) ?> Adicionar</button><?php endif; ?>
            <span class="cal-dn"><?= (int) substr($d, 8, 2) ?></span></div>
          <?php foreach (array_slice($list, 0, 3) as $p): ?>
            <button type="button" class="cal-chip ps-<?= e($p['status']) ?>" data-post="<?= (int) $p['id'] ?>" title="<?= e($p['cliente'] . ' · ' . $p['canal_label'] . ' · ' . $p['formato_label'] . ' · ' . $p['status_label'] . ($p['titulo'] ? ' — ' . $p['titulo'] : '')) ?>">
              <i class="chb ch-<?= e($p['canal']) ?>"><?= e($p['sigla']) ?></i><span class="cal-nm"><?= e($client ? ($p['titulo'] ?: $p['formato_label']) : explode(' ', $p['cliente'])[0]) ?></span>
              <time><?= e($p['hora'] ?: '') ?></time><?= post_status_icon($p['status']) ?></button>
          <?php endforeach; ?>
          <?php if ($list): ?><div class="cal-foot"><span><?= min(3, count($list)) ?> / <?= count($list) ?></span><button type="button" class="cal-more" data-day="<?= $d ?>">mais…</button></div><?php endif; ?>
          <button type="button" class="cal-hit" data-day="<?= $d ?>" aria-label="Ver os posts de <?= fmt_date($d) ?>"></button>
          <?php if ($list): ?><span class="cal-dots" aria-hidden="true"><?php foreach (array_slice($list, 0, 4) as $p): ?><i class="cal-dot ps-<?= e($p['status']) ?>"></i><?php endforeach; ?><?php if (count($list) > 4): ?><b>+<?= count($list) - 4 ?></b><?php endif; ?></span><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div></div>
    <script type="application/json" id="cal-data"><?= json_encode(['posts' => $posts, 'client' => $client], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <?php
    return $ret;
}

/** Diálogos do calendário: formulário, visualização (preview) e lista do dia. */
function calendar_dialogs(?int $client, string $ret): void
{
    $canEdit = can('posts.edit'); ?>
<dialog id="dlg-post" class="modal"><form method="post" class="stack" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="action" value="post_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo post|Editar post">Novo post</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <?php if ($client): ?><input type="hidden" name="client_id" value="<?= $client ?>"><?php else: ?>
  <label>Cliente<select name="client_id" required><?= client_options() ?></select></label><?php endif; ?>
  <div class="grid3"><label>Data<input type="date" name="dia" required></label><label>Horário<input type="time" name="hora" value="18:00"></label>
    <label>Status<select name="status"><?php foreach (POST_STATUS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <div class="grid2"><label>Canal<select name="canal"><?php foreach (POST_CHANNELS as $k => $v): ?><option value="<?= $k ?>"><?= e($v[0]) ?></option><?php endforeach; ?></select></label>
    <label>Formato <small class="muted">(conta no pacote)</small><select name="formato"><?php foreach (POST_FORMATS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <label>Título / tema<input name="titulo" maxlength="200" placeholder="Ex.: Dica de leitura — Checklist"></label>
  <label>Legenda<textarea name="legenda" rows="4" placeholder="Texto que vai junto com a publicação…"></textarea></label>
  <label>Link da arte <small class="muted">(Canva, Drive ou endereço de uma imagem)</small><input type="url" name="midia_url" placeholder="https://"></label>
  <label>Ou envie a imagem da arte <small class="muted">(PNG, JPG ou WEBP, até <?= fmt_size(UPLOAD_MAX_BYTES) ?>)</small><input type="file" name="imagem" accept="image/png,image/jpeg,image/webp"></label>
  <label class="switch" id="post-rmimg" hidden><input type="checkbox" name="remover_imagem" value="1"><span></span> Remover a imagem atual <small class="muted" id="post-curimg"></small></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar post</button></footer>
</form></dialog>

<dialog id="dlg-postview" class="modal postview">
  <header class="modal-h"><div class="pv-tabs"><button type="button" class="pv-tab active" data-pv="preview"><?= icon('eye', 16) ?> Preview</button><button type="button" class="pv-tab" data-pv="hist"><?= icon('clock', 16) ?> Histórico</button></div>
    <button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="pv-body" id="pv-item">
    <section class="pv-main">
      <div class="pv-pane" data-pane="preview">
        <div class="pv-head"><span class="avatar" id="pv-av"></span><div><b id="pv-client"></b><small class="muted" id="pv-sub"></small></div></div>
        <p class="pv-caption" id="pv-caption"></p>
        <div class="pv-media" id="pv-media"></div>
      </div>
      <div class="pv-pane" data-pane="hist" hidden><ul class="timeline" id="pv-hist"></ul></div>
    </section>
    <aside class="pv-side">
      <h4>Informações do post</h4>
      <ul class="pv-info"><li id="pv-i-canal"></li><li id="pv-i-status"></li><li id="pv-i-created"></li><li id="pv-i-when"></li></ul>
      <?php if ($canEdit): ?>
      <form method="post" id="pv-status" class="pv-status"><?= csrf_field() ?><input type="hidden" name="action" value="post_status"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
        <label>Mudar status<select name="status" onchange="this.form.submit()"><?php foreach (POST_STATUS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></form>
      <h4>Ações</h4>
      <div class="pv-actions">
        <button type="button" class="pv-link" data-edit="dlg-post"><?= icon('edit', 16) ?> Editar</button>
        <button type="button" class="pv-link" id="pv-clone"><?= icon('copy2', 16) ?> Clonar</button>
        <?php if (can('delete')): ?><form method="post" id="pv-del" data-confirm="Excluir este post do calendário?"><?= csrf_field() ?><input type="hidden" name="action" value="post_delete"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
          <button class="pv-link danger"><?= icon('trash', 16) ?> Excluir post</button></form><?php endif; ?>
      </div>
      <form method="post" id="pv-pub"><?= csrf_field() ?><input type="hidden" name="action" value="post_status"><input type="hidden" name="id" value=""><input type="hidden" name="status" value="concluido"><input type="hidden" name="return" value="<?= e($ret) ?>">
        <button class="btn primary full"><?= icon('check', 16) ?> Marcar como concluído</button></form>
      <?php endif; ?>
    </aside>
  </div>
</dialog>

<dialog id="dlg-day" class="modal"><header class="modal-h"><h3 id="day-title">Posts do dia</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="stack" id="day-list" style="margin-top:12px"></div></dialog>
<?php }

/* ---------------- PDF do calendário (estilo mLabs) ---------------- */

/** Status que entram no PDF: o que já foi aprovado (aguardando agendamento, aprovado e agendado, concluído). */
const POST_READY = ['aguardando', 'agendado', 'concluido'];

/** Miniatura quadrada (JPEG) da imagem enviada para o post. Retorna [bytes, largura, altura] ou null (sem imagem ou sem GD). */
function post_thumb(array $p, int $px = 180): ?array
{
    $file = trim((string) ($p['imagem'] ?? ''));
    $path = APP_ROOT . '/storage/uploads/' . basename($file);
    if ($file === '' || !is_file($path) || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return null;
    $info = @getimagesize($path);
    if (!$info || $info[0] * $info[1] > 36_000_000) return null;          // evita estourar a memória do servidor
    $src = @imagecreatefromstring((string) file_get_contents($path));
    if (!$src) return null;
    [$w, $h] = [imagesx($src), imagesy($src)];
    $side = min($w, $h);
    $dst = imagecreatetruecolor($px, $px);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $px, $px, $side, $side);
    ob_start(); imagejpeg($dst, null, 80); $jpg = (string) ob_get_clean();
    imagedestroy($src); imagedestroy($dst);
    return [$jpg, $px, $px];
}

/** Quebra o texto em linhas que cabem em $width pontos (estimativa pela largura média da Helvetica). */
function pdf_wrap(string $text, float $width, float $size, int $maxLines): array
{
    $lines = []; $cur = '';
    foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
        $try = $cur === '' ? $word : "$cur $word";
        if ($cur !== '' && MiniPdf::width($try, $size) > $width) { $lines[] = $cur; $cur = $word; } else $cur = $try;
    }
    if ($cur !== '') $lines[] = $cur;
    if (count($lines) > $maxLines) { $lines = array_slice($lines, 0, $maxLines); $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, -1)) . '…'; }
    return $lines;
}

/** PDF do mês: grade do calendário só com as postagens prontas e, nas páginas seguintes, o detalhe de cada uma. Retorna null se não houver nenhuma. */
function calendar_pdf(string $month, ?int $client, string $canal): ?string
{
    [$y, $mo] = array_map('intval', explode('-', $month));
    $first = sprintf('%04d-%02d-01', $y, $mo);
    $last = date('Y-m-t', strtotime($first));
    $in = implode(',', array_fill(0, count(POST_READY), '?'));
    $sql = "SELECT p.*, c.nome AS cliente FROM posts p JOIN clients c ON c.id = p.client_id WHERE p.dia >= ? AND p.dia <= ? AND p.status IN ($in)";
    $par = array_merge([$first, $last], POST_READY);
    if ($client) { $sql .= ' AND p.client_id = ?'; $par[] = $client; }
    if ($canal !== '') { $sql .= ' AND p.canal = ?'; $par[] = $canal; }
    $posts = rows($sql . " ORDER BY p.dia, COALESCE(p.hora, '99:99'), p.id", $par);
    if (!$posts) return null;
    $byDay = [];
    foreach ($posts as $i => $p) { $posts[$i]['_thumb'] = post_thumb($p); $byDay[$p['dia']][] = $posts[$i]; }

    $chColor = ['instagram' => [0.78, 0.27, 0.75], 'facebook' => [0.23, 0.51, 0.96], 'tiktok' => [0.06, 0.09, 0.16], 'linkedin' => [0.04, 0.4, 0.76],
        'youtube' => [0.86, 0.15, 0.15], 'outro' => [0.4, 0.4, 0.5]];
    $purple = [0.545, 0.361, 0.965]; $ink = [0.1, 0.1, 0.15]; $mute = [0.45, 0.45, 0.52]; $line = [0.85, 0.85, 0.9];
    $pdf = new MiniPdf(true);
    $W = $pdf->pw; $H = $pdf->ph; $M = 28;
    $who = $client ? (string) (row('SELECT nome FROM clients WHERE id = ?', [$client])['nome'] ?? '') : 'Todos os clientes';

    // cabeçalho
    $pdf->rect(0, $H - 62, $W, 62, [0.07, 0.07, 0.1]);
    $pdf->text($M, $H - 24, mb_strtoupper(setting('empresa_nome', 'BayApp')) . '  ·  CALENDÁRIO DE POSTAGENS', 8, true, $purple);
    $pdf->text($M, $H - 47, MONTHS_PT[$mo] . ' ' . $y, 22, true, [1, 1, 1]);
    $pdf->text($W - $M - MiniPdf::width($who, 11, true), $H - 36, $pdf->fit($who, 300, 11, true), 11, true, [1, 1, 1]);
    $sub = count($posts) . ' postagem(ns) pronta(s)  ·  gerado em ' . date('d/m/Y');
    $pdf->text($W - $M - MiniPdf::width($sub, 8), $H - 50, $sub, 8, false, [0.7, 0.7, 0.78]);

    // grade
    $lead = (int) date('w', strtotime($first));
    $weeks = (int) ceil(($lead + (int) date('t', strtotime($first))) / 7);
    $gx = $M; $gw = $W - 2 * $M; $cw = $gw / 7; $top = $H - 62 - 14; $hh = 16;
    $rh = ($top - $hh - 54) / $weeks;
    foreach (['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'] as $i => $wd) {
        $pdf->rect($gx + $i * $cw, $top - $hh, $cw, $hh, [0.94, 0.93, 0.99]);
        $pdf->text($gx + $i * $cw + 6, $top - 11, $wd, 7.5, true, [0.35, 0.25, 0.7]);
    }
    for ($i = 0; $i < $weeks * 7; $i++) {
        $d = date('Y-m-d', strtotime("$first -$lead day +$i day"));
        $x = $gx + ($i % 7) * $cw; $yb = $top - $hh - (intdiv($i, 7) + 1) * $rh;
        $inM = substr($d, 0, 7) === $month;
        if (!$inM) $pdf->rect($x, $yb, $cw, $rh, [0.965, 0.965, 0.975]);
        $pdf->frame($x, $yb, $cw, $rh, $line);
        $pdf->text($x + 5, $yb + $rh - 11, (string) (int) substr($d, 8, 2), 9, true, $inM ? $ink : [0.7, 0.7, 0.75]);
        $list = $inM ? ($byDay[$d] ?? []) : [];
        $eh = 25; $cap = max(1, (int) floor(($rh - 16) / $eh));
        $show = count($list) > $cap ? $cap - 1 : count($list);
        if ($show < 1 && $list) $show = 1;
        foreach (array_slice($list, 0, $show) as $k => $p) {
            $ey = $yb + $rh - 16 - ($k + 1) * $eh + 3;
            $col = $chColor[$p['canal']] ?? $chColor['outro'];
            if ($p['_thumb']) $pdf->image($p['_thumb'][0], $p['_thumb'][1], $p['_thumb'][2], $x + 4, $ey, 20, 20);
            else { $pdf->rect($x + 4, $ey, 20, 20, $col); $pdf->text($x + 14 - MiniPdf::width(POST_CHANNELS[$p['canal']][1] ?? '··', 7, true) / 2, $ey + 7, POST_CHANNELS[$p['canal']][1] ?? '··', 7, true, [1, 1, 1]); }
            $pdf->rect($x + 4, $ey, 2, 20, $col);
            $tw = $cw - 32;
            $pdf->text($x + 28, $ey + 12, $pdf->fit(trim(($p['hora'] ?: '') . ' ' . (POST_CHANNELS[$p['canal']][0] ?? '')), $tw, 6.5), 6.5, true, $col === $chColor['tiktok'] ? $ink : $col);
            $pdf->text($x + 28, $ey + 3, $pdf->fit((string) ($p['titulo'] ?: (POST_FORMATS[$p['formato']] ?? '')), $tw, 7), 7, false, $ink);
        }
        if (count($list) > $show) $pdf->text($x + 6, $yb + 4, '+' . (count($list) - $show) . ' mais (veja o detalhamento)', 6.5, true, $purple);
    }
    $pdf->text($M, 38, 'Somente postagens prontas: aguardando agendamento, aprovado e agendado ou concluído.', 7.5, false, $mute);

    // detalhamento
    $pdf->newPage();
    $head = function () use ($pdf, $W, $H, $M, $purple, $mo, $y) {
        $pdf->rect(0, $H - 40, $W, 40, [0.07, 0.07, 0.1]);
        $pdf->text($M, $H - 25, 'Detalhamento · ' . MONTHS_PT[$mo] . ' ' . $y, 12, true, [1, 1, 1]);
        $pdf->y = $H - 40 - 16;
    };
    $head();
    $bh = 104;
    foreach ($posts as $p) {
        if ($pdf->y - $bh < 36) { $pdf->newPage(); $head(); }
        $top = $pdf->y; $col = $chColor[$p['canal']] ?? $chColor['outro'];
        $pdf->frame($M, $top - $bh + 6, $W - 2 * $M, $bh - 6, $line);
        if ($p['_thumb']) $pdf->image($p['_thumb'][0], $p['_thumb'][1], $p['_thumb'][2], $M + 8, $top - 92, 84, 84);
        else { $pdf->rect($M + 8, $top - 92, 84, 84, [0.95, 0.94, 0.99]); $pdf->text($M + 22, $top - 54, 'Sem imagem', 8, false, $mute); }
        $tx = $M + 104;
        $when = fmt_date($p['dia']) . ($p['hora'] ? ' às ' . $p['hora'] : '');
        $pdf->rect($tx, $top - 22, 3, 14, $col);
        $pdf->text($tx + 9, $top - 19, $when . '   ·   ' . (POST_CHANNELS[$p['canal']][0] ?? '') . ' · ' . (POST_FORMATS[$p['formato']] ?? ''), 9.5, true, $ink);
        $st = POST_STATUS[$p['status']] ?? '';
        $pdf->text($W - $M - 8 - MiniPdf::width($st, 8, true), $top - 19, $st, 8, true, $purple);
        $pdf->text($tx, $top - 36, $pdf->fit(($who === 'Todos os clientes' ? $p['cliente'] . ($p['titulo'] ? ' — ' : '') : '') . (string) $p['titulo'], $W - $tx - $M - 10, 10.5, true), 10.5, true, $ink);
        foreach (pdf_wrap((string) ($p['legenda'] ?? ''), $W - $tx - $M - 14, 8, 5) as $n => $ln) $pdf->text($tx, $top - 52 - $n * 11, $ln, 8, false, [0.25, 0.25, 0.32]);
        if (empty($p['legenda'])) $pdf->text($tx, $top - 52, 'Sem legenda.', 8, false, $mute);
        $pdf->y = $top - $bh - 4;
    }
    return $pdf->output();
}
