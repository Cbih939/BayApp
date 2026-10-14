<?php
$id = (int) ($_GET['id'] ?? 0);
$c = row('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$c) { flash('Cliente não encontrado.', 'err'); redirect(url('clientes')); }
$ret = url('cliente', ['id' => $id]);
$R = fn(string $tab, array $x = []) => url('cliente', ['id' => $id, 'tab' => $tab] + $x);
$creds = can('creds.view') ? rows('SELECT * FROM credentials WHERE client_id = ? ORDER BY categoria, titulo', [$id]) : [];
$cycOpen = can('billing.view') ? cycle_open_events($id) : [];
$cycPaid = can('billing.view') ? cycle_paid_events($id, false, null, null, 36) : [];
$sub = ($_GET['sub'] ?? '') === 'cal' ? 'cal' : 'list';
$payRet = $R('t-bill');
$tasks = task_sort(rows(TASK_SELECT . ' WHERE t.client_id = ?', [$id]));
$links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$id]);
$tabIds = ['t-plan', 't-cal', 't-cred', 't-bill', 't-task', 't-link', 't-portal'];
$tab = in_array($_GET['tab'] ?? '', $tabIds, true) ? $_GET['tab'] : 't-plan';
$act = fn(string $id) => $tab === $id ? 'active' : '';
$contract = contract_active($id);
$contracts = rows('SELECT k.*, p.nome AS plano FROM contracts k LEFT JOIN plans p ON p.id = k.plan_id WHERE k.client_id = ? ORDER BY k.status, k.inicio DESC', [$id]);
$ym = date('Y-m');
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
      <a class="chip" href="<?= url('chat', ['id' => $id]) ?>"><?= icon('chat', 14) ?> mensagens</a>
      <span class="chip tag-<?= $c['status'] === 'ativo' ? 'ok' : 'paid' ?>"><?= e($c['status']) ?></span></div>
    <?php if ($c['notas']): ?><p class="note"><?= e($c['notas']) ?></p><?php endif; ?></div>
</section>

<div class="tabs" role="tablist">
  <button class="tab <?= $act('t-plan') ?>" data-tab="t-plan"><?= icon('file', 16) ?> Contrato &amp; pacote</button>
  <button class="tab <?= $act('t-cal') ?>" data-tab="t-cal"><?= icon('cal', 16) ?> Calendário</button>
  <?php if (can('creds.view')): ?><button class="tab <?= $act('t-cred') ?>" data-tab="t-cred"><?= icon('key', 16) ?> Senhas <em><?= count($creds) ?></em></button><?php endif; ?>
  <?php if (can('billing.view')): ?><button class="tab <?= $act('t-bill') ?>" data-tab="t-bill"><?= icon('card', 16) ?> Pagamentos <em><?= count($cycOpen) ?></em></button><?php endif; ?>
  <button class="tab <?= $act('t-task') ?>" data-tab="t-task"><?= icon('tasks', 16) ?> Tarefas <em><?= count(array_filter($tasks, fn($t) => $t['status'] !== 'feito')) ?></em></button>
  <button class="tab <?= $act('t-link') ?>" data-tab="t-link"><?= icon('folder', 16) ?> Pastas &amp; Links <em><?= count($links) ?></em></button>
  <?php if (can('clients.edit')): ?><button class="tab <?= $act('t-portal') ?>" data-tab="t-portal"><?= icon('users', 16) ?> Portal <em><?= count($portal) ?></em></button><?php endif; ?>
</div>

<section class="tabpane <?= $act('t-plan') ?>" id="t-plan">
  <div class="plan-grid">
    <div class="card contract anim-up">
      <div class="sec-h"><h3>Contrato em vigor</h3><?php if (can('clients.edit')): ?><button class="btn primary sm" data-open="dlg-contract" data-new><?= icon('plus', 16) ?> <?= $contract ? 'Novo contrato' : 'Cadastrar contrato' ?></button><?php endif; ?></div>
      <?php if ($contract): $dEnd = $contract['fim'] ? days_until($contract['fim']) : null; ?>
        <div data-item="<?= e(json_encode($contract, JSON_UNESCAPED_UNICODE)) ?>">
          <h2 class="contract-plan"><?= e($contract['plano'] ?: 'Sem pacote definido') ?></h2>
          <dl class="kv">
            <div><dt>Início</dt><dd><?= fmt_date($contract['inicio']) ?></dd></div>
            <div><dt>Término</dt><dd><?= $contract['fim'] ? fmt_date($contract['fim']) : 'Prazo indeterminado' ?><?php if ($dEnd !== null && $dEnd <= 60): ?> <span class="chip tag-<?= $dEnd <= 15 ? 'over' : 'd15' ?>">termina em <?= $dEnd ?> d</span><?php endif; ?></dd></div>
            <div><dt>Valor</dt><dd><?= fmt_money($contract['valor']) ?> <small class="muted"><?= e(mb_strtolower(REC_LABELS[$contract['periodicidade']] ?? '')) ?></small></dd></div>
            <div><dt>Há quanto tempo</dt><dd><?= e(contract_age($contract['inicio'])) ?></dd></div>
          </dl>
          <?php if ($contract['notas']): ?><p class="note"><?= e($contract['notas']) ?></p><?php endif; ?>
          <div class="chips"><?php if ($contract['url']): ?><a class="btn ghost sm" href="<?= e($contract['url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('file', 15) ?> Abrir contrato</a><?php else: ?><span class="chip dim">sem link do contrato</span><?php endif; ?></div>
          <?php if (can('clients.edit')): ?><div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-contract" title="Editar contrato"><?= icon('edit') ?></button>
            <?php if (can('delete')) echo delete_btn('contract_delete', (int) $contract['id'], $R('t-plan'), 'este contrato'); ?></div><?php endif; ?>
        </div>
      <?php else: echo empty_state('file', 'Nenhum contrato em vigor', 'Cadastre o contrato com início, pacote, valor e o link do documento (Drive).'); endif; ?>
    </div>
    <div class="card anim-up" style="--i:1">
      <div class="sec-h"><h3>O que o cliente tem direito</h3></div>
      <?php $usage = plan_usage($id, $ym); if ($usage): ?>
        <p class="muted small" style="margin-top:0">Pacote <b><?= e($contract['plano']) ?></b> · uso em <?= e(MONTHS_PT[(int) substr($ym, 5, 2)]) ?> (contado pelo calendário de postagem)</p>
        <?php render_usage($usage); ?>
      <?php else: echo '<p class="muted">' . ($contract ? 'O contrato não tem pacote vinculado.' : 'Vincule um pacote ao contrato para ver as entregas do mês.') . '</p>'; endif; ?>
      <?php if (can('clients.edit')): ?><p class="small"><a class="link" href="<?= url('pacotes') ?>">Gerenciar pacotes →</a></p><?php endif; ?>
    </div>
  </div>
  <?php $past = array_values(array_filter($contracts, fn($k) => !$contract || (int) $k['id'] !== (int) $contract['id'])); if ($past): ?>
    <div class="sec-h"><h3>Contratos anteriores</h3></div>
    <div class="grid cards"><?php foreach ($past as $k): ?>
      <article class="card tilt inactive" data-item="<?= e(json_encode($k, JSON_UNESCAPED_UNICODE)) ?>"><h4><?= e($k['plano'] ?: 'Sem pacote') ?></h4>
        <p class="muted"><?= fmt_date($k['inicio']) ?> → <?= $k['fim'] ? fmt_date($k['fim']) : '—' ?> · <?= e($k['status']) ?></p>
        <?php if (can('clients.edit')): ?><div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-contract" title="Editar"><?= icon('edit') ?></button>
          <?php if (can('delete')) echo delete_btn('contract_delete', (int) $k['id'], $R('t-plan'), 'este contrato'); ?></div><?php endif; ?></article>
    <?php endforeach; ?></div><?php endif; ?>
</section>

<section class="tabpane <?= $act('t-cal') ?>" id="t-cal">
  <?php $calRet = render_calendar($id, ['p' => 'cliente', 'id' => $id, 'tab' => 't-cal']); render_usage(plan_usage($id, cal_filters($_GET)['m'])); ?>
</section>

<?php if (can('creds.view')): ?>
<section class="tabpane <?= $act('t-cred') ?>" id="t-cred">
  <div class="sec-h"><h3>Senhas e acessos</h3><?php if (can('creds.edit')): ?><button class="btn primary sm" data-open="dlg-cred" data-new><?= icon('plus', 16) ?> Novo acesso</button><?php endif; ?></div>
  <?php if (!$creds) echo empty_state('key', 'Nenhum acesso guardado', 'Guarde logins de redes sociais, sites, hospedagem e e-mails com criptografia.'); ?>
  <div class="grid cards"><?php foreach ($creds as $i => $x): ?><div class="anim-up" style="--i:<?= min($i, 10) ?>"><?php render_cred_card($x, $R('t-cred')); ?></div><?php endforeach; ?></div>
</section><?php endif; ?>
<?php if (can('billing.view')): ?>
<section class="tabpane <?= $act('t-bill') ?>" id="t-bill">
  <div class="sec-h"><div><h3>Pagamentos do cliente</h3><p class="muted">Vencimento <b>fixo</b> (1x ao ano) e <b>variável</b> (mês a mês): hospedagem, domínio e outros serviços. Anexe o boleto e a quitação em cada pagamento — o cliente vê no portal.</p></div>
    <?php if (can('billing.edit')): ?><button class="btn primary sm" data-open="dlg-bill" data-new><?= icon('plus', 16) ?> Novo serviço</button><?php endif; ?></div>
  <div class="seg sub-toggle"><button type="button" class="pill <?= $sub === 'list' ? 'active' : '' ?>" data-sub="list"><?= icon('tasks', 15) ?> Lista</button>
    <button type="button" class="pill <?= $sub === 'cal' ? 'active' : '' ?>" data-sub="cal"><?= icon('cal', 15) ?> Calendário de pagamentos</button></div>
  <div class="sub-pane" data-pane="list" <?= $sub === 'list' ? '' : 'hidden' ?>>
    <div class="sec-h"><h3 class="grp tag-d15">Em aberto <em><?= count($cycOpen) ?></em></h3></div>
    <?php if (!$cycOpen) echo empty_state('card', 'Nada em aberto', 'Cadastre os serviços do cliente (hospedagem, domínio, redes sociais…) com o tipo de vencimento.'); ?>
    <div class="grid cards wide"><?php foreach ($cycOpen as $i => $ev): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_cycle_card($ev, $payRet); ?></div><?php endforeach; ?></div>
    <div class="sec-h"><h3 class="grp tag-paid">Pagos <em><?= count($cycPaid) ?></em></h3></div>
    <?php if (!$cycPaid) echo '<p class="muted">Nenhum pagamento registrado ainda.</p>'; ?>
    <div class="grid cards wide"><?php foreach ($cycPaid as $i => $ev): ?><div class="anim-up" style="--i:<?= min($i, 8) ?>"><?php render_cycle_card($ev, $payRet); ?></div><?php endforeach; ?></div>
  </div>
  <div class="sub-pane" data-pane="cal" <?= $sub === 'cal' ? '' : 'hidden' ?>><?php $calPayRet = render_paycal($id, ['p' => 'cliente', 'id' => $id, 'tab' => 't-bill', 'sub' => 'cal']); ?></div>
</section><?php endif; ?>
<section class="tabpane <?= $act('t-task') ?>" id="t-task">
  <div class="sec-h"><h3>Tarefas</h3><button class="btn primary sm" data-open="dlg-task" data-new><?= icon('plus', 16) ?> Nova tarefa</button></div>
  <?php if (!$tasks) echo empty_state('tasks', 'Nenhuma tarefa', 'Crie tarefas para este cliente e atribua à equipe.'); ?>
  <div class="grid cards"><?php foreach ($tasks as $t) render_task_card($t, $R('t-task')); ?></div>
</section>
<section class="tabpane <?= $act('t-link') ?>" id="t-link">
  <div class="sec-h"><h3>Pastas &amp; links</h3><?php if (can('links.edit')): ?><button class="btn primary sm" data-open="dlg-link" data-new><?= icon('plus', 16) ?> Novo link</button><?php endif; ?></div>
  <?php if (!$links) echo empty_state('folder', 'Sem links ainda', 'Adicione a pasta do Drive, o Canva e a Google Agenda deste cliente.'); ?>
  <div class="grid cards"><?php foreach ($links as $l) render_link_card($l, $R('t-link')); ?></div>
</section>
<?php if (can('clients.edit')): ?>
<section class="tabpane <?= $act('t-portal') ?>" id="t-portal">
  <div class="sec-h"><div><h3>Acesso ao portal do cliente</h3><p class="muted">O cliente vê apenas os próprios vencimentos e links (nunca senhas ou observações internas).</p></div>
    <button class="btn primary sm" data-open="dlg-portal" data-new><?= icon('plus', 16) ?> Novo acesso</button></div>
  <div class="codebox" style="margin-bottom:18px"><code><?= e($portalUrl) ?></code><button class="icon-btn" data-copy="<?= e($portalUrl) ?>" title="Copiar link"><?= icon('copy', 16) ?></button></div>
  <?php if (!$portal) echo empty_state('users', 'Nenhum acesso criado', 'Crie um login para o cliente acompanhar vencimentos e acessar pastas e links.'); ?>
  <div class="grid cards"><?php foreach ($portal as $i => $pu): ?>
    <article class="card user tilt anim-up <?= $pu['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 8) ?>" data-item="<?= e(json_encode($pu)) ?>">
      <header><span class="avatar"><?= e(initials($pu['nome'])) ?></span><div><h4><?= e($pu['nome']) ?></h4><p class="muted"><?= e($pu['email']) ?></p></div></header>
      <div class="chips"><span class="chip dim">último acesso: <?= $pu['ultimo_login'] ? ago($pu['ultimo_login']) : 'nunca' ?></span><?php if (!$pu['ativo']): ?><span class="chip">inativo</span><?php endif; ?></div>
      <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-portal" title="Editar / redefinir senha"><?= icon('edit') ?></button>
        <?php if (can('delete')) echo delete_btn('portal_user_delete', (int) $pu['id'], $R('t-portal'), 'este acesso ao portal'); ?></div>
    </article><?php endforeach; ?></div>
</section>
<dialog id="dlg-portal" class="modal"><form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
  <input type="hidden" name="action" value="portal_user_save"><input type="hidden" name="id" value=""><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($R('t-portal')) ?>">
  <header class="modal-h"><h3 data-title="Novo acesso ao portal|Editar acesso ao portal">Novo acesso ao portal</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Nome<input name="nome" required value=""></label><label>E-mail (login)<input type="email" name="email" required></label></div>
  <label>Senha <span class="muted" data-keep hidden>(vazio = manter)</span><div class="pwwrap"><input name="senha" type="text" class="mono" minlength="8" autocomplete="new-password"><button type="button" class="icon-btn" data-gen title="Gerar senha forte"><?= icon('dice') ?></button></div></label>
  <label class="switch"><input type="checkbox" name="ativo" checked><span></span> Acesso ativo</label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar acesso</button></footer>
</form></dialog>
<?php endif; ?>
<?php if (can('clients.edit')): ?>
<dialog id="dlg-contract" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="contract_save"><input type="hidden" name="id" value=""><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($R('t-plan')) ?>">
  <header class="modal-h"><h3 data-title="Novo contrato|Editar contrato">Novo contrato</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <label>Pacote<select name="plan_id"><option value="">— Sem pacote —</option><?php foreach (rows('SELECT id, nome FROM plans WHERE ativo = 1 ORDER BY nome') as $pl): ?><option value="<?= $pl['id'] ?>"><?= e($pl['nome']) ?></option><?php endforeach; ?></select></label>
  <div class="grid2"><label>Início do contrato<input type="date" name="inicio" required></label><label>Término <small class="muted">(vazio = indeterminado)</small><input type="date" name="fim"></label></div>
  <div class="grid2"><label>Valor (R$)<input name="valor" inputmode="decimal" placeholder="0,00"></label>
    <label>Cobrança<select name="periodicidade"><?php foreach (REC_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'mensal' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <label>Link do contrato <small class="muted">(PDF no Drive, assinatura digital…)</small><input type="url" name="url" placeholder="https://"></label>
  <label>Status<select name="status"><option value="ativo">Em vigor</option><option value="encerrado">Encerrado</option></select></label>
  <label>Observações<textarea name="notas" rows="2" placeholder="Cláusulas importantes, renovação, reajuste…"></textarea></label>
  <p class="muted small" style="margin:0">Ao cadastrar um novo contrato em vigor, o anterior é encerrado automaticamente.</p>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar contrato</button></footer>
</form></dialog>
<?php endif; ?>
<?php dlg_cred($id, $R('t-cred')); dlg_bill($id, $sub === 'cal' ? ($calPayRet ?? $payRet) : $payRet); dlg_link($id, $R('t-link')); dlg_task($id, $R('t-task')); dlg_task_view($R('t-task')); calendar_dialogs($id, $calRet);
if (can('billing.view')) pay_dialogs($sub === 'cal' ? ($calPayRet ?? $payRet) : $payRet); ?>
