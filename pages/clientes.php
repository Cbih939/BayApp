<?php
$list = rows("SELECT c.*, (SELECT COUNT(*) FROM credentials WHERE client_id=c.id) AS n_cred,
    (SELECT COUNT(*) FROM billings WHERE client_id=c.id AND status='pendente') AS n_bill,
    (SELECT COUNT(*) FROM links WHERE client_id=c.id) AS n_link FROM clients c ORDER BY c.status, c.nome");
$ret = url('clientes');
?>
<div class="sec-h big"><div><h3><?= count($list) ?> cliente(s)</h3><p class="muted">Clique em um card para ver senhas, vencimentos e pastas.</p></div>
  <?php if (can('clients.edit')): ?><button class="btn primary" data-open="dlg-client" data-new><?= icon('plus', 18) ?> Novo cliente</button><?php endif; ?></div>
<?php if (!$list) echo empty_state('briefcase', 'Nenhum cliente ainda', 'Cadastre o primeiro cliente para começar a organizar acessos e vencimentos.'); ?>
<section class="grid cards">
<?php foreach ($list as $i => $c): ?>
  <article class="card client tilt anim-up <?= $c['status'] === 'inativo' ? 'inactive' : '' ?>" style="--i:<?= min($i, 12) ?>" data-item="<?= e(json_encode($c)) ?>">
    <a class="card-link" href="<?= url('cliente', ['id' => $c['id']]) ?>" aria-label="Abrir <?= e($c['nome']) ?>"></a>
    <header><span class="avatar"><?= e(initials($c['nome'])) ?></span>
      <div><h4><?= e($c['nome']) ?></h4><p class="muted"><?= e($c['empresa'] ?: ($c['email'] ?: 'Sem empresa')) ?></p></div>
      <?php if ($c['status'] === 'inativo'): ?><span class="chip">inativo</span><?php endif; ?></header>
    <div class="metrics"><span><b><?= (int) $c['n_cred'] ?></b> senhas</span><span><b><?= (int) $c['n_bill'] ?></b> vencimentos</span><span><b><?= (int) $c['n_link'] ?></b> links</span></div>
    <?php if (can('clients.edit')): ?><div class="card-actions row z">
      <button type="button" class="icon-btn" data-edit="dlg-client" title="Editar"><?= icon('edit') ?></button>
      <?php if (can('delete')): ?><?= delete_btn('client_delete', (int) $c['id'], $ret, 'o cliente ' . $c['nome'] . ' e todos os seus dados') ?><?php endif; ?></div><?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
<?php if (can('clients.edit')): ?>
<dialog id="dlg-client" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="client_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo cliente|Editar cliente">Novo cliente</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Nome / contato<input name="nome" required></label><label>Empresa<input name="empresa"></label></div>
  <div class="grid2"><label>E-mail<input type="email" name="email"></label><label>CPF / CNPJ<input name="documento"></label></div>
  <div class="grid2"><label>Telefone<input name="telefone"></label><label>WhatsApp<input name="whatsapp" placeholder="5511999999999"></label></div>
  <div class="grid2"><label>Site<input name="site" placeholder="https://"></label>
    <label>Status<select name="status"><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select></label></div>
  <label>Observações<textarea name="notas" rows="3"></textarea></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar cliente</button></footer>
</form></dialog>
<?php if (isset($_GET['novo'])): ?><script>window.addEventListener('DOMContentLoaded',()=>document.querySelector('[data-new]')?.click())</script><?php endif; endif; ?>
