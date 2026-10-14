<?php $sub = ($_GET['sub'] ?? '') === 'cal' ? 'cal' : 'list'; ?>
<div class="sec-h big"><div><h3>Pagamentos</h3><p class="muted">Seus vencimentos, com o boleto para pagar e a quitação depois de pago.</p></div></div>
<div class="seg sub-toggle"><button type="button" class="pill <?= $sub === 'list' ? 'active' : '' ?>" data-sub="list"><?= icon('tasks', 15) ?> Lista</button>
  <button type="button" class="pill <?= $sub === 'cal' ? 'active' : '' ?>" data-sub="cal"><?= icon('cal', 15) ?> Calendário</button></div>
<div class="sub-pane" data-pane="list" <?= $sub === 'list' ? '' : 'hidden' ?>>
  <div class="sec-h"><h3 class="grp tag-d15">Em aberto <em><?= count($open) ?></em></h3></div>
  <?php if (!$open) echo empty_state('check', 'Nada em aberto', 'Quando houver um pagamento a fazer, ele aparece aqui com o boleto.'); ?>
  <div class="grid cards wide"><?php foreach ($open as $i => $ev): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_cycle_card($ev, 'portal.php?p=pagamentos', true); ?></div><?php endforeach; ?></div>
  <div class="sec-h"><h3 class="grp tag-paid">Pagos <em><?= count($paid) ?></em></h3></div>
  <?php if (!$paid) echo '<p class="muted">Nenhum pagamento registrado ainda.</p>'; ?>
  <div class="grid cards wide"><?php foreach ($paid as $i => $ev): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_cycle_card($ev, 'portal.php?p=pagamentos', true); ?></div><?php endforeach; ?></div>
</div>
<div class="sub-pane" data-pane="cal" <?= $sub === 'cal' ? '' : 'hidden' ?>><?php render_paycal($cid, ['p' => 'pagamentos', 'sub' => 'cal'], true, 'portal.php'); ?></div>
<?php pay_dialogs('portal.php?p=pagamentos', true); ?>
