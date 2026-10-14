<?php
$today = date('Y-m-d');
$nClients = (int) val("SELECT COUNT(*) FROM clients WHERE status = 'ativo'");
$nCreds = (int) val('SELECT COUNT(*) FROM credentials');
$plus30 = date('Y-m-d', strtotime('+30 day'));
$canInt = can('billing.edit'); $canPay = can('billing.view');
$intOpen = $canInt ? rows("SELECT * FROM internal_bills WHERE status = 'pendente' ORDER BY vencimento") : [];
$nInt30 = count(array_filter($intOpen, fn($b) => $b['vencimento'] <= $plus30));
$nIntOver = count(array_filter($intOpen, fn($b) => $b['vencimento'] < $today));
$sumInt30 = array_sum(array_map(fn($b) => (float) $b['valor'], array_filter($intOpen, fn($b) => $b['vencimento'] <= $plus30)));
$cyc = $canPay ? cycle_open_events(null) : [];
$nCliOver = count(array_filter($cyc, fn($e) => $e['estado'] === 'atrasado'));
$sumCli = array_sum(array_map(fn($e) => (float) $e['valor'], $cyc));
$nOver = $nIntOver + $nCliOver;
$n30 = $nInt30 + count(array_filter($cyc, fn($e) => $e['dia'] <= $plus30 && $e['estado'] !== 'atrasado'));
$recentLinks = rows('SELECT l.*, c.nome AS cliente FROM links l LEFT JOIN clients c ON c.id=l.client_id ORDER BY l.id DESC LIMIT 4');
$recentAlerts = array_merge(
    rows("SELECT a.canal, a.marco, a.status, a.enviado_em, b.descricao FROM alert_log a JOIN billings b ON b.id = a.billing_id ORDER BY a.id DESC LIMIT 5"),
    rows("SELECT a.canal, a.marco, a.status, a.enviado_em, b.descricao FROM internal_alert_log a JOIN internal_bills b ON b.id = a.bill_id ORDER BY a.id DESC LIMIT 5"));
usort($recentAlerts, fn($x, $y) => strcmp($y['enviado_em'], $x['enviado_em']));
$recentAlerts = array_slice($recentAlerts, 0, 5);
$myTasks = task_sort(rows(TASK_SELECT . " WHERE t.assignee_id = ? AND t.status <> 'feito'", [$me['id']]));
$nMyOver = count(array_filter($myTasks, fn($t) => $t['prazo'] && $t['prazo'] < $today));
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
  <?php $stats = [['Clientes ativos', $nClients, 'briefcase', 'clientes', 0], ['Acessos guardados', $nCreds, 'key', 'senhas', 0]];
  if ($canInt) $stats[] = ['Vencimentos internos (30 dias)', $nInt30, 'wallet', 'vencimentos', $nIntOver ? 1 : 0];
  if ($canPay) $stats[] = ['Pagamentos de clientes em aberto', count($cyc), 'card', 'pagamentos', $nCliOver ? 1 : 0];
  foreach ($stats as $i => [$l, $v, $ic, $to, $alert]): ?>
    <a href="<?= url($to) ?>" class="card stat tilt anim-up <?= $alert ? 'alert' : '' ?>" style="--i:<?= $i ?>">
      <span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></a>
  <?php endforeach; ?>
</section>

<section class="dash-tasks">
  <div class="sec-h"><h3>Minhas tarefas <?= $myTasks ? '<em class="muted">(' . count($myTasks) . ($nMyOver ? ", $nMyOver em atraso" : '') . ')</em>' : '' ?></h3><a href="<?= url('tarefas', ['quem' => 'eu']) ?>" class="link">Abrir quadro →</a></div>
  <?php if (!$myTasks): echo '<p class="muted">Nenhuma tarefa em aberto para você. 🎉</p>'; endif; ?>
  <div class="grid cards"><?php foreach (array_slice($myTasks, 0, 6) as $i => $t): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_task_card($t, $ret); ?></div><?php endforeach; ?></div>
</section>

<div class="split">
  <section>
    <?php if ($canInt): ?>
    <div class="sec-h"><h3>Vencimentos internos da Bay Groups</h3><a href="<?= url('vencimentos') ?>" class="link">Ver todos →</a></div>
    <?php if (!$intOpen) echo empty_state('wallet', 'Nenhum vencimento interno', 'Cadastre hospedagem, domínios, ferramentas e impostos da empresa para receber alertas.'); ?>
    <div class="stack-cards"><?php foreach (array_slice($intOpen, 0, 4) as $i => $b): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_ibill_card($b, $ret); ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($canPay): ?>
    <div class="sec-h"><h3>Pagamentos de clientes</h3><a href="<?= url('pagamentos') ?>" class="link">Calendário de pagamentos →</a></div>
    <?php if (!$cyc) echo empty_state('card', 'Nenhum pagamento em aberto', 'Cadastre os serviços dentro de cada cliente (aba Pagamentos).'); ?>
    <div class="stack-cards"><?php foreach (array_slice($cyc, 0, 4) as $i => $ev): ?><div class="anim-up" style="--i:<?= $i ?>"><?php render_cycle_card($ev, $ret, false, true); ?></div><?php endforeach; ?></div>
    <?php endif; ?>
  </section>
  <section>
    <?php if ($canInt || $canPay): ?>
    <div class="sec-h"><h3>Próximos 30 dias</h3></div>
    <div class="card money tilt anim-up">
      <?php if ($canInt): ?><small class="muted">A pagar (Bay Groups)</small><b><?= fmt_money($sumInt30) ?></b><?php endif; ?>
      <?php if ($canPay): ?><small class="muted">Pagamentos de clientes em aberto</small><b><?= fmt_money($sumCli) ?></b><?php endif; ?></div>
    <?php endif; ?>
    <div class="sec-h"><h3>Links recentes</h3><a href="<?= url('links') ?>" class="link">Ver todos →</a></div>
    <div class="stack-cards"><?php foreach ($recentLinks as $l) render_link_card($l, $ret); if (!$recentLinks) echo '<p class="muted">Nenhum link ainda.</p>'; ?></div>
    <div class="sec-h"><h3>Últimos alertas</h3></div>
    <div class="card feed"><?php foreach ($recentAlerts as $a): ?>
      <div class="feed-i"><span class="dot <?= $a['status'] === 'ok' ? 'on' : 'off' ?>"></span><div><b><?= e($a['descricao']) ?></b>
        <small class="muted"><?= e($a['canal']) ?> · marco <?= (int) $a['marco'] ?>d · <?= ago($a['enviado_em']) ?></small></div></div>
    <?php endforeach; if (!$recentAlerts) echo '<p class="muted">Nenhum alerta enviado ainda.</p>'; ?></div>
  </section>
</div>
<?php dlg_task(null, $ret); dlg_task_view($ret);
if ($canInt) dlg_ibill($ret);
if ($canPay) { dlg_bill(null, $ret); pay_dialogs($ret); } ?>
