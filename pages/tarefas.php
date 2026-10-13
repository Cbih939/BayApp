<?php
$who = $_GET['quem'] ?? 'todos';      // todos | eu | sem | <id>
$fc = (int) ($_GET['cliente'] ?? 0);
$fp = $_GET['prio'] ?? '';
$view = ($_GET['v'] ?? 'quadro') === 'lista' ? 'lista' : 'quadro';
$sql = TASK_SELECT . ' WHERE 1=1'; $par = [];
if ($who === 'eu') { $sql .= ' AND t.assignee_id = ?'; $par[] = $me['id']; }
elseif ($who === 'sem') $sql .= ' AND t.assignee_id IS NULL';
elseif (ctype_digit((string) $who)) { $sql .= ' AND t.assignee_id = ?'; $par[] = (int) $who; }
if ($fc) { $sql .= ' AND t.client_id = ?'; $par[] = $fc; }
if (isset(TASK_PRIO[$fp])) { $sql .= ' AND t.prioridade = ?'; $par[] = $fp; }
$list = rows($sql, $par);
$cols = array_fill_keys(array_keys(TASK_STATUS), []);
foreach ($list as $t) $cols[$t['status']][] = $t;
foreach ($cols as $k => $items) $cols[$k] = task_sort($items);
usort($cols['feito'], fn($a, $b) => strcmp((string) $b['concluida_em'], (string) $a['concluida_em']));
$hiddenDone = max(0, count($cols['feito']) - 20);
$cols['feito'] = array_slice($cols['feito'], 0, 20);

$today = date('Y-m-d');
$open = array_filter($list, fn($t) => $t['status'] !== 'feito');
$nOpen = count($open);
$nOver = count(array_filter($open, fn($t) => $t['prazo'] && $t['prazo'] < $today));
$nToday = count(array_filter($open, fn($t) => $t['prazo'] === $today));
$nDone = count($list) - $nOpen;
$base = ['quem' => $who, 'cliente' => $fc ?: '', 'prio' => $fp, 'v' => $view];
$ret = url('tarefas', array_filter($base, fn($v) => $v !== '' && $v !== 0));
?>
<div class="sec-h big"><div><h3>Tarefas</h3><p class="muted">Quem faz o quê, para qual cliente e até quando. Arraste os cartões entre as colunas ou use as setas.</p></div>
  <button class="btn primary" data-open="dlg-task" data-new><?= icon('plus', 18) ?> Nova tarefa</button></div>

<section class="grid stats">
  <?php foreach ([['Em aberto', $nOpen, 'tasks', 0], ['Atrasadas', $nOver, 'bell', $nOver ? 1 : 0], ['Vencem hoje', $nToday, 'calendar', 0], ['Concluídas', $nDone, 'check', 0]] as $i => [$l, $v, $ic, $al]): ?>
    <div class="card stat anim-up <?= $al ? 'alert' : '' ?>" style="--i:<?= $i ?>"><span class="stat-ico"><?= icon($ic, 22) ?></span><b class="count" data-to="<?= $v ?>">0</b><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
</section>

<form class="filters" method="get"><input type="hidden" name="p" value="tarefas"><input type="hidden" name="v" value="<?= $view ?>">
  <select name="quem" onchange="this.form.submit()">
    <option value="todos">Toda a equipe</option><option value="eu" <?= $who === 'eu' ? 'selected' : '' ?>>Minhas tarefas</option><option value="sem" <?= $who === 'sem' ? 'selected' : '' ?>>Sem responsável</option>
    <?php foreach (rows('SELECT id, nome FROM users WHERE ativo = 1 ORDER BY nome') as $u): ?><option value="<?= $u['id'] ?>" <?= (string) $who === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option><?php endforeach; ?></select>
  <select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option>
    <?php foreach (rows('SELECT id, nome FROM clients ORDER BY nome') as $c): ?><option value="<?= $c['id'] ?>" <?= $fc === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
  <select name="prio" onchange="this.form.submit()"><option value="">Qualquer prioridade</option>
    <?php foreach (TASK_PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $fp === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <span class="seg"><a class="pill <?= $view === 'quadro' ? 'active' : '' ?>" href="<?= e(url('tarefas', array_filter(['v' => 'quadro'] + $base, fn($v) => $v !== ''))) ?>">Quadro</a>
    <a class="pill <?= $view === 'lista' ? 'active' : '' ?>" href="<?= e(url('tarefas', array_filter(['v' => 'lista'] + $base, fn($v) => $v !== ''))) ?>">Lista</a></span>
</form>

<?php if (!$list) echo empty_state('tasks', 'Nenhuma tarefa por aqui', 'Crie a primeira tarefa e atribua a alguém da equipe.'); ?>

<?php if ($view === 'quadro' && $list): ?>
<div class="kanban" id="kanban" data-api="index.php?p=task_api">
  <?php foreach (TASK_STATUS as $st => $label): ?>
  <section class="kcol" data-status="<?= $st ?>">
    <header><h4><?= e($label) ?></h4><em><?= count($cols[$st]) ?></em></header>
    <div class="kbody"><?php foreach ($cols[$st] as $t) render_task_card($t, $ret); ?>
      <?php if ($st === 'feito' && $hiddenDone): ?><p class="muted small">+ <?= $hiddenDone ?> concluída(s) mais antiga(s) — veja na aba Lista.</p><?php endif; ?></div>
  </section>
  <?php endforeach; ?>
</div>
<script src="<?= asset('assets/js/tasks.js') ?>"></script>
<?php elseif ($list): ?>
<?php $flat = array_merge(...array_map(fn($s) => $cols[$s], ['a_fazer', 'fazendo', 'revisao'])); $flat = array_merge($flat, rows_all_done($list)); ?>
<div class="grid cards wide"><?php foreach ($flat as $i => $t): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_task_card($t, $ret); ?></div><?php endforeach; ?></div>
<?php endif; dlg_task(null, $ret); ?>
