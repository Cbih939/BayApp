<?php
/* Página do cliente: tudo o que é dele fica aqui, em abas. Só a aba aberta é carregada. */
$id = (int) ($_GET['id'] ?? 0);
$c = row('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$c || !client_allowed($id)) { flash('Cliente não encontrado.', 'err'); redirect(url('clientes')); }
$R = fn(string $tab, array $x = []) => url('cliente', ['id' => $id, 'tab' => $tab] + $x);
$pageCrumb = [[url('clientes'), 'Clientes'], [null, $c['nome']]];

$tabDefs = [
    't-plan' => ['Pacote', 'package', true, null],
    't-task' => ['Tarefas', 'tasks', true, (int) val("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status <> 'feito'", [$id])],
    't-cal' => ['Calendário', 'cal', true, null],
    't-bill' => ['Pagamentos', 'card', can('billing.view'), can('billing.view') ? (int) val("SELECT COUNT(*) FROM billings WHERE client_id = ? AND status = 'pendente'", [$id]) : 0],
    't-rep' => ['Relatórios', 'chart', true, null],
    't-chat' => ['Mensagens', 'chat', true, ($_GET['tab'] ?? '') === 't-chat' ? 0 : chat_unread_team($id)],
    't-link' => ['Pastas & Links', 'folder', true, (int) val('SELECT COUNT(*) FROM links WHERE client_id = ?', [$id])],
    't-cred' => ['Senhas', 'key', can('creds.view'), can('creds.view') ? (int) val('SELECT COUNT(*) FROM credentials WHERE client_id = ?', [$id]) : 0],
    't-portal' => ['Portal', 'users', can('clients.edit'), null],
];
$tabDefs = array_filter($tabDefs, fn($t) => $t[2]);
$tab = isset($tabDefs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 't-plan';
$ret = $R($tab);
?>
<section class="card profile" data-item="<?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>">
  <span class="avatar lg"><?= e(initials($c['nome'])) ?></span>
  <div class="profile-main"><h1><?= e($c['nome']) ?> <span class="chip tag-<?= $c['status'] === 'ativo' ? 'ok' : 'paid' ?>"><?= e($c['status']) ?></span></h1>
    <div class="profile-meta">
      <?php if ($c['empresa']): ?><span><?= e($c['empresa']) ?></span><?php endif; ?>
      <?php if ($c['documento']): ?><span><?= e($c['documento']) ?></span><?php endif; ?>
      <?php if ($c['email']): ?><a href="mailto:<?= e($c['email']) ?>"><?= icon('mail', 14) ?> <?= e($c['email']) ?></a><?php endif; ?>
      <?php if ($c['whatsapp'] || $c['telefone']): $w = only_digits($c['whatsapp'] ?: $c['telefone']); ?><a target="_blank" rel="noopener" href="https://wa.me/<?= e($w) ?>"><?= icon('phone', 14) ?> <?= e($c['whatsapp'] ?: $c['telefone']) ?></a><?php endif; ?>
      <?php if ($c['site']): ?><a target="_blank" rel="noopener noreferrer" href="<?= e($c['site']) ?>"><?= icon('external', 14) ?> site</a><?php endif; ?></div>
    <?php if ($c['notas']): ?><p class="note"><?= e($c['notas']) ?></p><?php endif; ?></div>
  <?php if (can('clients.edit')): ?><button type="button" class="btn sm" data-edit="dlg-client"><?= icon('edit', 15) ?> Editar</button><?php endif; ?>
</section>

<nav class="client-tabs" aria-label="Áreas do cliente">
  <?php foreach ($tabDefs as $k => [$label, $ico, , $n]): ?>
    <a class="ctab <?= $tab === $k ? 'active' : '' ?>" href="<?= e($R($k)) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= icon($ico, 16) ?> <span><?= e($label) ?></span><?php if ($n): ?><em class="<?= $k === 't-chat' ? 'hot' : '' ?>"><?= $n ?></em><?php endif; ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 't-plan'):
  $contract = contract_active($id);
  $contracts = rows('SELECT k.*, p.nome AS plano FROM contracts k LEFT JOIN plans p ON p.id = k.plan_id WHERE k.client_id = ? ORDER BY k.status, k.inicio DESC', [$id]);
  $ym = date('Y-m'); ?>
  <div class="plan-grid">
    <div class="card contract">
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
            <?php if (can('delete')) echo delete_btn('contract_delete', (int) $contract['id'], $ret, 'este contrato'); ?></div><?php endif; ?>
        </div>
      <?php else: echo empty_state('file', 'Nenhum contrato em vigor', 'Cadastre o contrato com início, pacote, valor e o link do documento (Drive).'); endif; ?>
    </div>
    <div class="card">
      <div class="sec-h"><h3>O que o cliente tem direito</h3></div>
      <?php $usage = plan_usage($id, $ym); if ($usage): ?>
        <p class="muted small" style="margin-top:0">Pacote <b><?= e($contract['plano']) ?></b> · uso em <?= e(MONTHS_PT[(int) substr($ym, 5, 2)]) ?> (contado pelo calendário de postagem)</p>
        <?php render_usage($usage); ?>
      <?php else: echo '<p class="muted">' . ($contract ? 'O contrato não tem pacote vinculado.' : 'Vincule um pacote ao contrato para ver as entregas do mês.') . '</p>'; endif; ?>
      <?php if ($usage && can('posts.edit')): $nextYm = date('Y-m', strtotime('first day of next month'));
        $cyc = array_column(rows('SELECT competencia, gerados FROM contract_cycles WHERE contract_id = ? ORDER BY competencia DESC LIMIT 3', [$contract['id']]), 'gerados', 'competencia'); ?>
        <div class="deliv">
          <p class="muted small">As entregas do pacote entram no calendário em rascunho, uma por item, e o cron abre o mês seguinte sozinho no dia 1.<?= isset($cyc[$ym]) ? ' Este mês já foi aberto.' : '' ?></p>
          <div class="row-btns"><?php foreach ([$ym, $nextYm] as $m): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="deliveries_generate"><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="m" value="<?= $m ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
              <button class="btn sm<?= $m === $ym ? ' primary' : '' ?>"><?= icon('cal', 15) ?> Gerar entregas de <?= e(month_label($m)) ?></button></form><?php endforeach; ?></div>
          <?php if ($extra = plan_extras($id, $ym)): ?><p class="note warn">Além do pacote neste mês: <?= e(implode(', ', $extra)) ?>. Pode ser uma demanda extra para orçar.</p><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (can('clients.edit')): ?><p class="small"><a class="link" href="<?= url('pacotes') ?>">Gerenciar os pacotes da agência →</a></p><?php endif; ?>
    </div>
  </div>
  <?php $past = array_values(array_filter($contracts, fn($k) => !$contract || (int) $k['id'] !== (int) $contract['id'])); if ($past): ?>
    <div class="sec-h"><h3>Contratos anteriores</h3></div>
    <div class="grid cards"><?php foreach ($past as $k): ?>
      <article class="card inactive" data-item="<?= e(json_encode($k, JSON_UNESCAPED_UNICODE)) ?>"><h4><?= e($k['plano'] ?: 'Sem pacote') ?></h4>
        <p class="muted"><?= fmt_date($k['inicio']) ?> → <?= $k['fim'] ? fmt_date($k['fim']) : '—' ?> · <?= e($k['status']) ?></p>
        <?php if (can('clients.edit')): ?><div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-contract" title="Editar"><?= icon('edit') ?></button>
          <?php if (can('delete')) echo delete_btn('contract_delete', (int) $k['id'], $ret, 'este contrato'); ?></div><?php endif; ?></article>
    <?php endforeach; ?></div><?php endif; ?>
  <?php if (can('clients.edit')): ?>
  <dialog id="dlg-contract" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
    <input type="hidden" name="action" value="contract_save"><input type="hidden" name="id" value=""><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
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
  </form></dialog><?php endif; ?>

<?php elseif ($tab === 't-task'):
  $tasks = rows(TASK_SELECT . ' WHERE t.client_id = ?', [$id]);
  $view = ($_GET['v'] ?? 'lista') === 'quadro' ? 'quadro' : 'lista';
  $taskRet = $R('t-task', ['v' => $view]); ?>
  <div class="sec-h"><?php render_task_view_toggle($view, fn($v) => $R('t-task', ['v' => $v])); ?><button class="btn primary sm" data-open="dlg-task" data-new><?= icon('plus', 16) ?> Nova tarefa</button></div>
  <?php if (!$tasks) echo empty_state('tasks', 'Nenhuma tarefa', 'Crie tarefas para este cliente e atribua à equipe.'); else render_tasks_view($tasks, $view, $taskRet, false); ?>
  <?php dlg_task($id, $taskRet); dlg_task_view($taskRet); ?>

<?php elseif ($tab === 't-cal'):
  $calRet = render_calendar($id, ['p' => 'cliente', 'id' => $id, 'tab' => 't-cal']);
  render_usage(plan_usage($id, cal_filters($_GET)['m']));
  calendar_dialogs($id, $calRet); ?>

<?php elseif ($tab === 't-bill'):
  $sub = ($_GET['sub'] ?? '') === 'cal' ? 'cal' : 'list';
  $cycOpen = cycle_open_events($id);
  $cycPaid = cycle_paid_events($id, false, null, null, 36);
  $payRet = $R('t-bill'); ?>
  <div data-subroot>
  <div class="sec-h"><div class="seg sub-toggle"><button type="button" class="pill <?= $sub === 'list' ? 'active' : '' ?>" data-sub="list"><?= icon('tasks', 15) ?> Lista</button>
    <button type="button" class="pill <?= $sub === 'cal' ? 'active' : '' ?>" data-sub="cal"><?= icon('cal', 15) ?> Calendário</button></div>
    <?php if (can('billing.edit')): ?><button class="btn primary sm" data-open="dlg-bill" data-new><?= icon('plus', 16) ?> Novo serviço</button><?php endif; ?></div>
  <div class="sub-pane" data-pane="list" <?= $sub === 'list' ? '' : 'hidden' ?>>
    <div class="sec-h"><h3 class="grp">Em aberto <em><?= count($cycOpen) ?></em></h3></div>
    <?php if (!$cycOpen) echo empty_state('card', 'Nada em aberto', 'Cadastre os serviços do cliente (hospedagem, domínio, redes sociais…) com o tipo de vencimento.'); ?>
    <div class="grid cards wide"><?php foreach ($cycOpen as $ev) render_cycle_card($ev, $payRet); ?></div>
    <div class="sec-h"><h3 class="grp">Pagos <em><?= count($cycPaid) ?></em></h3></div>
    <?php if (!$cycPaid) echo '<p class="muted">Nenhum pagamento registrado ainda.</p>'; ?>
    <div class="grid cards wide"><?php foreach ($cycPaid as $ev) render_cycle_card($ev, $payRet); ?></div>
  </div>
  <div class="sub-pane" data-pane="cal" <?= $sub === 'cal' ? '' : 'hidden' ?>><?php $calPayRet = render_paycal($id, ['p' => 'cliente', 'id' => $id, 'tab' => 't-bill', 'sub' => 'cal']); ?></div>
  </div>
  <?php dlg_bill($id, $sub === 'cal' ? $calPayRet : $payRet); pay_dialogs($sub === 'cal' ? $calPayRet : $payRet); ?>

<?php elseif ($tab === 't-rep'):
  require_once __DIR__ . '/_reports.php';
  render_reports(report_filters(['cliente' => $id] + $_GET), ['p' => 'cliente', 'id' => $id, 'tab' => 't-rep'], true); ?>

<?php elseif ($tab === 't-chat'): ?>
  <?php $on = chat_client_online($id); ?>
  <div class="sec-h"><p class="muted">Conversa com o cliente pelo portal.</p>
    <span class="presence" id="presence" data-viewer="team"><i class="dot <?= $on ? 'on' : '' ?>"></i><span><?= $on ? 'Cliente online' : 'Cliente offline' ?></span></span></div>
  <?php render_chat_box($id, 'team', 'index.php?p=chat_api', 'Nenhuma mensagem ainda. Escreva para iniciar a conversa com este cliente.'); ?>

<?php elseif ($tab === 't-link'):
  $links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$id]); ?>
  <div class="sec-h"><h3>Pastas &amp; links</h3><?php if (can('links.edit')): ?><button class="btn primary sm" data-open="dlg-link" data-new><?= icon('plus', 16) ?> Novo link</button><?php endif; ?></div>
  <?php if (!$links) echo empty_state('folder', 'Sem links ainda', 'Adicione a pasta do Drive, o Canva e a Google Agenda deste cliente.'); ?>
  <div class="grid cards"><?php foreach ($links as $l) render_link_card($l, $ret); ?></div>
  <?php dlg_link($id, $ret); ?>

<?php elseif ($tab === 't-cred'):
  $creds = rows('SELECT * FROM credentials WHERE client_id = ? ORDER BY categoria, titulo', [$id]); ?>
  <div class="sec-h"><h3>Senhas e acessos</h3><?php if (can('creds.edit')): ?><button class="btn primary sm" data-open="dlg-cred" data-new><?= icon('plus', 16) ?> Novo acesso</button><?php endif; ?></div>
  <?php if (!$creds) echo empty_state('key', 'Nenhum acesso guardado', 'Guarde logins de redes sociais, sites, hospedagem e e-mails com criptografia.'); ?>
  <div class="grid cards"><?php foreach ($creds as $x) render_cred_card($x, $ret); ?></div>
  <?php dlg_cred($id, $ret); ?>

<?php elseif ($tab === 't-portal'):
  $portal = rows('SELECT id, client_id, nome, email, ativo, ultimo_login FROM client_users WHERE client_id = ? ORDER BY nome', [$id]);
  $portalUrl = request_base_url() . '/portal.php'; ?>
  <div class="sec-h"><div><h3>Acesso ao portal do cliente</h3><p class="muted">O cliente vê apenas os próprios pagamentos, links e mensagens (nunca senhas ou observações internas).</p></div>
    <button class="btn primary sm" data-open="dlg-portal" data-new><?= icon('plus', 16) ?> Novo acesso</button></div>
  <div class="codebox" style="margin-bottom:18px"><code><?= e($portalUrl) ?></code><button class="icon-btn" data-copy="<?= e($portalUrl) ?>" title="Copiar link"><?= icon('copy', 16) ?></button></div>
  <?php if (!$portal) echo empty_state('users', 'Nenhum acesso criado', 'Crie um login para o cliente acompanhar pagamentos e acessar pastas e links.'); ?>
  <div class="grid cards"><?php foreach ($portal as $pu): ?>
    <article class="card user <?= $pu['ativo'] ? '' : 'inactive' ?>" data-item="<?= e(json_encode($pu)) ?>">
      <header><span class="avatar"><?= e(initials($pu['nome'])) ?></span><div><h4><?= e($pu['nome']) ?></h4><p class="muted"><?= e($pu['email']) ?></p></div></header>
      <div class="chips"><span class="chip dim">último acesso: <?= $pu['ultimo_login'] ? ago($pu['ultimo_login']) : 'nunca' ?></span><?php if (!$pu['ativo']): ?><span class="chip">inativo</span><?php endif; ?></div>
      <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-portal" title="Editar / redefinir senha"><?= icon('edit') ?></button>
        <?php if (can('delete')) echo delete_btn('portal_user_delete', (int) $pu['id'], $ret, 'este acesso ao portal'); ?></div>
    </article><?php endforeach; ?></div>
  <dialog id="dlg-portal" class="modal"><form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
    <input type="hidden" name="action" value="portal_user_save"><input type="hidden" name="id" value=""><input type="hidden" name="client_id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($ret) ?>">
    <header class="modal-h"><h3 data-title="Novo acesso ao portal|Editar acesso ao portal">Novo acesso ao portal</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
    <div class="grid2"><label>Nome<input name="nome" required value=""></label><label>E-mail (login)<input type="email" name="email" required></label></div>
    <label>Senha <span class="muted" data-keep hidden>(vazio = manter)</span><div class="pwwrap"><input name="senha" type="text" class="mono" minlength="8" autocomplete="new-password"><button type="button" class="icon-btn" data-gen title="Gerar senha forte"><?= icon('dice') ?></button></div></label>
    <label class="switch"><input type="checkbox" name="ativo" checked><span></span> Acesso ativo</label>
    <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar acesso</button></footer>
  </form></dialog>
<?php endif; ?>
<?php if (can('clients.edit')) dlg_client($ret); ?>
