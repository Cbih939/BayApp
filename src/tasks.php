<?php
declare(strict_types=1);

const TASK_STATUS = ['a_fazer' => 'A fazer', 'fazendo' => 'Em andamento', 'revisao' => 'Em revisão', 'feito' => 'Concluída'];
const TASK_PRIO = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'];
const TASK_PRIO_WEIGHT = ['urgente' => 0, 'alta' => 1, 'media' => 2, 'baixa' => 3];

/** Equipe ativa para o seletor de responsável. */
function team_options(?int $sel = null, string $empty = '— Sem responsável —'): string
{
    $o = '<option value="">' . e($empty) . '</option>';
    foreach (rows('SELECT id, nome FROM users WHERE ativo = 1 ORDER BY nome') as $u) {
        $o .= '<option value="' . $u['id'] . '"' . ($sel === (int) $u['id'] ? ' selected' : '') . '>' . e($u['nome']) . '</option>';
    }
    return $o;
}

/** [classe, rótulo] do prazo da tarefa. */
function task_urgency(array $t): array
{
    if ($t['status'] === 'feito') return ['paid', $t['concluida_em'] ? 'feita em ' . fmt_date($t['concluida_em']) : 'feita'];
    if (!$t['prazo']) return ['none', 'sem prazo'];
    $d = days_until($t['prazo']);
    if ($d < 0) return ['over', 'atrasada ' . abs($d) . ' d'];
    if ($d === 0) return ['over', 'vence hoje'];
    if ($d === 1) return ['d7', 'amanhã'];
    if ($d <= 7) return ['d7', "em $d dias"];
    return ['ok', fmt_date($t['prazo'])];
}

/** Ordena: prioridade, depois prazo (sem prazo por último). */
function task_sort(array $list): array
{
    usort($list, fn($a, $b) => [TASK_PRIO_WEIGHT[$a['prioridade']] ?? 2, $a['prazo'] ?: '9999-99-99', $a['id']]
        <=> [TASK_PRIO_WEIGHT[$b['prioridade']] ?? 2, $b['prazo'] ?: '9999-99-99', $b['id']]);
    return $list;
}

const TASK_SELECT = 'SELECT t.*, c.nome AS cliente, u.nome AS responsavel FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id LEFT JOIN users u ON u.id = t.assignee_id';

/** Tarefas em aberto que são do usuário (atrasadas, para o selo do menu). */
function tasks_overdue_mine(int $uid): int
{
    return (int) val("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status <> 'feito' AND prazo IS NOT NULL AND prazo < ?", [$uid, date('Y-m-d')]);
}

function task_move_to(int $id, string $status): void
{
    if (!isset(TASK_STATUS[$status])) throw new RuntimeException('Status inválido.');
    if (!row('SELECT id FROM tasks WHERE id = ?', [$id])) throw new RuntimeException('Tarefa não encontrada.');
    update('tasks', $id, ['status' => $status, 'concluida_em' => $status === 'feito' ? now() : null, 'atualizado_em' => now()]);
}

/** Itens do checklist de uma tarefa (carrega todos de uma vez na primeira chamada). */
function task_items_for(int $id): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (rows('SELECT id, task_id, texto, feito FROM task_items ORDER BY ordem, id') as $r) {
            $map[(int) $r['task_id']][] = ['id' => (int) $r['id'], 'texto' => $r['texto'], 'feito' => (int) $r['feito']];
        }
    }
    return $map[$id] ?? [];
}

/** Pastas e links do cliente da tarefa (ou os links gerais, quando a tarefa não tem cliente). */
function task_links_for(?int $cid): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (rows('SELECT client_id, tipo, titulo, url FROM links ORDER BY tipo, titulo') as $l) {
            $map[(int) $l['client_id']][] = ['tipo' => LINK_TYPES[$l['tipo']] ?? 'Link', 'titulo' => $l['titulo'], 'url' => $l['url']];
        }
    }
    return $map[(int) $cid] ?? [];
}

/** Dados da tarefa que o modal de leitura usa (vão em data-item do cartão). */
function task_item(array $t): array
{
    [$cls, $label] = task_urgency($t);
    $item = array_intersect_key($t, array_flip(['id', 'titulo', 'descricao', 'link', 'client_id', 'assignee_id', 'prioridade', 'status', 'prazo']));
    return $item + ['cliente' => (string) $t['cliente'], 'responsavel' => (string) $t['responsavel'], 'status_label' => TASK_STATUS[$t['status']] ?? '',
        'prio_label' => TASK_PRIO[$t['prioridade']] ?? '', 'prazo_label' => $label, 'prazo_cls' => $cls, 'itens' => task_items_for((int) $t['id']),
        'links' => task_links_for($t['client_id'] ? (int) $t['client_id'] : null)];
}

/** Linha compacta (usada na página de Pastas & Links): clicar abre o modal da tarefa. */
function render_task_mini(array $t): void
{
    [$cls, $label] = task_urgency($t);
    [$cd, $ct] = task_progress((int) $t['id']);
    ?>
    <article class="card task tmini prio-<?= e($t['prioridade']) ?>" tabindex="0" role="button" data-id="<?= (int) $t['id'] ?>" data-item="<?= e(json_encode(task_item($t), JSON_UNESCAPED_UNICODE)) ?>">
      <h4><?= e($t['titulo']) ?></h4>
      <div class="chips"><span class="chip"><?= e(TASK_STATUS[$t['status']] ?? '') ?></span><span class="chip tag-<?= $cls ?>"><?= icon('calendar', 12) ?> <?= e($label) ?></span>
        <?php if ($ct): ?><span class="chip dim"><?= icon('check', 12) ?> <?= $cd ?>/<?= $ct ?></span><?php endif; ?>
        <?php if ($t['link']): ?><span class="chip dim" title="Tem link"><?= icon('link', 12) ?></span><?php endif; ?></div>
    </article>
    <?php
}

/** [feitos, total] do checklist. */
function task_progress(int $id): array
{
    $it = task_items_for($id);
    return [count(array_filter($it, fn($i) => $i['feito'])), count($it)];
}

/** Substitui o checklist pelo enviado no formulário (item_texto[] / item_feito[]). */
function task_save_items(int $taskId, array $texts, array $done): void
{
    q('DELETE FROM task_items WHERE task_id = ?', [$taskId]);
    $n = 0;
    foreach ($texts as $i => $t) {
        $t = trim((string) $t);
        if ($t === '' || $n >= 100) continue;
        insert('task_items', ['task_id' => $taskId, 'texto' => mb_substr($t, 0, 300), 'feito' => !empty($done[$i]) ? 1 : 0, 'ordem' => $n++]);
    }
}

/** Só aceita links http(s). */
function clean_link(string $l): ?string
{
    $l = trim($l);
    if ($l === '') return null;
    if (!preg_match('~^https?://\S+$~i', $l)) throw new RuntimeException('O link precisa começar com http:// ou https://');
    return mb_substr($l, 0, 700);
}

/** Concluídas (todas), mais recentes primeiro. */
function rows_all_done(array $list): array
{
    $done = array_values(array_filter($list, fn($t) => $t['status'] === 'feito'));
    usort($done, fn($a, $b) => strcmp((string) $b['concluida_em'], (string) $a['concluida_em']));
    return $done;
}

function render_task_card(array $t, string $ret): void
{
    [$cls, $label] = task_urgency($t);
    $keys = array_keys(TASK_STATUS);
    $i = array_search($t['status'], $keys, true);
    $item = task_item($t);
    [$cd, $ct] = task_progress((int) $t['id']);
    ?>
    <article class="card task prio-<?= e($t['prioridade']) ?> <?= $t['status'] === 'feito' ? 'done' : '' ?>" draggable="true" data-id="<?= (int) $t['id'] ?>" data-item="<?= e(json_encode($item, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" role="button" aria-label="Abrir tarefa <?= e($t['titulo']) ?>">
      <h4><?= e($t['titulo']) ?></h4>
      <?php if ($t['cliente']): ?><p class="muted"><?= icon('briefcase', 13) ?> <a href="<?= url('cliente', ['id' => $t['client_id']]) ?>"><?= e($t['cliente']) ?></a></p><?php endif; ?>
      <?php if ($t['descricao']): ?><p class="note"><?= e(mb_strimwidth($t['descricao'], 0, 110, '…')) ?></p><?php endif; ?>
      <?php if ($ct): ?><div class="tprog" title="<?= $cd ?> de <?= $ct ?> itens do checklist"><span class="bar"><i style="width:<?= (int) round(100 * $cd / $ct) ?>%"></i></span><small><?= $cd ?>/<?= $ct ?></small></div><?php endif; ?>
      <div class="chips">
        <?php if ($t['link']): ?><a class="chip" href="<?= e($t['link']) ?>" target="_blank" rel="noopener noreferrer" title="Abrir link da tarefa"><?= icon('link', 13) ?> link</a><?php endif; ?>
        <span class="chip prio-chip"><?= e(TASK_PRIO[$t['prioridade']] ?? '') ?></span>
        <?php if ($cls !== 'none' || $t['status'] !== 'feito'): ?><span class="chip tag-<?= $cls ?>"><?= icon('calendar', 13) ?> <?= e($label) ?></span><?php endif; ?>
        <?php if ($t['responsavel']): ?><span class="chip dim" title="Responsável"><?= e(explode(' ', $t['responsavel'])[0]) ?></span><?php endif; ?>
      </div>
      <div class="card-actions row task-actions">
        <?php if ($i > 0): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="task_move"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="status" value="<?= $keys[$i - 1] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn" title="Mover para <?= e(TASK_STATUS[$keys[$i - 1]]) ?>">←</button></form><?php endif; ?>
        <?php if ($i < count($keys) - 1): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="task_move"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="status" value="<?= $keys[$i + 1] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn <?= $i + 1 === count($keys) - 1 ? 'ok' : '' ?>" title="Mover para <?= e(TASK_STATUS[$keys[$i + 1]]) ?>"><?= $i + 1 === count($keys) - 1 ? icon('check') : '→' ?></button></form><?php endif; ?>
        <button type="button" class="icon-btn" data-edit="dlg-task" title="Editar"><?= icon('edit') ?></button>
        <?php if (can('delete')) echo delete_btn('task_delete', (int) $t['id'], $ret, 'esta tarefa'); ?>
      </div>
    </article>
    <?php
}

function dlg_task(?int $client = null, string $ret = ''): void
{ ?>
<dialog id="dlg-task" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="task_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Nova tarefa|Editar tarefa">Nova tarefa</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <label>Título<input name="titulo" required maxlength="200" placeholder="Ex.: Criar artes do feed de outubro"></label>
  <?php if ($client): ?><input type="hidden" name="client_id" value="<?= $client ?>"><?php else: ?>
  <label>Cliente<select name="client_id"><?= client_options(null, true) ?></select></label><?php endif; ?>
  <div class="grid2"><label>Responsável<select name="assignee_id"><?= team_options() ?></select></label>
    <label>Prazo<input type="date" name="prazo"></label></div>
  <div class="grid2"><label>Prioridade<select name="prioridade"><?php foreach (TASK_PRIO as $k => $v): ?><option value="<?= $k ?>" <?= $k === 'media' ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status"><?php foreach (TASK_STATUS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <label>Link <small class="muted">(Drive, Canva, briefing…)</small><input type="url" name="link" maxlength="700" placeholder="https://"></label>
  <label>Detalhes<textarea name="descricao" rows="3" placeholder="O que precisa ser feito, referências, observações…"></textarea></label>
  <div class="field"><label>Checklist <small class="muted">(lista de tarefas com check)</small></label>
    <div class="chk-rows" data-rows="itens"></div>
    <button type="button" class="btn ghost sm" data-addrow="itens"><?= icon('plus', 14) ?> Adicionar item</button>
    <template data-tpl="itens"><div class="chk-row"><input type="checkbox" class="chk-box" title="Marcar como feito"><input type="hidden" name="item_feito[]" value="0" data-k="feito">
      <input type="text" name="item_texto[]" data-k="texto" maxlength="300" placeholder="Ex.: Aprovar roteiro com o cliente"><button type="button" class="icon-btn danger" data-delrow title="Remover"><?= icon('x', 14) ?></button></div></template></div>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar tarefa</button></footer>
</form></dialog>
<?php }

/** Modal de leitura da tarefa (preenchido por JS a partir do data-item do cartão). */
function dlg_task_view(string $ret): void
{ ?>
<dialog id="dlg-taskview" class="modal taskview"><div id="tv-item">
  <header class="modal-h"><div><p class="eyebrow" id="tv-client"></p><h3 id="tv-title"></h3></div><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="chips" id="tv-chips"></div>
  <a class="btn ghost sm" id="tv-link" target="_blank" rel="noopener noreferrer" hidden><?= icon('link', 15) ?> <span>Abrir link</span></a>
  <h4 class="tv-h" id="tv-links-h" hidden>Pastas e links do cliente</h4><div class="chips tv-links" id="tv-links"></div>
  <h4 class="tv-h">Detalhes</h4><p class="tv-desc" id="tv-desc"></p>
  <h4 class="tv-h">Checklist <span class="muted" id="tv-count"></span></h4>
  <span class="bar tv-bar"><i id="tv-bar"></i></span>
  <ul class="tv-list" id="tv-list"></ul>
  <form class="tv-add" id="tv-add"><input type="text" id="tv-new" maxlength="300" placeholder="Adicionar item ao checklist e pressionar Enter…"><button class="icon-btn" title="Adicionar"><?= icon('plus') ?></button></form>
  <footer class="modal-f tv-foot">
    <form method="post" id="tv-move"><?= csrf_field() ?><input type="hidden" name="action" value="task_move"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
      <select name="status" onchange="this.form.submit()" title="Mudar status"><?php foreach (TASK_STATUS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></form>
    <button type="button" class="btn ghost" data-edit="dlg-task"><?= icon('edit', 16) ?> Editar</button>
    <button type="button" class="btn primary" data-close>Fechar</button>
  </footer>
</div></dialog>
<?php }

/** Resumo diário por e-mail: tarefas atrasadas ou que vencem até amanhã, uma mensagem por responsável. Roda no máximo 1x/dia. */
function run_task_alerts(): array
{
    $today = date('Y-m-d');
    if (setting('task_digest_date') === $today) return ['usuarios' => 0, 'tarefas' => 0, 'ignorado' => true];
    $limit = date('Y-m-d', strtotime('+1 day'));
    $by = [];
    foreach (rows(TASK_SELECT . " WHERE t.status <> 'feito' AND t.prazo IS NOT NULL AND t.prazo <= ? AND u.ativo = 1 ORDER BY t.prazo", [$limit]) as $t) {
        $by[$t['assignee_id']][] = $t;
    }
    $sent = $n = 0;
    foreach ($by as $uid => $list) {
        $u = row('SELECT nome, email FROM users WHERE id = ?', [$uid]);
        if (!$u || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) continue;
        $li = '';
        foreach ($list as $t) {
            [, $label] = task_urgency($t);
            $li .= '<li><b>' . e($t['titulo']) . '</b>' . ($t['cliente'] ? ' — ' . e($t['cliente']) : '') . ' <i>(' . e($label) . ')</i></li>';
        }
        $html = '<div style="font-family:Arial,sans-serif"><h2>Olá, ' . e(explode(' ', $u['nome'])[0]) . '!</h2><p>Estas tarefas precisam de atenção:</p><ul>' . $li . '</ul></div>';
        if (send_mail([$u['email']], 'Tarefas para hoje — ' . setting('empresa_nome', 'BayApp'), $html)) { $sent++; $n += count($list); }
    }
    set_setting('task_digest_date', $today);
    return ['usuarios' => $sent, 'tarefas' => $n, 'ignorado' => false];
}
