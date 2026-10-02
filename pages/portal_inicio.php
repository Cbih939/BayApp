<?php
$over = count(array_filter($pending, fn($b) => days_until($b['vencimento']) < 0));
$n30 = count(array_filter($pending, fn($b) => ($d = days_until($b['vencimento'])) >= 0 && $d <= 30));
$sum = array_sum(array_map(fn($b) => (float) $b['valor'], array_filter($pending, fn($b) => days_until($b['vencimento']) <= 30)));
?>
<section class="hero anim-up"><div><p class="eyebrow"><?= e($me['empresa'] ?: $me['cliente']) ?></p>
  <h1>Olá, <?= e(explode(' ', $me['nome'])[0]) ?> 👋</h1>
  <p class="muted"><?= $over ? "<b class='warn'>$over vencimento(s) em atraso.</b> Fale com a nossa equipe." : ($n30 ? "Você tem $n30 vencimento(s) nos próximos 30 dias." : 'Tudo em dia com a sua conta.') ?></p></div></section>
<section class="grid stats">
  <?php foreach ([['A vencer em 30 dias', $n30, 'calendar'], ['Em atraso', $over, 'bell'], ['Pastas e links', count($links), 'folder']] as $i => [$l, $v, $ic]): ?>
    <div class="card stat tilt anim-up <?= $l === 'Em atraso' && $v ? 'alert' : '' ?>" style="--i:<?= $i ?>"><span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
  <div class="card stat tilt anim-up" style="--i:3"><span class="stat-ico"><?= icon('wallet', 22) ?></span><b style="font-size:1.6rem"><?= fmt_money($sum) ?></b><small>Previsto em 30 dias</small></div>
</section>
<div class="sec-h"><h3>Próximos vencimentos</h3><a href="portal.php?p=vencimentos" class="link">Ver todos →</a></div>
<?php if (!$pending) echo empty_state('calendar', 'Nada pendente', 'Quando houver vencimentos de domínio, hospedagem ou outros serviços, eles aparecem aqui.'); ?>
<div class="grid cards wide"><?php foreach (array_slice($pending, 0, 4) as $i => $b): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_bill_card($b, false, $ret); ?></div><?php endforeach; ?></div>
<div class="sec-h"><h3>Suas pastas e links</h3><a href="portal.php?p=links" class="link">Ver todos →</a></div>
<?php if (!$links) echo empty_state('folder', 'Nenhum link ainda', 'Sua equipe vai disponibilizar aqui o Drive, o Canva e a Agenda.'); ?>
<div class="grid cards"><?php foreach (array_slice($links, 0, 3) as $l) render_link_card($l, $ret); ?></div>
