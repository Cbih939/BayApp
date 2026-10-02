<?php
$id = (int) ($_GET['id'] ?? 0);
$c = row('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$c) { flash('Cliente não encontrado.', 'err'); redirect(url('clientes')); }
$ret = url('cliente', ['id' => $id]);
$creds = can('creds.view') ? rows('SELECT * FROM credentials WHERE client_id = ? ORDER BY categoria, titulo', [$id]) : [];
$bills = can('billing.view') ? rows('SELECT b.*, ? AS cliente FROM billings b WHERE client_id = ? ORDER BY status DESC, vencimento', [$c['nome'], $id]) : [];
$links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$id]);
?>
<a href="<?= url('clientes') ?>" class="link back">← Todos os clientes</a>
<section class="card profile anim-up">
  <span class="avatar xl"><?= e(initials($c['nome'])) ?></span>
  <div class="profile-main"><h1><?= e($c['nome']) ?></h1><p class="muted"><?= e($c['empresa'] ?: '') ?><?= $c['documento'] ? ' · ' . e($c['documento']) : '' ?></p>
    <div class="chips">
      <?php if ($c['email']): ?><a class="chip" href="mailto:<?= e($c['email']) ?>"><?= icon('mail', 14) ?> <?= e($c['email']) ?></a><?php endif; ?>
      <?php if ($c['whatsapp'] || $c['telefone']): $w = only_digits($c['whatsapp'] ?: $c['telefone']); ?><a class="chip" target="_blank" rel="noopener" href="https://wa.me/<?= e($w) ?>"><?= icon('phone', 14) ?> <?= e($c['whatsapp'] ?: $c['telefone']) ?></a><?php endif; ?>
      <?php if ($c['site']): ?><a class="chip" target="_blank" rel="noopener noreferrer" href="<?= e($c['site']) ?>"><?= icon('external', 14) ?> site</a><?php endif; ?>
      <span class="chip tag-<?= $c['status'] === 'ativo' ? 'ok' : 'paid' ?>"><?= e($c['status']) ?></span></div>
    <?php if ($c['notas']): ?><p class="note"><?= e($c['notas']) ?></p><?php endif; ?></div>
</section>

<div class="tabs" role="tablist">
  <?php if (can('creds.view')): ?><button class="tab active" data-tab="t-cred"><?= icon('key', 16) ?> Senhas <em><?= count($creds) ?></em></button><?php endif; ?>
  <?php if (can('billing.view')): ?><button class="tab <?= can('creds.view') ? '' : 'active' ?>" data-tab="t-bill"><?= icon('calendar', 16) ?> Vencimentos <em><?= count($bills) ?></em></button><?php endif; ?>
  <button class="tab <?= can('creds.view') || can('billing.view') ? '' : 'active' ?>" data-tab="t-link"><?= icon('folder', 16) ?> Pastas &amp; Links <em><?= count($links) ?></em></button>
</div>

<?php if (can('creds.view')): ?>
<section class="tabpane active" id="t-cred">
  <div class="sec-h"><h3>Senhas e acessos</h3><?php if (can('creds.edit')): ?><button class="btn primary sm" data-open="dlg-cred" data-new><?= icon('plus', 16) ?> Novo acesso</button><?php endif; ?></div>
  <?php if (!$creds) echo empty_state('key', 'Nenhum acesso guardado', 'Guarde logins de redes sociais, sites, hospedagem e e-mails com criptografia.'); ?>
  <div class="grid cards"><?php foreach ($creds as $i => $x): ?><div class="anim-up" style="--i:<?= min($i, 10) ?>"><?php render_cred_card($x, $ret); ?></div><?php endforeach; ?></div>
</section><?php endif; ?>
<?php if (can('billing.view')): ?>
<section class="tabpane <?= can('creds.view') ? '' : 'active' ?>" id="t-bill">
  <div class="sec-h"><h3>Vencimentos</h3><?php if (can('billing.edit')): ?><button class="btn primary sm" data-open="dlg-bill" data-new><?= icon('plus', 16) ?> Novo vencimento</button><?php endif; ?></div>
  <?php if (!$bills) echo empty_state('calendar', 'Nada a vencer', 'Cadastre domínio, hospedagem, e-mail… e receba alertas de 30, 15 e 7 dias.'); ?>
  <div class="stack-cards"><?php foreach ($bills as $b) render_bill_card($b, false, $ret); ?></div>
</section><?php endif; ?>
<section class="tabpane <?= can('creds.view') || can('billing.view') ? '' : 'active' ?>" id="t-link">
  <div class="sec-h"><h3>Pastas &amp; links</h3><?php if (can('links.edit')): ?><button class="btn primary sm" data-open="dlg-link" data-new><?= icon('plus', 16) ?> Novo link</button><?php endif; ?></div>
  <?php if (!$links) echo empty_state('folder', 'Sem links ainda', 'Adicione a pasta do Drive, o Canva e a Google Agenda deste cliente.'); ?>
  <div class="grid cards"><?php foreach ($links as $l) render_link_card($l, $ret); ?></div>
</section>
<?php dlg_cred($id, $ret); dlg_bill($id, $ret); dlg_link($id, $ret); ?>
