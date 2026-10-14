<?php
/* Pastas & Links junto com as tarefas: cada cliente mostra seus links e as tarefas em aberto. */
$ft = isset(LINK_TYPES[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : '';
$ret = url('links', array_filter(['tipo' => $ft]));
$linksBy = [];
foreach (rows('SELECT l.*, c.nome AS cliente FROM links l LEFT JOIN clients c ON c.id = l.client_id' . ($ft ? ' WHERE l.tipo = ?' : '') . ' ORDER BY l.tipo, l.titulo', $ft ? [$ft] : []) as $l) $linksBy[(int) $l['client_id']][] = $l;
$tasksBy = [];
foreach (task_sort(rows(TASK_SELECT . " WHERE t.status <> 'feito'")) as $t) $tasksBy[(int) $t['client_id']][] = $t;
$blocks = [];
foreach (rows('SELECT id, nome FROM clients ORDER BY nome') as $c) {
    $l = $linksBy[(int) $c['id']] ?? []; $t = $tasksBy[(int) $c['id']] ?? [];
    if ($l || (!$ft && $t)) $blocks[] = ['id' => (int) $c['id'], 'nome' => $c['nome'], 'links' => $l, 'tasks' => $t];
}
$gl = $linksBy[0] ?? []; $gt = $tasksBy[0] ?? [];
if ($gl || (!$ft && $gt)) $blocks[] = ['id' => 0, 'nome' => 'Geral (sem cliente)', 'links' => $gl, 'tasks' => $gt];
?>
<div class="sec-h big"><div><h3>Pastas &amp; Links e tarefas</h3><p class="muted">Drive, Canva e Google Agenda de cada cliente, junto com as tarefas em aberto dele. Clique numa tarefa para ler os detalhes e ver as pastas e links dela.</p></div>
  <div class="row-btns"><?php if (can('links.edit')): ?><button class="btn primary" data-open="dlg-link" data-new><?= icon('plus', 18) ?> Novo link</button><?php endif; ?>
    <button class="btn ghost" data-open="dlg-task" data-new><?= icon('plus', 18) ?> Nova tarefa</button></div></div>
<div class="pills"><a class="pill <?= $ft === '' ? 'active' : '' ?>" href="<?= url('links') ?>">Todos</a>
  <?php foreach (LINK_TYPES as $k => $v): ?><a class="pill <?= $ft === $k ? 'active' : '' ?>" href="<?= url('links', ['tipo' => $k]) ?>"><?= e($v) ?></a><?php endforeach; ?></div>
<?php if (!$blocks) echo empty_state('folder', 'Nenhum link ou tarefa por aqui', 'Cole o link da pasta do Drive, do Canva ou da agenda — o tipo é detectado sozinho.'); ?>
<?php foreach ($blocks as $bi => $b): ?>
  <section class="cblock card anim-up" style="--i:<?= min($bi, 8) ?>">
    <header class="cblock-h"><span class="avatar sm"><?= e(initials($b['nome'])) ?></span>
      <h3><?php if ($b['id']): ?><a href="<?= url('cliente', ['id' => $b['id']]) ?>"><?= e($b['nome']) ?></a><?php else: echo e($b['nome']); endif; ?></h3>
      <span class="chip"><?= icon('folder', 13) ?> <?= count($b['links']) ?></span><span class="chip"><?= icon('tasks', 13) ?> <?= count($b['tasks']) ?> em aberto</span></header>
    <div class="cblock-body">
      <div class="cb-col"><h5>Pastas e links</h5>
        <?php if (!$b['links']) echo '<p class="muted small">Nenhum link' . ($ft ? ' deste tipo' : '') . '.</p>'; ?>
        <div class="stack-cards"><?php foreach ($b['links'] as $l) render_link_card($l, $ret); ?></div></div>
      <?php if (!$ft): ?><div class="cb-col"><h5>Tarefas em aberto</h5>
        <?php if (!$b['tasks']) echo '<p class="muted small">Nenhuma tarefa em aberto.</p>'; ?>
        <div class="stack-cards"><?php foreach (array_slice($b['tasks'], 0, 6) as $t) render_task_mini($t); ?></div>
        <?php if (count($b['tasks']) > 6): ?><a class="link small" href="<?= url('tarefas', ['cliente' => $b['id']]) ?>">Ver as <?= count($b['tasks']) ?> tarefas →</a><?php endif; ?></div><?php endif; ?>
    </div>
  </section>
<?php endforeach; dlg_link(null, $ret); dlg_task(null, $ret); dlg_task_view($ret); ?>
