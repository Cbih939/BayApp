<?php
$f = report_filters($_GET);
$r = report_build($f);
$qs = report_query($f);
$ret = url('relatorios', ['de' => $f['from'], 'ate' => $f['to'], 'cliente' => $f['client'] ?: '']);
$clientWa = $f['client'] ? only_digits((string) val('SELECT COALESCE(NULLIF(whatsapp, \'\'), telefone) FROM clients WHERE id = ?', [$f['client']])) : '';
$waLink = 'https://wa.me/' . $clientWa . '?text=' . rawurlencode(report_text($r));
?>
<div class="sec-h big"><div><h3>Relatórios</h3><p class="muted">Vencimentos, tarefas e clientes em gráficos. Baixe em PDF ou Excel (CSV) ou envie o resumo pelo WhatsApp.</p></div></div>

<form class="filters rep-filters" method="get"><input type="hidden" name="p" value="relatorios">
  <label>De<input type="date" name="de" value="<?= e($f['from']) ?>"></label>
  <label>Até<input type="date" name="ate" value="<?= e($f['to']) ?>"></label>
  <label>Cliente<select name="cliente"><?= client_options($f['client'] ?: null, true) ?></select></label>
  <button class="btn primary" type="submit">Gerar</button>
</form>
<div class="chips rep-presets"><?php foreach (REPORT_PRESETS as $k => $label): ?>
  <a class="chip <?= $f['preset'] === $k ? 'active' : 'dim' ?>" href="<?= url('relatorios', ['per' => $k, 'cliente' => $f['client'] ?: '']) ?>"><?= e($label) ?></a><?php endforeach; ?></div>

<div class="card rep-actions anim-up">
  <b>Exportar e compartilhar</b>
  <div class="row" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
    <a class="btn" href="index.php?p=relatorio_export&fmt=pdf&<?= e($qs) ?>"><?= icon('download', 16) ?> Baixar PDF</a>
    <a class="btn" href="index.php?p=relatorio_export&fmt=csv&<?= e($qs) ?>"><?= icon('download', 16) ?> Baixar Excel (CSV)</a>
    <?php if (whatsapp_configured()): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="report_whatsapp"><input type="hidden" name="return" value="<?= e($ret) ?>">
      <input type="hidden" name="de" value="<?= e($f['from']) ?>"><input type="hidden" name="ate" value="<?= e($f['to']) ?>"><input type="hidden" name="cliente" value="<?= $f['client'] ?: '' ?>">
      <button class="btn primary" type="submit"><?= icon('send', 16) ?> Enviar ao WhatsApp da equipe</button></form>
    <?php endif; ?>
    <a class="btn ghost" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('phone', 16) ?> <?= $clientWa ? 'Abrir conversa com o cliente' : 'Escolher contato no WhatsApp' ?></a>
  </div>
  <p class="muted" style="margin-top:10px;font-size:.85rem">O envio pelo WhatsApp leva o resumo em texto (indicadores e totais). Para anexar o PDF, baixe o arquivo e envie na conversa.<?= whatsapp_configured() ? '' : ' Para enviar direto pelo app, configure o WhatsApp em Configurações.' ?></p>
</div>

<section class="grid stats rep-kpis">
  <?php foreach ($r['kpis'] as $i => [$k, $v, $s]): ?>
    <div class="card stat anim-up" style="--i:<?= $i ?>"><b class="rep-kpi"><?= e($v) ?></b><small><?= e($k) ?></small><?php if ($s !== ''): ?><small class="muted"><?= e($s) ?></small><?php endif; ?></div>
  <?php endforeach; ?>
</section>

<section class="grid rep-charts">
  <?php foreach ($r['charts'] as $i => [$t, $kind, $d, $money]): ?>
    <div class="card anim-up" style="--i:<?= $i ?>"><h4><?= e($t) ?></h4>
      <?= $kind === 'donut' ? chart_donut_svg($d, $money) : chart_bar_svg($d, $money) ?></div>
  <?php endforeach; ?>
</section>

<?php foreach ($r['tables'] as [$t, $head, $list]): ?>
  <div class="sec-h"><h3><?= e($t) ?> <em class="muted">(<?= count($list) ?>)</em></h3></div>
  <?php if (!$list): echo '<p class="muted">Nada a listar neste período.</p>'; continue; endif; ?>
  <div class="card rep-table"><table><thead><tr><?php foreach ($head as $h) echo '<th>' . e($h) . '</th>'; ?></tr></thead>
    <tbody><?php foreach ($list as $row): echo '<tr>'; foreach ($row as $c) echo '<td>' . e($c) . '</td>'; echo '</tr>'; endforeach; ?></tbody></table></div>
<?php endforeach; ?>
