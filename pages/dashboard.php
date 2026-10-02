<?php
$today = date('Y-m-d');
$nClients = (int) val("SELECT COUNT(*) FROM clients WHERE status = 'ativo'");
$nCreds = (int) val('SELECT COUNT(*) FROM credentials');
$n30 = (int) val("SELECT COUNT(*) FROM billings WHERE status='pendente' AND vencimento >= ? AND vencimento <= ?", [$today, date('Y-m-d', strtotime('+30 day'))]);
$nOver = (int) val("SELECT COUNT(*) FROM billings WHERE status='pendente' AND vencimento < ?", [$today]);
$sum30 = (float) val("SELECT COALESCE(SUM(valor),0) FROM billings WHERE status='pendente' AND vencimento <= ?", [date('Y-m-d', strtotime('+30 day'))]);
$upcoming = rows("SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id=b.client_id WHERE b.status='pendente' ORDER BY b.vencimento LIMIT 6");
$recentLinks = rows('SELECT l.*, c.nome AS cliente FROM links l LEFT JOIN clients c ON c.id=l.client_id ORDER BY l.id DESC LIMIT 4');
$recentAlerts = rows("SELECT a.*, b.descricao FROM alert_log a JOIN billings b ON b.id=a.billing_id ORDER BY a.id DESC LIMIT 5");
$ret = url('dashboard');
$hour = (int) date('G');
$greet = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
?>
<section class="hero anim-up">
  <div><p class="eyebrow">Painel</p><h1><?= $greet ?>, <?= e(explode(' ', $me['nome'])[0]) ?> 👋</h1>
    <p class="muted"><?= $nOver ? "<b class='warn'>$nOver vencimento(s) em atraso</b> pedem atenção." : ($n30 ? "Você tem $n30 vencimento(s) nos próximos 30 dias." : 'Tudo em dia por aqui.') ?></p></div>
  <div class="hero-actions">
    <?php if (can('clients.edit')): ?><a class="btn primary" href="<?= url('clientes', ['novo' => 1]) ?>"><?= icon('plus', 18) ?> Novo cliente</a><?php endif; ?>
    <a class="btn ghost" href="<?= url('guia') ?>"><?= icon('book', 18) ?> Ver guia</a></div>
</section>

<section class="grid stats">
  <?php foreach ([['Clientes ativos', $nClients, 'briefcase', 'clientes', 0], ['Acessos guardados', $nCreds, 'key', 'senhas', 0],
    ['Vencem em 30 dias', $n30, 'calendar', 'vencimentos', 0], ['Em atraso', $nOver, 'bell', 'vencimentos', $nOver ? 1 : 0]] as $i => [$l, $v, $ic, $to, $alert]): ?>
    <a href="<?= url($to) ?>" class="card stat tilt anim-up <?= $alert ? 'alert' : '' ?>" style="--i:<?= $i ?>">
      <span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></a>
  <?php endforeach; ?>
</section>

<div class="split">
  <section>
    <div class="sec-h"><h3>Próximos vencimentos</h3><a href="<?= url('vencimentos') ?>" class="link">Ver todos →</a></div>
    <?php if (!$upcoming): echo empty_state('calendar', 'Nenhum vencimento pendente', 'Cadastre domínios, hospedagens e contas para receber alertas.'); endif; ?>
    <div class="stack-cards"><?php foreach ($upcoming as $i => $b): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_bill_card($b, true, $ret); ?></div><?php endforeach; ?></div>
  </section>
  <section>
    <div class="sec-h"><h3>Previsão 30 dias</h3></div>
    <div class="card money tilt anim-up"><small class="muted">Total a pagar</small><b><?= fmt_money($sum30) ?></b><span class="bar"><i style="width:<?= $n30 + $nOver ? min(100, ($nOver ? 100 : 40 + $n30 * 8)) : 0 ?>%"></i></span></div>
    <div class="sec-h"><h3>Links recentes</h3><a href="<?= url('links') ?>" class="link">Ver todos →</a></div>
    <div class="stack-cards"><?php foreach ($recentLinks as $l) render_link_card($l, $ret); if (!$recentLinks) echo '<p class="muted">Nenhum link ainda.</p>'; ?></div>
    <div class="sec-h"><h3>Últimos alertas</h3></div>
    <div class="card feed"><?php foreach ($recentAlerts as $a): ?>
      <div class="feed-i"><span class="dot <?= $a['status'] === 'ok' ? 'on' : 'off' ?>"></span><div><b><?= e($a['descricao']) ?></b>
        <small class="muted"><?= e($a['canal']) ?> · marco <?= (int) $a['marco'] ?>d · <?= ago($a['enviado_em']) ?></small></div></div>
    <?php endforeach; if (!$recentAlerts) echo '<p class="muted">Nenhum alerta enviado ainda.</p>'; ?></div>
  </section>
</div>
