<?php
/* Vencimentos internos da Bay Groups: contas que a própria empresa paga (hospedagem, domínios, ferramentas, impostos…). */
$st = ($_GET['st'] ?? 'pendente') === 'pago' ? 'pago' : 'pendente';
$fc = isset(INTERNAL_CATS[$_GET['cat'] ?? '']) ? $_GET['cat'] : '';
$ret = url('vencimentos', array_filter(['st' => $st === 'pago' ? 'pago' : '', 'cat' => $fc]));
$today = date('Y-m-d');
$all = rows("SELECT * FROM internal_bills WHERE status = 'pendente' ORDER BY vencimento");
$n7 = count(array_filter($all, fn($b) => $b['vencimento'] <= date('Y-m-d', strtotime('+7 day'))));
$over = array_filter($all, fn($b) => $b['vencimento'] < $today);
$sum30 = array_sum(array_map(fn($b) => (float) $b['valor'], array_filter($all, fn($b) => $b['vencimento'] <= date('Y-m-d', strtotime('+30 day')))));
$month = array_sum(array_map(fn($b) => (float) $b['valor'] / REC_MONTHS[$b['recorrencia']], array_filter($all, fn($b) => isset(REC_MONTHS[$b['recorrencia']]))));
$list = array_values(array_filter($all, fn($b) => !$fc || $b['categoria'] === $fc));
$groups = ['over' => ['Vencidos', []], 'd7' => ['Até 7 dias', []], 'd15' => ['8 a 15 dias', []], 'd30' => ['16 a 30 dias', []], 'ok' => ['Mais de 30 dias', []]];
foreach ($list as $b) $groups[bill_urgency($b)[0]][1][] = $b;
$paid = $st === 'pago' ? rows('SELECT p.*, b.descricao, b.categoria, b.fornecedor FROM internal_payments p JOIN internal_bills b ON b.id = p.bill_id' . ($fc ? ' WHERE b.categoria = ?' : '') . ' ORDER BY p.pago_em DESC, p.id DESC LIMIT 60', $fc ? [$fc] : []) : [];
?>
<div class="sec-h big"><div><h3>Vencimentos da Bay Groups</h3><p class="muted">Contas internas da empresa — hospedagem, domínios, ferramentas, impostos… Alertas por e-mail e WhatsApp com 30, 15 e 7 dias. Os pagamentos dos clientes ficam em cada cliente e em <a class="link" href="<?= url('pagamentos') ?>">Pagamentos</a>.</p></div>
  <button class="btn primary" data-open="dlg-ibill" data-new><?= icon('plus', 18) ?> Novo vencimento</button></div>

<section class="grid stats">
  <?php foreach ([['Vencem em 7 dias', $n7, 'bell', 0], ['A pagar em 30 dias', fmt_money($sum30), 'wallet', 0], ['Em atraso', count($over), 'calendar', count($over) ? 1 : 0], ['Custo mensal fixo', fmt_money($month), 'card', 0]] as $i => [$l, $v, $ic, $al]): ?>
    <div class="card stat anim-up <?= $al ? 'alert' : '' ?>" style="--i:<?= $i ?>"><span class="stat-ico"><?= icon($ic, 22) ?></span>
      <?php if (is_int($v)): ?><b class="count" data-to="<?= $v ?>">0</b><?php else: ?><b style="font-size:1.5rem"><?= e($v) ?></b><?php endif; ?><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
</section>

<form class="filters" method="get"><input type="hidden" name="p" value="vencimentos">
  <select name="st" onchange="this.form.submit()"><option value="pendente" <?= $st === 'pendente' ? 'selected' : '' ?>>Em aberto</option><option value="pago" <?= $st === 'pago' ? 'selected' : '' ?>>Pagos (histórico)</option></select>
  <select name="cat" onchange="this.form.submit()"><option value="">Todas as categorias</option><?php foreach (INTERNAL_CATS as $k => $v): ?><option value="<?= $k ?>" <?= $fc === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></form>

<?php if ($st === 'pago'): ?>
  <?php if (!$paid) echo empty_state('check', 'Nenhum pagamento registrado', 'Ao marcar um vencimento como pago, ele aparece aqui com a competência e a data.'); ?>
  <div class="grid cards wide"><?php foreach ($paid as $i => $pg): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_ipaid_card($pg); ?></div><?php endforeach; ?></div>
<?php else: ?>
  <?php if (!$list) echo empty_state('wallet', 'Nenhum vencimento interno', 'Cadastre as contas da Bay Groups (hospedagem, domínios, Canva, impostos…) para ser avisado com antecedência.'); ?>
  <?php foreach ($groups as $k => [$label, $items]): if (!$items) continue; ?>
    <div class="sec-h"><h3 class="grp tag-<?= $k ?>"><?= $label ?> <em><?= count($items) ?></em></h3></div>
    <div class="grid cards wide"><?php foreach ($items as $i => $b): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_ibill_card($b, $ret); ?></div><?php endforeach; ?></div>
  <?php endforeach; ?>
<?php endif; dlg_ibill($ret); ?>
