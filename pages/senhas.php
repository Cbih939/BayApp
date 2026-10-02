<?php
$fc = (int) ($_GET['client'] ?? 0); $fk = $_GET['cat'] ?? '';
$sql = 'SELECT x.*, c.nome AS cliente FROM credentials x JOIN clients c ON c.id = x.client_id WHERE 1=1';
$par = [];
if ($fc) { $sql .= ' AND x.client_id = ?'; $par[] = $fc; }
if (isset(CRED_CATS[$fk])) { $sql .= ' AND x.categoria = ?'; $par[] = $fk; }
$list = rows($sql . ' ORDER BY c.nome, x.categoria, x.titulo', $par);
$ret = url('senhas', array_filter(['client' => $fc, 'cat' => $fk]));
?>
<div class="sec-h big"><div><h3>Cofre de senhas</h3><p class="muted">Armazenadas com criptografia AES-256. Cada visualização é registrada.</p></div>
  <?php if (can('creds.edit')): ?><button class="btn primary" data-open="dlg-cred" data-new><?= icon('plus', 18) ?> Novo acesso</button><?php endif; ?></div>
<form class="filters" method="get"><input type="hidden" name="p" value="senhas">
  <select name="client" onchange="this.form.submit()"><option value="">Todos os clientes</option><?php foreach (rows('SELECT id, nome FROM clients ORDER BY nome') as $c): ?><option value="<?= $c['id'] ?>" <?= $fc === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
  <select name="cat" onchange="this.form.submit()"><option value="">Todos os tipos</option><?php foreach (CRED_CATS as $k => $v): ?><option value="<?= $k ?>" <?= $fk === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></form>
<?php if (!$list) echo empty_state('key', 'Nenhum acesso encontrado', 'Use “Novo acesso” — escolha o tipo e os campos são preenchidos para você.'); ?>
<section class="grid cards"><?php foreach ($list as $i => $x): ?><div class="anim-up" style="--i:<?= min($i, 10) ?>"><?php render_cred_card($x, $ret); ?></div><?php endforeach; ?></section>
<?php dlg_cred($fc ?: null, $ret); ?>
