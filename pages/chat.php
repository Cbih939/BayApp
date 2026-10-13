<?php
$cid = (int) ($_GET['id'] ?? 0);
$cur = $cid ? row('SELECT id, nome, empresa FROM clients WHERE id = ?', [$cid]) : null;
if ($cur) chat_mark_read((int) $cur['id'], 'team'); // antes de listar, para a conversa aberta não aparecer como não lida
$convs = chat_conversations();
if (!$cur && $convs) {
    $cur = ['id' => $convs[0]['id'], 'nome' => $convs[0]['nome'], 'empresa' => $convs[0]['empresa']];
    chat_mark_read((int) $cur['id'], 'team');
    $convs = chat_conversations();
}
$others = rows("SELECT id, nome FROM clients WHERE status = 'ativo' ORDER BY nome");
?>
<div class="chat-layout">
  <aside class="card chat-side">
    <div class="sec-h"><h3>Conversas</h3></div>
    <div class="chat-convs" id="convList" data-current="<?= (int) ($cur['id'] ?? 0) ?>">
      <?php foreach ($convs as $c): ?>
        <a class="conv <?= $cur && $cur['id'] === $c['id'] ? 'active' : '' ?>" href="<?= url('chat', ['id' => $c['id']]) ?>">
          <span class="avatar-wrap"><span class="avatar"><?= e(initials($c['nome'])) ?></span><i class="dot <?= $c['online'] ? 'on' : '' ?>" title="<?= $c['online'] ? 'Online' : 'Offline' ?>"></i></span>
          <span class="conv-main"><b><?= e($c['nome']) ?></b><small><?= e($c['preview']) ?></small></span>
          <?php if ($c['unread']): ?><em class="badge"><?= $c['unread'] ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <form method="get" action="index.php" class="chat-new"><input type="hidden" name="p" value="chat">
      <select name="id" onchange="this.form.submit()"><option value="">Iniciar conversa com…</option>
        <?php foreach ($others as $o): ?><option value="<?= $o['id'] ?>"><?= e($o['nome']) ?></option><?php endforeach; ?></select></form>
  </aside>
  <div class="chat-main">
    <?php if ($cur): ?>
      <header class="chat-head"><span class="avatar"><?= e(initials($cur['nome'])) ?></span>
        <div><h3><?= e($cur['nome']) ?></h3><p class="muted"><?= e($cur['empresa'] ?: 'Portal do cliente') ?></p></div>
        <?php $on = chat_client_online((int) $cur['id']); ?>
        <span class="presence" id="presence" data-viewer="team"><i class="dot <?= $on ? 'on' : '' ?>"></i><span><?= $on ? 'Cliente online' : 'Cliente offline' ?></span></span>
        <a class="btn ghost sm" href="<?= url('cliente', ['id' => $cur['id']]) ?>">Ver cliente</a></header>
      <?php render_chat_box((int) $cur['id'], 'team', 'index.php?p=chat_api', 'Nenhuma mensagem ainda. Escreva para iniciar a conversa com este cliente.'); ?>
    <?php else: echo empty_state('chat', 'Nenhuma conversa ainda', 'Escolha um cliente em “Iniciar conversa com…” ou aguarde uma mensagem pelo portal.'); endif; ?>
  </div>
</div>
