<?php
$fc = (int) ($_GET['cliente'] ?? 0);
$clients = rows('SELECT id, nome FROM clients ORDER BY nome');
?>
<div class="sec-h big"><div><h3>Calendário de postagem</h3><p class="muted">Veja e planeje as publicações de todos os clientes. Clique em um post para ler o conteúdo, ou em “Adicionar” no dia.</p></div></div>
<form class="filters" method="get"><input type="hidden" name="p" value="calendario"><input type="hidden" name="m" value="<?= e(cal_filters($_GET)['m']) ?>">
  <select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option>
    <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $fc === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></form>
<?php
$pairs = ['p' => 'calendario'] + ($fc ? ['cliente' => $fc] : []);
$ret = render_calendar($fc ?: null, $pairs);
if ($fc) render_usage(plan_usage($fc, cal_filters($_GET)['m']));
calendar_dialogs($fc ?: null, $ret);
