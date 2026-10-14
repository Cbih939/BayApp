<?php
declare(strict_types=1);

/* Pagamentos dos clientes: cada "ciclo" (um vencimento de um serviço) pode estar em aberto, atrasado, pago ou previsto (recorrência futura). */

const CYCLE_STATE = ['aberto' => 'Em aberto', 'atrasado' => 'Atrasado', 'pago' => 'Pago', 'previsto' => 'Previsto'];

/** fixo (1x ao ano) | variavel (mês a mês, valor muda) | unico. Registros antigos sem modelo são tratados como fixo/único. */
function bill_model(array $b): string
{
    if (isset(BILL_MODELS[$b['modelo'] ?? ''])) return $b['modelo'];
    return ($b['recorrencia'] ?? 'nenhuma') === 'nenhuma' ? 'unico' : 'fixo';
}

function download_url(int $id, bool $portal): string
{
    return ($portal ? 'portal.php?p=arquivo&id=' : 'index.php?p=download&id=') . $id;
}

/** Monta o evento (ciclo) mostrado em cartões, calendário e modal. */
function cycle_event(array $b, string $dia, string $estado, ?array $pay, bool $portal): array
{
    $model = bill_model($b);
    $valor = $pay ? $pay['valor'] : (($estado === 'previsto' && $model === 'variavel') ? null : $b['valor']);
    $ev = ['key' => $b['id'] . ':' . substr($dia, 0, 7), 'billing_id' => (int) $b['id'], 'client_id' => (int) $b['client_id'], 'cliente' => (string) ($b['cliente'] ?? ''),
        'competencia' => substr($dia, 0, 7), 'dia' => $dia, 'dia_fmt' => fmt_date($dia), 'estado' => $estado, 'estado_label' => CYCLE_STATE[$estado],
        'tipo' => $b['tipo'], 'tipo_label' => BILL_TYPES[$b['tipo']] ?? $b['tipo'], 'descricao' => $b['descricao'], 'modelo' => $model,
        'modelo_label' => ($model === 'fixo' && $b['recorrencia'] !== 'anual') ? 'Fixo · ' . mb_strtolower(REC_LABELS[$b['recorrencia']] ?? '') : BILL_MODEL_SHORT[$model], 'valor' => $valor, 'valor_fmt' => $valor === null ? 'a definir' : fmt_money($valor),
        'pago_em' => $pay['pago_em'] ?? null, 'pago_fmt' => isset($pay['pago_em']) ? fmt_date(substr($pay['pago_em'], 0, 10)) : null, 'arquivos' => [],
        'reabrir' => !empty($pay['reabrir'])];
    if (!$portal) {
        $ev['bill'] = ['id' => (int) $b['id'], 'client_id' => (int) $b['client_id'], 'tipo' => $b['tipo'], 'descricao' => $b['descricao'], 'fornecedor' => $b['fornecedor'],
            'valor' => $b['valor'], 'vencimento' => $b['vencimento'], 'recorrencia' => $b['recorrencia'], 'modelo' => $model,
            'avisar_email' => (int) $b['avisar_email'], 'avisar_whatsapp' => (int) $b['avisar_whatsapp'], 'notas' => $b['notas']];
    }
    return $ev;
}

/** Anexa os arquivos de cada ciclo (uma consulta só). No portal, arquivos internos ficam de fora. */
function cycle_attach_files(array &$events, bool $portal): void
{
    $ids = array_values(array_unique(array_column($events, 'billing_id')));
    if (!$ids) return;
    $by = [];
    $sql = 'SELECT id, billing_id, competencia, kind, nome, tamanho, criado_em FROM files WHERE billing_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' . ($portal ? " AND kind <> 'interno'" : '') . ' ORDER BY id';
    foreach (rows($sql, $ids) as $f) {
        $by[$f['billing_id'] . ':' . $f['competencia']][] = ['id' => (int) $f['id'], 'kind' => $f['kind'], 'kind_label' => FILE_KINDS[$f['kind']] ?? $f['kind'], 'nome' => $f['nome'],
            'tamanho' => fmt_size((int) $f['tamanho']), 'data' => fmt_date(substr($f['criado_em'], 0, 10)), 'url' => download_url((int) $f['id'], $portal)];
    }
    foreach ($events as &$e) $e['arquivos'] = $by[$e['key']] ?? [];
}

/** Ciclos em aberto (um por serviço pendente: o vencimento atual). */
function cycle_open_events(?int $cid, bool $portal = false): array
{
    $today = date('Y-m-d');
    $out = [];
    foreach (rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.status = 'pendente'" . ($cid ? ' AND b.client_id = ?' : '') . ' ORDER BY b.vencimento', $cid ? [$cid] : []) as $b) {
        $out[] = cycle_event($b, $b['vencimento'], $b['vencimento'] < $today ? 'atrasado' : 'aberto', null, $portal);
    }
    cycle_attach_files($out, $portal);
    return $out;
}

/** Ciclos pagos (histórico), mais recentes primeiro; filtro opcional por data do vencimento. */
function cycle_paid_events(?int $cid, bool $portal = false, ?string $from = null, ?string $to = null, int $limit = 0): array
{
    $last = [];
    foreach (rows('SELECT billing_id, MAX(id) m FROM payments GROUP BY billing_id') as $r) $last[(int) $r['billing_id']] = (int) $r['m'];
    $out = [];
    foreach (rows('SELECT p.id AS pid, p.competencia, p.vencimento AS pvenc, p.valor AS pvalor, p.pago_em AS ppago, b.*, c.nome AS cliente
        FROM payments p JOIN billings b ON b.id = p.billing_id JOIN clients c ON c.id = p.client_id' . ($cid ? ' WHERE p.client_id = ?' : '') . ' ORDER BY p.pago_em DESC, p.id DESC', $cid ? [$cid] : []) as $r) {
        $dia = $r['pvenc'] ?: $r['competencia'] . '-01';
        if (($from && $dia < $from) || ($to && $dia > $to)) continue;
        $pay = ['valor' => $r['pvalor'], 'pago_em' => $r['ppago'], 'reabrir' => $r['status'] === 'pago' && ($last[(int) $r['id']] ?? 0) === (int) $r['pid']];
        $out[] = cycle_event($r, $dia, 'pago', $pay, $portal);
        if ($limit && count($out) >= $limit) break;
    }
    cycle_attach_files($out, $portal);
    return $out;
}

/** Recorrências futuras (depois do vencimento atual) dentro do período, para o calendário. */
function cycle_projected_events(?int $cid, string $from, string $to, bool $portal): array
{
    $out = [];
    foreach (rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE b.status = 'pendente'" . ($cid ? ' AND b.client_id = ?' : ''), $cid ? [$cid] : []) as $b) {
        foreach (bill_occurrences($b, $from, $to) as $d) if ($d !== $b['vencimento']) $out[] = cycle_event($b, $d, 'previsto', null, $portal);
    }
    cycle_attach_files($out, $portal);
    return $out;
}

/* ---------------- Coletor de dados para o modal (JSON único por página) ---------------- */

function pay_register(array $events): void
{
    $GLOBALS['__pay_events'] = ($GLOBALS['__pay_events'] ?? []) + array_column($events, null, 'key');
}

/* ---------------- Cartão de um ciclo ---------------- */

function render_cycle_card(array $ev, string $ret, bool $portal = false, bool $showClient = false): void
{
    pay_register([$ev]);
    $open = in_array($ev['estado'], ['aberto', 'atrasado'], true);
    [$cls, $label, $d] = bill_urgency(['status' => $ev['estado'] === 'pago' ? 'pago' : 'pendente', 'vencimento' => $ev['dia']]);
    $canEdit = !$portal && can('billing.edit');
    $files = $ev['arquivos'];
    ?>
    <article class="card bill <?= $cls ?> tilt" data-key="<?= e($ev['key']) ?>"<?= $open && !$portal ? ' data-item="' . e(json_encode($ev['bill'], JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
      <div class="bill-count"><span><?= $cls === 'paid' ? icon('check', 22) : ($d < 0 ? '!' : max($d, 0)) ?></span><small><?= $cls === 'paid' ? 'pago' : ($d < 0 ? 'atraso' : 'dias') ?></small></div>
      <div class="bill-main">
        <h4><?= e($ev['descricao']) ?></h4>
        <p class="muted"><?php if ($showClient): ?><a href="<?= url('cliente', ['id' => $ev['client_id'], 'tab' => 't-bill']) ?>"><?= e($ev['cliente']) ?></a> · <?php endif; ?><?= e($ev['tipo_label']) ?> · <?= e($ev['modelo_label']) ?></p>
        <div class="chips"><span class="chip"><?= icon('calendar', 14) ?> <?= e(MONTHS_PT[(int) substr($ev['competencia'], 5, 2)]) ?>/<?= substr($ev['competencia'], 0, 4) ?> · <?= $ev['dia_fmt'] ?></span>
          <span class="chip<?= $ev['valor'] === null ? ' dim' : '' ?>"><?= e($ev['valor_fmt']) ?></span>
          <span class="chip tag-<?= $cls ?>"><?= $ev['estado'] === 'pago' ? 'Pago em ' . e($ev['pago_fmt']) : e($label) ?></span></div>
        <div class="chips files-row">
          <?php $byKind = [];
          foreach ($files as $f) $byKind[$f['kind']][] = $f;
          foreach (['boleto' => 'Boleto', 'quitacao' => 'Quitação', 'outro' => 'Arquivo', 'interno' => 'Interno'] as $k => $short): if (empty($byKind[$k])) continue; $fl = $byKind[$k]; ?>
            <?php if (count($fl) === 1): ?><a class="chip file-chip k-<?= $k ?>" href="<?= e($fl[0]['url']) ?>" target="_blank" rel="noopener" title="<?= e($fl[0]['nome']) ?>"><?= icon('clip', 13) ?> <?= $short ?></a>
            <?php else: ?><button type="button" class="chip file-chip k-<?= $k ?>" data-payopen="<?= e($ev['key']) ?>" title="Ver os <?= count($fl) ?> arquivos"><?= icon('clip', 13) ?> <?= $short ?> ×<?= count($fl) ?></button><?php endif; ?>
          <?php endforeach; ?>
          <button type="button" class="chip file-btn" data-payopen="<?= e($ev['key']) ?>"><?= icon('upload', 13) ?> <?= $files ? 'Arquivos (' . count($files) . ')' : ($canEdit ? ($ev['estado'] === 'pago' ? 'Anexar quitação' : 'Anexar boleto') : 'Arquivos') ?></button>
        </div>
      </div>
      <?php if ($canEdit): ?>
      <div class="card-actions">
        <?php if ($open): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bill_pay"><input type="hidden" name="id" value="<?= $ev['billing_id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn ok" title="Marcar como pago"><?= icon('check') ?></button></form>
          <button type="button" class="icon-btn" data-edit="dlg-bill" title="Editar"><?= icon('edit') ?></button>
          <?php if (can('delete')) echo delete_btn('bill_delete', $ev['billing_id'], $ret, 'este serviço e todo o histórico dele'); ?>
        <?php elseif ($ev['reabrir']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bill_reopen"><input type="hidden" name="id" value="<?= $ev['billing_id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn" title="Reabrir (desfazer pagamento)">↺</button></form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </article>
    <?php
}

/** Diálogo de arquivos do pagamento + JSON dos ciclos da página. Chamar uma vez, no fim da página. */
function pay_dialogs(string $ret, bool $portal = false): void
{
    $canEdit = !$portal && can('billing.edit');
    $payload = ['events' => $GLOBALS['__pay_events'] ?? [], 'ret' => $ret, 'portal' => $portal, 'canEdit' => $canEdit];
    ?>
<script type="application/json" id="pay-data"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<dialog id="dlg-payday" class="modal"><header class="modal-h"><h3 id="payday-title">Pagamentos do dia</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="stack" id="payday-list" style="margin-top:12px"></div></dialog>
<dialog id="dlg-pay" class="modal paymodal"><div id="pm-item">
  <header class="modal-h"><div><p class="eyebrow" id="pm-client"></p><h3 id="pm-title"></h3></div><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="chips" id="pm-chips"></div>
  <ul class="pv-info pm-info"><li id="pm-venc"></li><li id="pm-valor"></li><li id="pm-status"></li></ul>
  <h4 class="tv-h">Arquivos <span class="muted" id="pm-count"></span></h4>
  <div id="pm-files"></div>
  <?php if ($canEdit): ?>
  <form method="post" enctype="multipart/form-data" id="pm-upload" class="pm-upload"><?= csrf_field() ?>
    <input type="hidden" name="action" value="file_upload"><input type="hidden" name="billing_id" value=""><input type="hidden" name="competencia" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
    <div class="grid2"><label>Tipo do arquivo<select name="kind"><?php foreach (FILE_KINDS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label>Arquivos <small class="muted">(pode escolher vários — PDF, imagem, Word ou Excel, até <?= (int) (UPLOAD_MAX_BYTES / 1048576) ?> MB cada)</small>
        <input type="file" name="arquivos[]" multiple required accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"></label></div>
    <button class="btn primary sm" style="align-self:flex-start"><?= icon('upload', 15) ?> Enviar arquivos</button>
  </form>
  <?php endif; ?>
  <footer class="modal-f tv-foot">
    <?php if ($canEdit): ?>
      <form method="post" id="pm-pay"><?= csrf_field() ?><input type="hidden" name="action" value="bill_pay"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn ghost"><?= icon('check', 16) ?> Marcar como pago</button></form>
      <button type="button" class="btn ghost" id="pm-edit" data-edit="dlg-bill"><?= icon('edit', 16) ?> Editar serviço</button>
    <?php endif; ?>
    <button type="button" class="btn primary" data-close>Fechar</button>
  </footer>
</div></dialog>
<?php }

/* ---------------- Calendário de pagamentos ---------------- */

function paycal_filters(array $src): array
{
    $m = (string) ($src['pm'] ?? '');
    return ['pm' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m'), 'pe' => isset(CYCLE_STATE[$src['pe'] ?? '']) ? $src['pe'] : ''];
}

/** Calendário mensal dos pagamentos. $pairs = parâmetros fixos da página; $base = index.php ou portal.php. Retorna a URL de retorno. */
function render_paycal(?int $client, array $pairs, bool $portal = false, string $base = 'index.php'): string
{
    $f = paycal_filters($_GET);
    $link = fn(array $over = []) => $base . '?' . http_build_query(array_filter(array_merge($pairs, $f, $over), fn($v) => $v !== ''));
    $ret = $link();
    [$y, $mo] = array_map('intval', explode('-', $f['pm']));
    $first = sprintf('%04d-%02d-01', $y, $mo);
    $lead = (int) date('w', strtotime($first));
    $weeks = (int) ceil(($lead + (int) date('t', strtotime($first))) / 7);
    $start = date('Y-m-d', strtotime("$first -$lead day"));
    $end = date('Y-m-d', strtotime("$start +" . ($weeks * 7 - 1) . ' day'));
    $events = array_merge(
        array_values(array_filter(cycle_open_events($client, $portal), fn($e) => $e['dia'] >= $start && $e['dia'] <= $end)),
        cycle_projected_events($client, $start, $end, $portal),
        cycle_paid_events($client, $portal, $start, $end));
    if ($f['pe']) $events = array_values(array_filter($events, fn($e) => $e['estado'] === $f['pe']));
    pay_register($events);
    $by = [];
    foreach ($events as $e) $by[$e['dia']][] = $e;
    $today = date('Y-m-d');
    $inMonth = array_filter($events, fn($e) => substr($e['dia'], 0, 7) === $f['pm']);
    $sum = fn(array $st) => array_sum(array_map(fn($e) => (float) $e['valor'], array_filter($inMonth, fn($e) => in_array($e['estado'], $st, true))));
    $prev = date('Y-m', strtotime("$first -1 month"));
    $next = date('Y-m', strtotime("$first +1 month"));
    ?>
    <div class="cal-bar">
      <div class="cal-nav">
        <a class="icon-btn" href="<?= e($link(['pm' => $prev])) ?>" title="Mês anterior"><?= icon('chevl') ?></a>
        <h3><?= e(MONTHS_PT[$mo] . ' ' . $y) ?></h3>
        <a class="icon-btn" href="<?= e($link(['pm' => $next])) ?>" title="Próximo mês"><?= icon('chevr') ?></a>
        <a class="btn ghost sm" href="<?= e($link(['pm' => date('Y-m')])) ?>">Hoje</a>
      </div>
      <form class="cal-filters" method="get" action="<?= e($base) ?>">
        <?php foreach (array_merge($pairs, ['pm' => $f['pm']]) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <select name="pe" onchange="this.form.submit()"><option value="">Todas as situações</option>
          <?php foreach (CYCLE_STATE as $k => $v): ?><option value="<?= $k ?>" <?= $f['pe'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </form>
    </div>
    <p class="muted cal-sum"><?= count($inMonth) ?> pagamento(s) em <?= e(MONTHS_PT[$mo]) ?> · <b><?= e(fmt_money($sum(['aberto', 'atrasado', 'previsto']))) ?></b> em aberto · <b><?= e(fmt_money($sum(['pago']))) ?></b> pago(s)
      · <span class="legend"><i class="pst pst-pl"><?= icon('clock', 11) ?></i> aberto <i class="pst pst-no"><?= icon('x', 11) ?></i> atrasado <i class="pst pst-ok"><?= icon('check', 11) ?></i> pago <i class="pst pst-fu">·</i> previsto</span></p>
    <div class="cal-wrap"><div class="cal">
      <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $w): ?><div class="cal-wd"><?= $w ?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $weeks * 7; $i++):
          $d = date('Y-m-d', strtotime("$start +$i day"));
          $list = $by[$d] ?? [];
          $cls = ($d === $today ? ' today' : '') . (substr($d, 0, 7) !== $f['pm'] ? ' out' : '') . ($i % 7 === 0 || $i % 7 === 6 ? ' wk' : ''); ?>
        <div class="cal-day<?= $cls ?>">
          <div class="cal-dh"><span class="cal-dn"><?= (int) substr($d, 8, 2) ?></span></div>
          <?php foreach (array_slice($list, 0, 3) as $e): ?>
            <button type="button" class="cal-chip st-<?= e($e['estado']) ?>" data-payopen="<?= e($e['key']) ?>" title="<?= e($e['cliente'] . ' · ' . $e['descricao'] . ' · ' . $e['estado_label']) ?>">
              <span class="cal-nm"><?= e($client ? $e['descricao'] : explode(' ', $e['cliente'])[0]) ?></span>
              <time><?= $e['valor'] === null ? '—' : e(preg_replace('/,00$/', '', str_replace('R$ ', '', $e['valor_fmt']))) ?></time>
              <?= $e['arquivos'] ? icon('clip', 11) : '' ?><?= match ($e['estado']) {
                  'pago' => '<i class="pst pst-ok">' . icon('check', 11) . '</i>', 'atrasado' => '<i class="pst pst-no">' . icon('x', 11) . '</i>',
                  'previsto' => '<i class="pst pst-fu">·</i>', default => '<i class="pst pst-pl">' . icon('clock', 11) . '</i>' } ?></button>
          <?php endforeach; ?>
          <?php if (count($list) > 3): ?><button type="button" class="cal-more" data-payday="<?= $d ?>">+<?= count($list) - 3 ?> mais…</button><?php endif; ?>
        </div>
      <?php endfor; ?>
    </div></div>
    <?php
    return $ret;
}
