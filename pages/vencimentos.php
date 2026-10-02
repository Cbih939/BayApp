<?php
$st = $_GET['st'] ?? 'pendente'; $ft = $_GET['tipo'] ?? '';
$sql = 'SELECT b.*, c.nome AS cliente FROM billings b JOIN clients c ON c.id = b.client_id WHERE 1=1'; $par = [];
if ($st === 'pendente' || $st === 'pago') { $sql .= ' AND b.status = ?'; $par[] = $st; }
if (isset(BILL_TYPES[$ft])) { $sql .= ' AND b.tipo = ?'; $par[] = $ft; }
$list = rows($sql . ' ORDER BY b.vencimento', $par);
$ret = url('vencimentos', ['st' => $st, 'tipo' => $ft]);
$groups = ['over' => ['Vencidos', []], 'd7' => ['Até 7 dias', []], 'd15' => ['8 a 15 dias', []], 'd30' => ['16 a 30 dias', []], 'ok' => ['Mais de 30 dias', []], 'paid' => ['Pagos', []]];
foreach ($list as $b) $groups[bill_urgency($b)[0]][1][] = $b;
?>
<div class="sec-h big"><div><h3>Vencimentos</h3><p class="muted">Alertas automáticos por e-mail e WhatsApp com 30, 15 e 7 dias de antecedência.</p></div>
  <?php if (can('billing.edit')): ?><button class="btn primary" data-open="dlg-bill" data-new><?= icon('plus', 18) ?> Novo vencimento</button><?php endif; ?></div>
<form class="filters" method="get"><input type="hidden" name="p" value="vencimentos">
  <select name="st" onchange="this.form.submit()"><option value="pendente" <?= $st === 'pendente' ? 'selected' : '' ?>>Pendentes</option><option value="pago" <?= $st === 'pago' ? 'selected' : '' ?>>Pagos</option><option value="todos" <?= $st === 'todos' ? 'selected' : '' ?>>Todos</option></select>
  <select name="tipo" onchange="this.form.submit()"><option value="">Todos os tipos</option><?php foreach (BILL_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $ft === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></form>
<?php if (!$list) echo empty_state('calendar', 'Nenhum vencimento aqui', 'Cadastre domínios, hospedagens, e-mails e outras contas dos clientes.'); ?>
<?php foreach ($groups as $k => [$label, $items]): if (!$items) continue; ?>
  <div class="sec-h"><h3 class="grp tag-<?= $k ?>"><?= $label ?> <em><?= count($items) ?></em></h3></div>
  <div class="grid cards wide"><?php foreach ($items as $i => $b): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_bill_card($b, true, $ret); ?></div><?php endforeach; ?></div>
<?php endforeach; dlg_bill(null, $ret); ?>
