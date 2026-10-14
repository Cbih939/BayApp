<?php
/* Calendário de pagamentos dos clientes: vencimentos em aberto, pagos e previstos, com boleto e quitação anexados. */
$fc = (int) ($_GET['cliente'] ?? 0);
$clients = rows('SELECT id, nome FROM clients ORDER BY nome');
$open = cycle_open_events($fc ?: null);
$over = count(array_filter($open, fn($e) => $e['estado'] === 'atrasado'));
$sumOpen = array_sum(array_map(fn($e) => (float) $e['valor'], $open));
$noBoleto = count(array_filter($open, fn($e) => !array_filter($e['arquivos'], fn($f) => $f['kind'] === 'boleto')));
?>
<div class="sec-h big"><div><h3>Calendário de pagamentos</h3><p class="muted">Pagamentos dos clientes por vencimento. Clique em um pagamento para anexar boleto e quitação (vários arquivos) ou baixar o que já foi enviado.</p></div></div>
<section class="grid stats">
  <?php foreach ([['Em aberto', count($open), 'card', 0], ['Atrasados', $over, 'bell', $over ? 1 : 0], ['Sem boleto anexado', $noBoleto, 'clip', 0]] as $i => [$l, $v, $ic, $al]): ?>
    <div class="card stat anim-up <?= $al ? 'alert' : '' ?>" style="--i:<?= $i ?>"><span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
  <div class="card stat anim-up" style="--i:3"><span class="stat-ico"><?= icon('wallet', 22) ?></span><b style="font-size:1.5rem"><?= e(fmt_money($sumOpen)) ?></b><small>Total em aberto</small></div>
</section>
<form class="filters" method="get"><input type="hidden" name="p" value="pagamentos"><input type="hidden" name="pm" value="<?= e(paycal_filters($_GET)['pm']) ?>">
  <select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option>
    <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $fc === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></form>
<?php
$ret = render_paycal($fc ?: null, ['p' => 'pagamentos'] + ($fc ? ['cliente' => $fc] : []));
dlg_bill($fc ?: null, $ret);
pay_dialogs($ret);
