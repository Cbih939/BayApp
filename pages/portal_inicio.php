<?php
$over = count(array_filter($open, fn($e) => $e['estado'] === 'atrasado'));
$plus30 = date('Y-m-d', strtotime('+30 day'));
$soon = array_filter($open, fn($e) => $e['estado'] === 'aberto' && $e['dia'] <= $plus30);
$sum = array_sum(array_map(fn($e) => (float) $e['valor'], array_filter($open, fn($e) => $e['dia'] <= $plus30)));
$boletos = count(array_filter($open, fn($e) => array_filter($e['arquivos'], fn($f) => $f['kind'] === 'boleto')));
?>
<section class="hero anim-up"><div><p class="eyebrow"><?= e($me['empresa'] ?: $me['cliente']) ?></p>
  <h1>Olá, <?= e(explode(' ', $me['nome'])[0]) ?> 👋</h1>
  <p class="muted"><?= $over ? "<b class='warn'>$over pagamento(s) em atraso.</b> Fale com a nossa equipe." : (count($soon) ? 'Você tem ' . count($soon) . ' pagamento(s) nos próximos 30 dias.' : 'Tudo em dia com a sua conta.') ?></p></div></section>
<section class="grid stats">
  <?php foreach ([['A pagar em 30 dias', count($soon), 'calendar'], ['Em atraso', $over, 'bell'], ['Boletos disponíveis', $boletos, 'clip'], ['Pastas e links', count($links), 'folder']] as $i => [$l, $v, $ic]): ?>
    <div class="card stat tilt anim-up <?= $l === 'Em atraso' && $v ? 'alert' : '' ?>" style="--i:<?= $i ?>"><span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
</section>
<div class="sec-h"><h3>Próximos pagamentos <?= $sum ? '<em class="muted">(' . e(fmt_money($sum)) . ' em 30 dias)</em>' : '' ?></h3><a href="portal.php?p=pagamentos" class="link">Ver todos →</a></div>
<?php if (!$open) echo empty_state('check', 'Nada pendente', 'Quando houver um pagamento a fazer, ele aparece aqui com o boleto.'); ?>
<div class="grid cards wide"><?php foreach (array_slice($open, 0, 4) as $i => $ev): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_cycle_card($ev, 'portal.php', true); ?></div><?php endforeach; ?></div>
<div class="sec-h"><h3>Suas pastas e links</h3><a href="portal.php?p=links" class="link">Ver todos →</a></div>
<?php if (!$links) echo empty_state('folder', 'Nenhum link ainda', 'Sua equipe vai disponibilizar aqui o Drive, o Canva e a Agenda.'); ?>
<div class="grid cards"><?php foreach (array_slice($links, 0, 3) as $l) render_link_card($l, $ret); ?></div>
<?php pay_dialogs('portal.php', true); ?>
