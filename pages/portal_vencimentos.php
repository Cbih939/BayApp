<div class="sec-h big"><div><h3>Vencimentos</h3><p class="muted">Domínios, hospedagem e outros serviços da sua conta.</p></div></div>
<?php if (!$bills) echo empty_state('calendar', 'Nenhum vencimento cadastrado', 'Quando houver, ele aparece aqui.');
$groups = ['over' => ['Vencidos', []], 'd7' => ['Até 7 dias', []], 'd15' => ['8 a 15 dias', []], 'd30' => ['16 a 30 dias', []], 'ok' => ['Mais de 30 dias', []], 'paid' => ['Pagos', []]];
foreach ($bills as $b) $groups[bill_urgency($b)[0]][1][] = $b;
foreach ($groups as $k => [$label, $items]): if (!$items) continue; ?>
  <div class="sec-h"><h3 class="grp tag-<?= $k ?>"><?= $label ?> <em><?= count($items) ?></em></h3></div>
  <div class="grid cards wide"><?php foreach ($items as $i => $b): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_bill_card($b, false, $ret); ?></div><?php endforeach; ?></div>
<?php endforeach; ?>
