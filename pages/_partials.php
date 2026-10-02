<?php
/* Componentes de visual reutilizáveis (cards) */

function client_options(?int $sel = null, bool $withEmpty = false): string
{
    $o = $withEmpty ? '<option value="">— Geral (sem cliente) —</option>' : '<option value="" disabled selected>Selecione o cliente…</option>';
    foreach (rows('SELECT id, nome FROM clients WHERE status = \'ativo\' ORDER BY nome') as $c) {
        $o .= '<option value="' . $c['id'] . '"' . ($sel === (int) $c['id'] ? ' selected' : '') . '>' . e($c['nome']) . '</option>';
    }
    return $o;
}

function empty_state(string $ico, string $title, string $msg, string $btn = ''): string
{
    return '<div class="empty anim-up">' . icon($ico, 40) . '<h3>' . e($title) . '</h3><p class="muted">' . e($msg) . '</p>' . $btn . '</div>';
}

function bill_urgency(array $b): array
{
    $d = days_until($b['vencimento']);
    if ($b['status'] === 'pago') return ['paid', 'Pago', $d];
    if ($d < 0) return ['over', 'Vencido há ' . abs($d) . ' d', $d];
    if ($d === 0) return ['over', 'Vence hoje', $d];
    if ($d <= 7) return ['d7', "$d dia" . ($d > 1 ? 's' : ''), $d];
    if ($d <= 15) return ['d15', "$d dias", $d];
    if ($d <= 30) return ['d30', "$d dias", $d];
    return ['ok', "$d dias", $d];
}

function render_bill_card(array $b, bool $showClient = true, string $ret = ''): void
{
    [$cls, $label, $d] = bill_urgency($b);
    $json = e(json_encode($b));
    ?>
    <article class="card bill <?= $cls ?> tilt" data-item="<?= $json ?>">
      <div class="bill-count"><span><?= $cls === 'paid' ? icon('check', 22) : ($d < 0 ? '!' : max($d, 0)) ?></span><small><?= $cls === 'paid' ? 'pago' : ($d < 0 ? 'atraso' : 'dias') ?></small></div>
      <div class="bill-main">
        <h4><?= e($b['descricao']) ?></h4>
        <p class="muted"><?php if ($showClient): ?><a href="<?= url('cliente', ['id' => $b['client_id']]) ?>"><?= e($b['cliente'] ?? '') ?></a> · <?php endif; ?><?= e(BILL_TYPES[$b['tipo']] ?? $b['tipo']) ?><?= $b['fornecedor'] ? ' · ' . e($b['fornecedor']) : '' ?></p>
        <div class="chips"><span class="chip"><?= icon('calendar', 14) ?> <?= fmt_date($b['vencimento']) ?></span>
          <span class="chip"><?= fmt_money($b['valor']) ?></span>
          <?php if ($b['recorrencia'] !== 'nenhuma'): ?><span class="chip">↻ <?= e($b['recorrencia']) ?></span><?php endif; ?>
          <span class="chip tag-<?= $cls ?>"><?= e($label) ?></span>
          <?php if ($b['avisar_email']): ?><span class="chip dim" title="Alerta por e-mail"><?= icon('mail', 13) ?></span><?php endif; ?>
          <?php if ($b['avisar_whatsapp']): ?><span class="chip dim" title="Alerta por WhatsApp"><?= icon('phone', 13) ?></span><?php endif; ?></div>
      </div>
      <?php if (can('billing.edit')): ?>
      <div class="card-actions">
        <?php if ($b['status'] === 'pendente'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bill_pay"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn ok" title="Marcar como pago"><?= icon('check') ?></button></form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="bill_reopen"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn" title="Reabrir">↺</button></form>
        <?php endif; ?>
        <button type="button" class="icon-btn" data-edit="dlg-bill" title="Editar"><?= icon('edit') ?></button>
        <?php if (can('delete')): ?><?= delete_btn('bill_delete', (int) $b['id'], $ret, 'este vencimento') ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </article>
    <?php
}

function delete_btn(string $action, int $id, string $ret, string $what): string
{
    return '<form method="post" data-confirm="Excluir ' . e($what) . '? Esta ação não pode ser desfeita.">' . csrf_field()
        . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="return" value="' . e($ret) . '"><button class="icon-btn danger" title="Excluir">' . icon('trash') . '</button></form>';
}

function render_cred_card(array $c, string $ret): void
{
    $cat = CRED_CATS[$c['categoria']] ?? ['Outro', ''];
    $item = $c; unset($item['senha_enc']); $item['tem_senha'] = $c['senha_enc'] ? 1 : 0;
    ?>
    <article class="card cred tilt" data-item="<?= e(json_encode($item)) ?>">
      <header><span class="avatar sm cat-<?= e($c['categoria']) ?>"><?= e(mb_strtoupper(mb_substr($cat[0], 0, 2))) ?></span>
        <div><h4><?= e($c['titulo']) ?></h4><p class="muted"><?= e($cat[0]) ?><?= isset($c['cliente']) ? ' · <a href="' . url('cliente', ['id' => $c['client_id']]) . '">' . e($c['cliente']) . '</a>' : '' ?></p></div></header>
      <?php if ($c['url']): ?><a class="url" href="<?= e($c['url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 14) ?> <?= e(preg_replace('~^https?://(www\.)?~', '', $c['url'])) ?></a><?php endif; ?>
      <div class="field"><label>Login</label><div class="copyrow"><code><?= e($c['login'] ?: '—') ?></code><?php if ($c['login']): ?><button type="button" class="icon-btn" data-copy="<?= e($c['login']) ?>" title="Copiar login"><?= icon('copy', 16) ?></button><?php endif; ?></div></div>
      <div class="field"><label>Senha</label><div class="copyrow"><code class="pw" data-id="<?= $c['id'] ?>">••••••••••</code>
        <?php if ($c['senha_enc']): ?><button type="button" class="icon-btn" data-reveal="<?= $c['id'] ?>" title="Mostrar/ocultar"><?= icon('eye', 16) ?></button>
        <button type="button" class="icon-btn" data-copy-pw="<?= $c['id'] ?>" title="Copiar senha"><?= icon('copy', 16) ?></button><?php endif; ?></div></div>
      <?php if ($c['notas']): ?><p class="note"><?= e($c['notas']) ?></p><?php endif; ?>
      <?php if (can('creds.edit')): ?><div class="card-actions row">
        <button type="button" class="icon-btn" data-edit="dlg-cred" title="Editar"><?= icon('edit') ?></button>
        <?php if (can('delete')): ?><?= delete_btn('cred_delete', (int) $c['id'], $ret, 'este acesso') ?><?php endif; ?></div><?php endif; ?>
    </article>
    <?php
}

function render_link_card(array $l, string $ret): void
{
    $t = LINK_TYPES[$l['tipo']] ?? 'Link';
    ?>
    <article class="card linkc tilt lt-<?= e($l['tipo']) ?>" data-item="<?= e(json_encode($l)) ?>">
      <a class="linkc-main" href="<?= e($l['url']) ?>" target="_blank" rel="noopener noreferrer">
        <span class="avatar lg"><?= icon($l['tipo'] === 'agenda' ? 'calendar' : 'folder', 24) ?></span>
        <div><h4><?= e($l['titulo']) ?></h4><p class="muted"><span class="chip"><?= e($t) ?></span>
          <?= isset($l['cliente']) && $l['cliente'] ? e($l['cliente']) : 'Geral' ?></p>
          <?php if ($l['notas']): ?><p class="note"><?= e($l['notas']) ?></p><?php endif; ?></div>
        <?= icon('external', 18) ?></a>
      <?php if (can('links.edit')): ?><div class="card-actions row">
        <button type="button" class="icon-btn" data-edit="dlg-link" title="Editar"><?= icon('edit') ?></button>
        <?php if (can('delete')): ?><?= delete_btn('link_delete', (int) $l['id'], $ret, 'este link') ?><?php endif; ?></div><?php endif; ?>
    </article>
    <?php
}

/* ---------- Diálogos (formulários) ---------- */
function dlg_cred(?int $client = null, string $ret = ''): void
{ ?>
<dialog id="dlg-cred" class="modal"><form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
  <input type="hidden" name="action" value="cred_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo acesso|Editar acesso">Novo acesso</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="field"><label>Tipo de acesso <small class="muted">(clique para preencher rápido)</small></label>
    <div class="catchips"><?php foreach (CRED_CATS as $k => $c): ?><button type="button" class="catchip" data-cat="<?= $k ?>" data-url="<?= e($c[1]) ?>" data-name="<?= e($c[0]) ?>"><?= e($c[0]) ?></button><?php endforeach; ?></div>
    <input type="hidden" name="categoria" value="outro"></div>
  <?php if ($client): ?><input type="hidden" name="client_id" value="<?= $client ?>"><?php else: ?>
  <label>Cliente<select name="client_id" required><?= client_options() ?></select></label><?php endif; ?>
  <div class="grid2"><label>Título<input name="titulo" placeholder="Ex.: Instagram oficial"></label>
    <label>Link de acesso<input name="url" type="url" placeholder="https://"></label></div>
  <div class="grid2"><label>Login / usuário<input name="login"></label>
    <label>Senha <span class="muted" data-keep hidden>(vazio = manter)</span><div class="pwwrap"><input name="senha" type="text" class="mono" autocomplete="new-password"><button type="button" class="icon-btn" data-gen title="Gerar senha forte"><?= icon('dice') ?></button></div></label></div>
  <details class="paste"><summary><?= icon('sparkles', 16) ?> Colar tudo de uma vez</summary>
    <textarea rows="3" placeholder="Cole aqui: &#10;usuario: joao@email.com&#10;senha: 123456&#10;https://site.com/login"></textarea>
    <button type="button" class="btn ghost sm" data-parse>Preencher campos</button></details>
  <label>Observações<textarea name="notas" rows="2" placeholder="Ex.: verificação em 2 etapas no celular da Ana"></textarea></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar acesso</button></footer>
</form></dialog>
<?php }

function dlg_bill(?int $client = null, string $ret = ''): void
{ ?>
<dialog id="dlg-bill" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="bill_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo vencimento|Editar vencimento">Novo vencimento</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <?php if ($client): ?><input type="hidden" name="client_id" value="<?= $client ?>"><?php else: ?>
  <label>Cliente<select name="client_id" required><?= client_options() ?></select></label><?php endif; ?>
  <div class="grid2"><label>Tipo<select name="tipo"><?php foreach (BILL_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Descrição<input name="descricao" placeholder="Ex.: meucliente.com.br"></label></div>
  <div class="grid3"><label>Vencimento<input type="date" name="vencimento" required></label>
    <label>Valor (R$)<input name="valor" inputmode="decimal" placeholder="0,00"></label>
    <label>Repete<select name="recorrencia"><option value="nenhuma">Não repete</option><option value="mensal">Todo mês</option><option value="anual">Todo ano</option></select></label></div>
  <label>Fornecedor<input name="fornecedor" placeholder="Registro.br, Hostinger, Google…"></label>
  <div class="field"><label>Avisar nos marcos de 30, 15 e 7 dias por</label>
    <div class="switches"><label class="switch"><input type="checkbox" name="avisar_email" checked><span></span> <?= icon('mail', 16) ?> E-mail</label>
    <label class="switch"><input type="checkbox" name="avisar_whatsapp" checked><span></span> <?= icon('phone', 16) ?> WhatsApp</label></div></div>
  <label>Observações<textarea name="notas" rows="2"></textarea></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar vencimento</button></footer>
</form></dialog>
<?php }

function dlg_link(?int $client = null, string $ret = ''): void
{ ?>
<dialog id="dlg-link" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="link_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo link|Editar link">Novo link</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <label>Link (Drive, Canva, Agenda…)<input name="url" type="url" required placeholder="https://drive.google.com/…" data-autotype></label>
  <div class="grid2"><label>Título<input name="titulo" placeholder="Ex.: Pasta de criativos"></label>
    <label>Tipo<select name="tipo"><?php foreach (LINK_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <?php if ($client): ?><input type="hidden" name="client_id" value="<?= $client ?>"><?php else: ?>
  <label>Cliente<select name="client_id"><?= client_options(null, true) ?></select></label><?php endif; ?>
  <label>Observações<textarea name="notas" rows="2"></textarea></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar link</button></footer>
</form></dialog>
<?php }
