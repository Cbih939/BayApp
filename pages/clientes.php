<?php
$today = date('Y-m-d');
$list = rows("SELECT c.*, (SELECT COUNT(*) FROM tasks WHERE client_id=c.id AND status<>'feito') AS n_task,
    (SELECT COUNT(*) FROM billings WHERE client_id=c.id AND status='pendente') AS n_bill,
    (SELECT COUNT(*) FROM billings WHERE client_id=c.id AND status='pendente' AND vencimento < '$today') AS n_late,
    (SELECT COUNT(*) FROM messages WHERE client_id=c.id AND sender='client' AND lida_em IS NULL) AS n_msg FROM clients c WHERE 1 = 1" . client_scope('c.id') . " ORDER BY c.status, c.nome");
$ret = url('clientes');
?>
<div class="sec-h big"><div><h3><?= count($list) ?> cliente(s)</h3><p class="muted">Clique em um cliente para ver tudo dele: pacote, tarefas, calendário, pagamentos e mais.</p></div>
  <?php if (can('clients.edit')): ?><button class="btn primary" data-open="dlg-client" data-new><?= icon('plus', 18) ?> Novo cliente</button><?php endif; ?></div>
<?php if (!$list) echo empty_state('briefcase', 'Nenhum cliente ainda', 'Cadastre o primeiro cliente para começar a organizar acessos e vencimentos.'); ?>
<section class="grid cards">
<?php foreach ($list as $i => $c): ?>
  <article class="card client <?= $c['status'] === 'inativo' ? 'inactive' : '' ?>" data-item="<?= e(json_encode($c)) ?>">
    <a class="card-link" href="<?= url('cliente', ['id' => $c['id']]) ?>" aria-label="Abrir <?= e($c['nome']) ?>"></a>
    <header><span class="avatar"><?= e(initials($c['nome'])) ?></span>
      <div><h4><?= e($c['nome']) ?></h4><p class="muted"><?= e($c['empresa'] ?: ($c['email'] ?: 'Sem empresa')) ?></p></div>
      <?php if ($c['status'] === 'inativo'): ?><span class="chip">inativo</span><?php endif; ?></header>
    <div class="metrics"><span><b><?= (int) $c['n_task'] ?></b> tarefas</span><span><b><?= (int) $c['n_bill'] ?></b> pagamentos</span></div>
    <?php if ($c['n_msg'] || $c['n_late']): ?><div class="chips z">
      <?php if ($c['n_msg']): ?><a class="chip tag-pink" href="<?= url('cliente', ['id' => $c['id'], 'tab' => 't-chat']) ?>"><?= icon('chat', 13) ?> <?= (int) $c['n_msg'] ?> nova(s)</a><?php endif; ?>
      <?php if ($c['n_late'] && can('billing.view')): ?><a class="chip tag-over" href="<?= url('cliente', ['id' => $c['id'], 'tab' => 't-bill']) ?>"><?= (int) $c['n_late'] ?> em atraso</a><?php endif; ?></div><?php endif; ?>
    <?php if (can('clients.edit')): ?><div class="card-actions row z">
      <button type="button" class="icon-btn" data-edit="dlg-client" title="Editar"><?= icon('edit') ?></button>
      <?php if (can('delete')): ?><?= delete_btn('client_delete', (int) $c['id'], $ret, 'o cliente ' . $c['nome'] . ' e todos os seus dados') ?><?php endif; ?></div><?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
<?php if (can('clients.edit')): ?>
<?php dlg_client($ret); ?>
<?php if (isset($_GET['novo'])): ?><script>window.addEventListener('DOMContentLoaded',()=>document.querySelector('[data-new]')?.click())</script><?php endif; endif; ?>
