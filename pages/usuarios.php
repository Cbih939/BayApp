<?php
$ret0 = url('usuarios');
$tab = in_array($_GET['t'] ?? '', ['portal', 'atividade'], true) ? $_GET['t'] : 'equipe';
$ret = url('usuarios', ['t' => $tab]);
$list = rows('SELECT * FROM users ORDER BY ativo DESC, papel, nome');
$portal = rows('SELECT cu.*, c.nome AS cliente, c.empresa FROM client_users cu JOIN clients c ON c.id = cu.client_id ORDER BY cu.ativo DESC, c.nome, cu.nome');
$canPortal = can('clients.edit');
$stat = ['total' => count($list), 'ativos' => 0, 'admins' => 0, 'nunca' => 0];
foreach ($list as $x) { if ($x['ativo']) $stat['ativos']++; if ($x['papel'] === 'admin' && $x['ativo']) $stat['admins']++; if (!$x['ultimo_login']) $stat['nunca']++; }
$pAtivos = count(array_filter($portal, fn($x) => $x['ativo']));

/* Atividade */
$fu = $_GET['u'] ?? ''; $fa = preg_replace('/[^a-z_]/', '', (string) ($_GET['a'] ?? ''));
$pg = max(1, (int) ($_GET['pg'] ?? 1)); $per = 40;
$where = []; $par = [];
if ($fu === 'portal') $where[] = "a.acao LIKE 'portal%'";
elseif ($fu !== '' && ctype_digit((string) $fu)) { $where[] = 'a.user_id = ?'; $par[] = (int) $fu; }
if ($fa !== '') { $where[] = 'a.acao = ?'; $par[] = $fa; }
$W = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$totalAud = 0; $audit = [];
if ($tab === 'atividade') {
    $totalAud = (int) val("SELECT COUNT(*) FROM audit a $W", $par);
    $audit = rows("SELECT a.*, u.nome AS usuario FROM audit a LEFT JOIN users u ON u.id = a.user_id $W ORDER BY a.id DESC LIMIT $per OFFSET " . (($pg - 1) * $per), $par);
}
$acoes = $tab === 'atividade' ? array_column(rows('SELECT DISTINCT acao FROM audit ORDER BY acao'), 'acao') : [];
$audQ = fn(array $o = []) => url('usuarios', array_filter(['t' => 'atividade', 'u' => $fu, 'a' => $fa] + $o, fn($v) => $v !== '' && $v !== null));
?>
<div class="sec-h big"><div><h3>Usuários</h3><p class="muted">Equipe, acessos ao portal dos clientes e histórico de atividade.</p></div>
  <button class="btn primary" data-open="dlg-user" data-new><?= icon('plus', 18) ?> Novo usuário</button></div>
<section class="grid cards stats">
  <article class="card"><h4><?= $stat['ativos'] ?>/<?= $stat['total'] ?></h4><p class="muted">usuários da equipe ativos</p></article>
  <article class="card"><h4><?= $stat['admins'] ?></h4><p class="muted">administradores ativos</p></article>
  <article class="card"><h4><?= $stat['nunca'] ?></h4><p class="muted">nunca acessaram</p></article>
  <article class="card"><h4><?= $pAtivos ?>/<?= count($portal) ?></h4><p class="muted">acessos ao portal ativos</p></article>
</section>
<div class="tabs" role="tablist" style="margin-top:22px">
  <button class="tab <?= $tab === 'equipe' ? 'active' : '' ?>" data-tab="t-equipe"><?= icon('users', 16) ?> Equipe <em><?= count($list) ?></em></button>
  <button class="tab <?= $tab === 'portal' ? 'active' : '' ?>" data-tab="t-portalu"><?= icon('briefcase', 16) ?> Clientes (portal) <em><?= count($portal) ?></em></button>
  <button class="tab <?= $tab === 'atividade' ? 'active' : '' ?>" data-tab="t-ativ"><?= icon('shield', 16) ?> Atividade</button>
</div>

<section class="tabpane <?= $tab === 'equipe' ? 'active' : '' ?>" id="t-equipe">
<section class="grid cards">
<?php foreach ($list as $i => $u): $item = $u; unset($item['senha_hash']); $self = (int) $u['id'] === (int) $me['id']; ?>
  <article class="card user tilt anim-up <?= $u['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 10) ?>" data-item="<?= e(json_encode($item)) ?>">
    <header><span class="avatar"><?= e(initials($u['nome'])) ?></span><div><h4><?= e($u['nome']) ?><?= $self ? ' <small class="muted">(você)</small>' : '' ?></h4><p class="muted"><?= e($u['email']) ?></p></div></header>
    <div class="chips"><span class="chip role-<?= e($u['papel']) ?>"><?= e(ROLES[$u['papel']]) ?></span><?php if (!$u['ativo']): ?><span class="chip">inativo</span><?php endif; ?>
      <?php if ($u['telefone']): ?><span class="chip dim"><?= e($u['telefone']) ?></span><?php endif; ?>
      <span class="chip dim">último acesso: <?= $u['ultimo_login'] ? ago($u['ultimo_login']) : 'nunca' ?></span>
      <span class="chip dim">desde <?= fmt_date($u['criado_em']) ?></span></div>
    <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-user" title="Editar"><?= icon('edit') ?></button>
      <a class="icon-btn" href="<?= e($audQ(['u' => (string) $u['id']])) ?>" title="Ver atividade"><?= icon('shield') ?></a>
      <?= user_action_btn('user_reset', (int) $u['id'], $ret, 'key', 'Redefinir senha (gera uma nova)', 'Gerar nova senha para ' . $u['nome'] . '? A senha atual deixará de funcionar.') ?>
      <?php if (!$self): echo user_action_btn('user_toggle', (int) $u['id'], $ret, $u['ativo'] ? 'x' : 'play', $u['ativo'] ? 'Desativar' : 'Reativar', ($u['ativo'] ? 'Desativar ' : 'Reativar ') . $u['nome'] . '?');
        echo delete_btn('user_delete', (int) $u['id'], $ret, 'o usuário ' . $u['nome']); endif; ?></div>
  </article>
<?php endforeach; ?>
</section>
<section class="card perms anim-up"><h4>O que cada perfil pode fazer</h4>
  <table><thead><tr><th>Recurso</th><th>Administrador</th><th>Desenvolvedor</th><th>Designer</th></tr></thead><tbody>
    <tr><td>Ver senhas, vencimentos e links</td><td>✔</td><td>✔</td><td>✔</td></tr>
    <tr><td>Editar senhas e links</td><td>✔</td><td>✔</td><td>✔</td></tr>
    <tr><td>Cadastrar/editar clientes e acessos do portal</td><td>✔</td><td>✔</td><td>—</td></tr>
    <tr><td>Editar vencimentos</td><td>✔</td><td>✔</td><td>—</td></tr>
    <tr><td>Excluir registros</td><td>✔</td><td>—</td><td>—</td></tr>
    <tr><td>Equipe, atividade e configurações</td><td>✔</td><td>—</td><td>—</td></tr></tbody></table></section>
</section>

<section class="tabpane <?= $tab === 'portal' ? 'active' : '' ?>" id="t-portalu">
<?php if (!$portal): ?><p class="muted">Nenhum cliente tem acesso ao portal ainda. Crie em Clientes → (cliente) → aba Portal.</p><?php endif; ?>
<section class="grid cards">
<?php foreach ($portal as $i => $x): ?>
  <article class="card user anim-up <?= $x['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 10) ?>">
    <header><span class="avatar"><?= e(initials($x['nome'])) ?></span><div><h4><?= e($x['nome']) ?></h4><p class="muted"><?= e($x['email']) ?></p></div></header>
    <div class="chips"><a class="chip" href="<?= e(url('cliente', ['id' => $x['client_id']])) ?>"><?= e($x['empresa'] ?: $x['cliente']) ?></a>
      <?php if (!$x['ativo']): ?><span class="chip">inativo</span><?php endif; ?>
      <span class="chip dim">último acesso: <?= $x['ultimo_login'] ? ago($x['ultimo_login']) : 'nunca' ?></span></div>
    <?php if ($canPortal): ?><div class="card-actions row">
      <?= user_action_btn('portal_user_reset', (int) $x['id'], $ret, 'key', 'Redefinir senha (gera uma nova)', 'Gerar nova senha do portal para ' . $x['nome'] . '?') ?>
      <?= user_action_btn('portal_user_toggle', (int) $x['id'], $ret, $x['ativo'] ? 'x' : 'play', $x['ativo'] ? 'Desativar' : 'Reativar', ($x['ativo'] ? 'Desativar o acesso de ' : 'Reativar o acesso de ') . $x['nome'] . '?') ?>
      <?php if (can('delete')) echo delete_btn('portal_user_delete', (int) $x['id'], $ret, 'o acesso de ' . $x['nome']); ?></div><?php endif; ?>
  </article>
<?php endforeach; ?>
</section></section>

<section class="tabpane <?= $tab === 'atividade' ? 'active' : '' ?>" id="t-ativ">
  <form method="get" class="row" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px"><input type="hidden" name="p" value="usuarios"><input type="hidden" name="t" value="atividade">
    <select name="u"><option value="">Todos os usuários</option><option value="portal" <?= $fu === 'portal' ? 'selected' : '' ?>>Clientes (portal)</option>
      <?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (string) $fu === (string) $x['id'] ? 'selected' : '' ?>><?= e($x['nome']) ?></option><?php endforeach; ?></select>
    <select name="a"><option value="">Todas as ações</option>
      <?php foreach ($acoes as $ac): ?><option value="<?= e($ac) ?>" <?= $fa === $ac ? 'selected' : '' ?>><?= e(AUDIT_LABELS[$ac] ?? $ac) ?></option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button></form>
  <section class="card perms"><table><thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Detalhe</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($audit as $a): ?><tr><td><?= e(date('d/m H:i', strtotime($a['criado_em']))) ?></td>
      <td><?= e($a['usuario'] ?? (strpos($a['acao'], 'portal') === 0 ? 'Cliente (portal)' : '—')) ?></td>
      <td><?= e(AUDIT_LABELS[$a['acao']] ?? $a['acao']) ?></td><td><?= e($a['detalhe']) ?></td><td><?= e($a['ip']) ?></td></tr>
    <?php endforeach; if (!$audit): ?><tr><td colspan="5" class="muted">Nada registrado com esses filtros.</td></tr><?php endif; ?></tbody></table></section>
  <?php if ($totalAud > $per): ?><div class="row" style="display:flex;gap:10px;align-items:center;margin-top:14px;justify-content:center">
    <?php if ($pg > 1): ?><a class="btn ghost sm" href="<?= e($audQ(['pg' => $pg - 1])) ?>">← Anteriores</a><?php endif; ?>
    <span class="muted">Página <?= $pg ?> de <?= (int) ceil($totalAud / $per) ?></span>
    <?php if ($pg * $per < $totalAud): ?><a class="btn ghost sm" href="<?= e($audQ(['pg' => $pg + 1])) ?>">Próximas →</a><?php endif; ?></div><?php endif; ?>
</section>
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
