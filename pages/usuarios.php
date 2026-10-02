<?php
$list = rows('SELECT * FROM users ORDER BY ativo DESC, papel, nome');
$ret = url('usuarios');
?>
<div class="sec-h big"><div><h3>Equipe</h3><p class="muted">Administradores, designers e desenvolvedores com acessos por perfil.</p></div>
  <button class="btn primary" data-open="dlg-user" data-new><?= icon('plus', 18) ?> Novo usuário</button></div>
<section class="grid cards">
<?php foreach ($list as $i => $u): $item = $u; unset($item['senha_hash']); ?>
  <article class="card user tilt anim-up <?= $u['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 10) ?>" data-item="<?= e(json_encode($item)) ?>">
    <header><span class="avatar"><?= e(initials($u['nome'])) ?></span><div><h4><?= e($u['nome']) ?></h4><p class="muted"><?= e($u['email']) ?></p></div></header>
    <div class="chips"><span class="chip role-<?= e($u['papel']) ?>"><?= e(ROLES[$u['papel']]) ?></span><?php if (!$u['ativo']): ?><span class="chip">inativo</span><?php endif; ?>
      <span class="chip dim">último acesso: <?= $u['ultimo_login'] ? ago($u['ultimo_login']) : 'nunca' ?></span></div>
    <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-user" title="Editar"><?= icon('edit') ?></button>
      <?php if ((int) $u['id'] !== (int) $me['id']) echo delete_btn('user_delete', (int) $u['id'], $ret, 'o usuário ' . $u['nome']); ?></div>
  </article>
<?php endforeach; ?>
</section>
<section class="card perms anim-up"><h4>O que cada perfil pode fazer</h4>
  <table><thead><tr><th>Recurso</th><th>Administrador</th><th>Desenvolvedor</th><th>Designer</th></tr></thead><tbody>
    <tr><td>Ver senhas, vencimentos e links</td><td>✔</td><td>✔</td><td>✔</td></tr>
    <tr><td>Editar senhas e links</td><td>✔</td><td>✔</td><td>✔</td></tr>
    <tr><td>Cadastrar/editar clientes</td><td>✔</td><td>✔</td><td>—</td></tr>
    <tr><td>Editar vencimentos</td><td>✔</td><td>✔</td><td>—</td></tr>
    <tr><td>Excluir registros</td><td>✔</td><td>—</td><td>—</td></tr>
    <tr><td>Equipe e configurações</td><td>✔</td><td>—</td><td>—</td></tr></tbody></table></section>
<dialog id="dlg-user" class="modal"><form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
  <input type="hidden" name="action" value="user_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo usuário|Editar usuário">Novo usuário</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Nome<input name="nome" required></label><label>E-mail (login)<input type="email" name="email" required></label></div>
  <div class="grid2"><label>Perfil<select name="papel"><?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Telefone<input name="telefone"></label></div>
  <label>Senha <span class="muted" data-keep hidden>(vazio = manter)</span><div class="pwwrap"><input name="senha" type="text" class="mono" minlength="8" autocomplete="new-password"><button type="button" class="icon-btn" data-gen><?= icon('dice') ?></button></div></label>
  <label class="switch"><input type="checkbox" name="ativo" checked><span></span> Usuário ativo</label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar usuário</button></footer>
</form></dialog>
