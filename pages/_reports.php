<?php
/** Tela de relatórios. $params são os parâmetros fixos da URL; com $fixed o filtro de cliente some (aba dentro do cliente). */
function render_reports(array $f, array $params, bool $fixed): void
{
$r = report_build($f);
$qs = report_query($f);
$extra = $fixed ? [] : ['cliente' => $f['client'] ?: ''];
$link = fn(array $x) => 'index.php?' . http_build_query($params + $x + $extra);
$ret = $link(['t' => $f['kind'], 'de' => $f['from'], 'ate' => $f['to']]);
$kinds = array_filter(REPORT_KINDS, fn($k) => $k !== 'financeiro' || can('billing.view'), ARRAY_FILTER_USE_KEY);
$clientWa = $f['client'] ? only_digits((string) val('SELECT COALESCE(NULLIF(whatsapp, \'\'), telefone) FROM clients WHERE id = ?', [$f['client']])) : '';
$waLink = 'https://wa.me/' . $clientWa . '?text=' . rawurlencode(report_text($r));
?>
<div class="pills rep-kinds"><?php foreach ($kinds as $k => $label): ?>
  <a class="pill <?= $f['kind'] === $k ? 'active' : '' ?>" href="<?= e($link(['t' => $k, 'de' => $f['from'], 'ate' => $f['to']])) ?>"><?= icon($k === 'tarefas' ? 'tasks' : 'wallet', 16) ?> <?= e($label) ?></a><?php endforeach; ?></div>

<form class="filters rep-filters" method="get"><?php foreach ($params as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?><input type="hidden" name="t" value="<?= e($f['kind']) ?>">
  <label>De<input type="date" name="de" value="<?= e($f['from']) ?>"></label>
  <label>Até<input type="date" name="ate" value="<?= e($f['to']) ?>"></label>
  <?php if (!$fixed): ?><label>Cliente<select name="cliente"><?= client_options($f['client'] ?: null, true) ?></select></label><?php endif; ?>
  <button class="btn primary" type="submit">Gerar</button>
</form>
<div class="chips rep-presets"><?php foreach (REPORT_PRESETS as $k => $label): ?>
  <a class="chip <?= $f['preset'] === $k ? 'active' : 'dim' ?>" href="<?= e($link(['t' => $f['kind'], 'per' => $k])) ?>"><?= e($label) ?></a><?php endforeach; ?></div>

<div class="card rep-actions">
  <b>Exportar e compartilhar</b>
  <div class="row" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
    <a class="btn" href="index.php?p=relatorio_export&fmt=pdf&<?= e($qs) ?>"><?= icon('download', 16) ?> Baixar PDF</a>
    <a class="btn" href="index.php?p=relatorio_export&fmt=csv&<?= e($qs) ?>"><?= icon('download', 16) ?> Baixar Excel (CSV)</a>
    <?php if (whatsapp_configured()): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="report_whatsapp"><input type="hidden" name="return" value="<?= e($ret) ?>">
      <input type="hidden" name="t" value="<?= e($f['kind']) ?>"><input type="hidden" name="de" value="<?= e($f['from']) ?>"><input type="hidden" name="ate" value="<?= e($f['to']) ?>"><input type="hidden" name="cliente" value="<?= $f['client'] ?: '' ?>">
      <button class="btn primary" type="submit"><?= icon('send', 16) ?> Enviar ao WhatsApp da equipe</button></form>
    <?php endif; ?>
    <a class="btn ghost" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('phone', 16) ?> <?= $clientWa ? 'Abrir conversa com o cliente' : 'Escolher contato no WhatsApp' ?></a>
  </div>
  <p class="muted" style="margin-top:10px;font-size:.85rem">O envio pelo WhatsApp leva o resumo em texto (indicadores e totais). Para anexar o PDF, baixe o arquivo e envie na conversa.<?= whatsapp_configured() ? '' : ' Para enviar direto pelo app, configure o WhatsApp em Configurações.' ?></p>
</div>

<section class="grid stats rep-kpis">
  <?php foreach ($r['kpis'] as $i => [$k, $v, $s]): ?>
    <div class="card stat"><b class="rep-kpi"><?= e($v) ?></b><small><?= e($k) ?></small><?php if ($s !== ''): ?><small class="muted"><?= e($s) ?></small><?php endif; ?></div>
  <?php endforeach; ?>
</section>

<section class="grid rep-charts">
  <?php foreach ($r['charts'] as $i => $ch): [$t, $kind, $d, $money] = $ch; ?>
    <div class="card"><h4><?= e($t) ?></h4>
      <?= $kind === 'dual' ? chart_dual_svg($d, $ch[4], $money, str_contains($t, 'custos') ? ['Receita', 'Custos'] : ['Receitas', 'Despesas']) : ($kind === 'cols' ? chart_columns_svg($d, $money) : chart_bar_svg($d, $money)) ?></div>
  <?php endforeach; ?>
</section>

<?php foreach ($r['tables'] as [$t, $head, $list]): /* o 4º item (larguras) só serve ao PDF */ ?>
  <div class="sec-h"><h3><?= e($t) ?> <em class="muted">(<?= count($list) ?>)</em></h3></div>
  <?php if (!$list): echo '<p class="muted">Nada a listar neste período.</p>'; continue; endif; ?>
  <div class="card rep-table"><table><thead><tr><?php foreach ($head as $h) echo '<th>' . e($h) . '</th>'; ?></tr></thead>
    <tbody><?php foreach ($list as $row): echo '<tr>'; foreach ($row as $c) echo '<td>' . e($c) . '</td>'; echo '</tr>'; endforeach; ?></tbody></table></div>
<?php endforeach; ?>
<?php }
