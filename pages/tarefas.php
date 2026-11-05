<?php
$who = $_GET['quem'] ?? 'todos';      // todos | eu | sem | <id>
$fc = (int) ($_GET['cliente'] ?? 0);
$fp = $_GET['prio'] ?? '';
$view = ($_GET['v'] ?? 'lista') === 'quadro' ? 'quadro' : 'lista';
$sql = TASK_SELECT . ' WHERE 1=1' . task_scope(); $par = [];
if ($who === 'eu') { $sql .= ' AND t.assignee_id = ?'; $par[] = $me['id']; }
elseif ($who === 'sem') $sql .= ' AND t.assignee_id IS NULL';
elseif (ctype_digit((string) $who)) { $sql .= ' AND t.assignee_id = ?'; $par[] = (int) $who; }
if ($fc) { $sql .= ' AND t.client_id = ?'; $par[] = $fc; }
if (isset(TASK_PRIO[$fp])) { $sql .= ' AND t.prioridade = ?'; $par[] = $fp; }
$list = rows($sql, $par);

$today = date('Y-m-d');
$open = array_filter($list, fn($t) => $t['status'] !== 'feito');
$nOpen = count($open);
$nOver = count(array_filter($open, fn($t) => $t['prazo'] && $t['prazo'] < $today));
$nToday = count(array_filter($open, fn($t) => $t['prazo'] === $today));
$nDone = count($list) - $nOpen;
$base = ['quem' => $who, 'cliente' => $fc ?: '', 'prio' => $fp, 'v' => $view];
$ret = url('tarefas', array_filter($base, fn($v) => $v !== '' && $v !== 0));
?>
<div class="sec-h big"><div><h3>Todas as tarefas</h3><p class="muted">Quem faz o quê, para qual cliente e até quando. Clique em uma linha para abrir.</p></div>
  <button class="btn primary" data-open="dlg-task" data-new><?= icon('plus', 16) ?> Nova tarefa</button></div>

<section class="grid stats">
  <?php foreach ([['Em aberto', $nOpen, 'tasks', 0], ['Atrasadas', $nOver, 'bell', $nOver ? 1 : 0], ['Vencem hoje', $nToday, 'calendar', 0], ['Concluídas', $nDone, 'check', 0]] as [$l, $v, $ic, $al]): ?>
    <div class="card stat <?= $al ? 'alert' : '' ?>"><span class="stat-ico"><?= icon($ic, 20) ?></span><b><?= (int) $v ?></b><small><?= e($l) ?></small></div>
  <?php endforeach; ?>
</section>

<form class="filters" method="get"><input type="hidden" name="p" value="tarefas"><input type="hidden" name="v" value="<?= $view ?>">
  <select name="quem" onchange="this.form.submit()">
    <option value="todos">Toda a equipe</option><option value="eu" <?= $who === 'eu' ? 'selected' : '' ?>>Minhas tarefas</option><option value="sem" <?= $who === 'sem' ? 'selected' : '' ?>>Sem responsável</option>
    <?php foreach (rows('SELECT id, nome FROM users WHERE ativo = 1 ORDER BY nome') as $u): ?><option value="<?= $u['id'] ?>" <?= (string) $who === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option><?php endforeach; ?></select>
  <select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option>
    <?php foreach (visible_clients() as $c): ?><option value="<?= $c['id'] ?>" <?= $fc === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
  <select name="prio" onchange="this.form.submit()"><option value="">Qualquer prioridade</option>
    <?php foreach (TASK_PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $fp === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
  <?php render_task_view_toggle($view, fn($v) => url('tarefas', array_filter(['v' => $v] + $base, fn($x) => $x !== ''))); ?>
</form>

<?php if (!$list) echo empty_state('tasks', 'Nenhuma tarefa por aqui', 'Crie a primeira tarefa e atribua a alguém da equipe.'); else render_tasks_view($list, $view, $ret); ?>
<?php dlg_task(null, $ret); dlg_task_view($ret); ?>
