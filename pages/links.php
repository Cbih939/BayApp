<?php
$ft = $_GET['tipo'] ?? '';
$sql = 'SELECT l.*, c.nome AS cliente FROM links l LEFT JOIN clients c ON c.id = l.client_id'; $par = [];
if (isset(LINK_TYPES[$ft])) { $sql .= ' WHERE l.tipo = ?'; $par[] = $ft; }
$list = rows($sql . ' ORDER BY c.nome, l.tipo, l.titulo', $par);
$ret = url('links', array_filter(['tipo' => $ft]));
?>
<div class="sec-h big"><div><h3>Pastas &amp; Links</h3><p class="muted">Drive, Canva e Google Agenda dos clientes a um clique.</p></div>
  <?php if (can('links.edit')): ?><button class="btn primary" data-open="dlg-link" data-new><?= icon('plus', 18) ?> Novo link</button><?php endif; ?></div>
<div class="pills"><a class="pill <?= $ft === '' ? 'active' : '' ?>" href="<?= url('links') ?>">Todos</a>
  <?php foreach (LINK_TYPES as $k => $v): ?><a class="pill <?= $ft === $k ? 'active' : '' ?>" href="<?= url('links', ['tipo' => $k]) ?>"><?= e($v) ?></a><?php endforeach; ?></div>
<?php if (!$list) echo empty_state('folder', 'Nenhum link por aqui', 'Cole o link da pasta do Drive, do Canva ou da agenda — o tipo é detectado sozinho.'); ?>
<section class="grid cards"><?php foreach ($list as $i => $l): ?><div class="anim-up" style="--i:<?= min($i, 10) ?>"><?php render_link_card($l, $ret); ?></div><?php endforeach; ?></section>
<?php dlg_link(null, $ret); ?>
