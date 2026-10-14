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
<dialog id="dlg-post" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
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
