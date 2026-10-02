<?php
$id = (int) ($_GET['id'] ?? 0);
$c = row('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$c) { flash('Cliente não encontrado.', 'err'); redirect(url('clientes')); }
$ret = url('cliente', ['id' => $id]);
$creds = can('creds.view') ? rows('SELECT * FROM credentials WHERE client_id = ? ORDER BY categoria, titulo', [$id]) : [];
$bills = can('billing.view') ? rows('SELECT b.*, ? AS cliente FROM billings b WHERE client_id = ? ORDER BY status DESC, vencimento', [$c['nome'], $id]) : [];
$links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$id]);
$portal = can('clients.edit') ? rows('SELECT id, client_id, nome, email, ativo, ultimo_login FROM client_users WHERE client_id = ? ORDER BY nome', [$id]) : [];
$portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/portal.php';
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
  <?php if (can('clients.edit')): ?><button class="tab" data-tab="t-portal"><?= icon('users', 16) ?> Portal <em><?= count($portal) ?></em></button><?php endif; ?>
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
<?php if (can('clients.edit')): ?>
<section class="tabpane" id="t-portal">
  <div class="sec-h"><div><h3>Acesso ao portal do cliente</h3><p class="muted">O cliente vê apenas os próprios vencimentos e links (nunca senhas ou observações internas).</p></div>
    <button class="btn primary sm" data-open="dlg-portal" data-new><?= icon('plus', 16) ?> Novo acesso</button></div>
  <div class="codebox" style="margin-bottom:18px"><code><?= e($portalUrl) ?></code><button class="icon-btn" data-copy="<?= e($portalUrl) ?>" title="Copiar link"><?= icon('copy', 16) ?></button></div>
  <?php if (!$portal) echo empty_state('users', 'Nenhum acesso criado', 'Crie um login para o cliente acompanhar vencimentos e acessar pastas e links.'); ?>
  <div class="grid cards"><?php foreach ($portal as $i => $pu): ?>
    <article class="card user tilt anim-up <?= $pu['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 8) ?>" data-item="<?= e(json_encode($pu)) ?>">
      <header><span class="avatar"><?= e(initials($pu['nome'])) ?></span><div><h4><?= e($pu['nome']) ?></h4><p class="muted"><?= e($pu['email']) ?></p></div></header>
      <div class="chips"><span class="chip dim">último acesso: <?= $pu['ultimo_login'] ? ago($pu['ultimo_login']) : 'nunca' ?></span><?php if (!$pu['ativo']): ?><span class="chip">inativo</span><?php endif; ?></div>
      <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-portal" title="Editar / redefinir senha"><?= icon('edit') ?></button>
        <?php if (can('delete')) echo delete_btn('portal_user_delete', (int) $pu['id'], $ret, 'este acesso ao portal'); ?></div>
    </article><?php endforeach; ?></div>
</section>
<dialog id="dlg-portal" class="modal"><form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
  <input type="hidden" name="action" value="portal_user_save"><input type="hidden" name="id" value=""><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo acesso ao portal|Editar acesso ao portal">Novo acesso ao portal</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Nome<input name="nome" required value=""></label><label>E-mail (login)<input type="email" name="email" required></label></div>
  <label>Senha <span class="muted" data-keep hidden>(vazio = manter)</span><div class="pwwrap"><input name="senha" type="text" class="mono" minlength="8" autocomplete="new-password"><button type="button" class="icon-btn" data-gen title="Gerar senha forte"><?= icon('dice') ?></button></div></label>
  <label class="switch"><input type="checkbox" name="ativo" checked><span></span> Acesso ativo</label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar acesso</button></footer>
</form></dialog>
<?php endif; ?>
<?php dlg_cred($id, $ret); dlg_bill($id, $ret); dlg_link($id, $ret); ?>
