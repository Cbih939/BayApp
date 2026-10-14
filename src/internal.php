<?php
declare(strict_types=1);

/* Vencimentos internos da Bay Groups (contas que a própria empresa paga). Separados dos pagamentos dos clientes. */

function record_internal_payment(array $b, ?int $uid): void
{
    insert('internal_payments', ['bill_id' => $b['id'], 'competencia' => substr($b['vencimento'], 0, 7), 'vencimento' => $b['vencimento'],
        'valor' => $b['valor'], 'pago_em' => now(), 'user_id' => $uid]);
}

function render_ibill_card(array $b, string $ret): void
{
    [$cls, $label, $d] = bill_urgency($b);
    $item = array_intersect_key($b, array_flip(['id', 'categoria', 'descricao', 'fornecedor', 'valor', 'vencimento', 'recorrencia', 'avisar_email', 'avisar_whatsapp', 'notas']));
    $canEdit = can('billing.edit');
    ?>
    <article class="card bill <?= $cls ?> tilt" data-item="<?= e(json_encode($item, JSON_UNESCAPED_UNICODE)) ?>">
      <div class="bill-count"><span><?= $cls === 'paid' ? icon('check', 22) : ($d < 0 ? '!' : max($d, 0)) ?></span><small><?= $cls === 'paid' ? 'pago' : ($d < 0 ? 'atraso' : 'dias') ?></small></div>
      <div class="bill-main">
        <h4><?= e($b['descricao']) ?></h4>
        <p class="muted"><?= e(INTERNAL_CATS[$b['categoria']] ?? $b['categoria']) ?><?= $b['fornecedor'] ? ' · ' . e($b['fornecedor']) : '' ?></p>
        <div class="chips"><span class="chip"><?= icon('calendar', 14) ?> <?= fmt_date($b['vencimento']) ?></span>
          <span class="chip"><?= fmt_money($b['valor']) ?></span>
          <?php if ($b['recorrencia'] !== 'nenhuma'): ?><span class="chip">↻ <?= e(REC_LABELS[$b['recorrencia']] ?? $b['recorrencia']) ?></span><?php endif; ?>
          <span class="chip tag-<?= $cls ?>"><?= e($label) ?></span>
          <?php if ($b['avisar_email']): ?><span class="chip dim" title="Alerta por e-mail"><?= icon('mail', 13) ?></span><?php endif; ?>
          <?php if ($b['avisar_whatsapp']): ?><span class="chip dim" title="Alerta por WhatsApp"><?= icon('phone', 13) ?></span><?php endif; ?></div>
        <?php if ($b['notas']): ?><p class="note"><?= e($b['notas']) ?></p><?php endif; ?>
      </div>
      <?php if ($canEdit): ?>
      <div class="card-actions">
        <?php if ($b['status'] === 'pendente'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ibill_pay"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn ok" title="Marcar como pago"><?= icon('check') ?></button></form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ibill_reopen"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="icon-btn" title="Reabrir">↺</button></form>
        <?php endif; ?>
        <button type="button" class="icon-btn" data-edit="dlg-ibill" title="Editar"><?= icon('edit') ?></button>
        <?php if (can('delete')) echo delete_btn('ibill_delete', (int) $b['id'], $ret, 'este vencimento interno e o histórico dele'); ?>
      </div>
      <?php endif; ?>
    </article>
    <?php
}

/** Histórico de pagamentos internos (um cartão por pagamento). */
function render_ipaid_card(array $p): void
{ ?>
    <article class="card bill paid tilt">
      <div class="bill-count"><span><?= icon('check', 22) ?></span><small>pago</small></div>
      <div class="bill-main"><h4><?= e($p['descricao']) ?></h4>
        <p class="muted"><?= e(INTERNAL_CATS[$p['categoria']] ?? $p['categoria']) ?><?= $p['fornecedor'] ? ' · ' . e($p['fornecedor']) : '' ?></p>
        <div class="chips"><span class="chip"><?= icon('calendar', 14) ?> <?= e(month_label($p['competencia'])) ?></span><span class="chip"><?= fmt_money($p['valor']) ?></span>
          <span class="chip tag-paid">Pago em <?= fmt_date(substr($p['pago_em'], 0, 10)) ?></span></div></div>
    </article>
<?php }

function dlg_ibill(string $ret): void
{ ?>
<dialog id="dlg-ibill" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="ibill_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo vencimento interno|Editar vencimento interno">Novo vencimento interno</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Categoria<select name="categoria"><?php foreach (INTERNAL_CATS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Descrição<input name="descricao" required maxlength="200" placeholder="Ex.: Hospedagem Hostinger, Canva Pro…"></label></div>
  <div class="grid3" style="grid-template-columns:1fr 1fr 1.35fr"><label>Vencimento<input type="date" name="vencimento" required></label>
    <label>Valor (R$)<input name="valor" inputmode="decimal" placeholder="0,00"></label>
    <label>Cobrança<select name="recorrencia"><?php foreach (REC_LABELS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label></div>
  <label>Fornecedor<input name="fornecedor" placeholder="Hostinger, Registro.br, Meta…"></label>
  <div class="field"><label>Avisar nos marcos de 30, 15 e 7 dias por</label>
    <div class="switches"><label class="switch"><input type="checkbox" name="avisar_email" checked><span></span> <?= icon('mail', 16) ?> E-mail</label>
    <label class="switch"><input type="checkbox" name="avisar_whatsapp" checked><span></span> <?= icon('phone', 16) ?> WhatsApp</label></div></div>
  <label>Observações<textarea name="notas" rows="2"></textarea></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar vencimento</button></footer>
</form></dialog>
<?php }
