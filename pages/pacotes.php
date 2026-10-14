<?php
$list = rows("SELECT p.*, (SELECT COUNT(*) FROM contracts k WHERE k.plan_id = p.id AND k.status = 'ativo') AS n_cli FROM plans p ORDER BY p.ativo DESC, p.nome");
$ret = url('pacotes');
?>
<div class="sec-h big"><div><h3>Pacotes</h3><p class="muted">Defina o que cada pacote garante ao cliente por mês. O calendário de postagem mostra quanto já foi usado.</p></div>
  <button class="btn primary" data-open="dlg-plan" data-new><?= icon('plus', 18) ?> Novo pacote</button></div>
<?php if (!$list) echo empty_state('package', 'Nenhum pacote cadastrado', 'Ex.: “Social Media Essencial” — 12 posts, 4 stories e 1 relatório por mês. Depois vincule ao contrato de cada cliente.'); ?>
<section class="grid cards">
<?php foreach ($list as $i => $pl): $its = plan_items((int) $pl['id']); $item = $pl + ['itens' => $its]; ?>
  <article class="card plan tilt anim-up <?= $pl['ativo'] ? '' : 'inactive' ?>" style="--i:<?= min($i, 10) ?>" data-item="<?= e(json_encode($item, JSON_UNESCAPED_UNICODE)) ?>">
    <header><span class="avatar"><?= icon('package', 20) ?></span><div><h4><?= e($pl['nome']) ?></h4><p class="muted"><?= $pl['valor'] !== null ? e(fmt_money($pl['valor'])) : 'Sem valor definido' ?></p></div>
      <?php if (!$pl['ativo']): ?><span class="chip">inativo</span><?php endif; ?></header>
    <?php if ($pl['descricao']): ?><p class="note"><?= e($pl['descricao']) ?></p><?php endif; ?>
    <ul class="plan-items"><?php foreach ($its as $it): ?><li><b><?= (int) $it['quantidade'] ?>×</b> <?= e(plan_item_label($it)) ?> <small class="muted">/mês</small></li><?php endforeach; ?>
      <?php if (!$its): ?><li class="muted">Nenhum item definido.</li><?php endif; ?></ul>
    <div class="chips"><span class="chip dim"><?= (int) $pl['n_cli'] ?> cliente(s) com este pacote</span></div>
    <div class="card-actions row"><button type="button" class="icon-btn" data-edit="dlg-plan" title="Editar"><?= icon('edit') ?></button>
      <?php if (can('delete')) echo delete_btn('plan_delete', (int) $pl['id'], $ret, 'o pacote ' . $pl['nome']); ?></div>
  </article>
<?php endforeach; ?>
</section>
<dialog id="dlg-plan" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="plan_save"><input type="hidden" name="id" value=""><input type="hidden" name="return" value="<?= e($ret) ?>">
  <header class="modal-h"><h3 data-title="Novo pacote|Editar pacote">Novo pacote</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <div class="grid2"><label>Nome do pacote<input name="nome" required maxlength="120" placeholder="Social Media Essencial"></label>
    <label>Valor de referência (R$)<input name="valor" inputmode="decimal" placeholder="0,00"></label></div>
  <label>Descrição<input name="descricao" maxlength="300" placeholder="Resumo do que está incluso"></label>
  <div class="field"><label>O que o cliente tem direito <small class="muted">(por mês)</small></label>
    <div class="plan-rows" data-rows="itens" data-start="1"></div>
    <button type="button" class="btn ghost sm" data-addrow="itens"><?= icon('plus', 14) ?> Adicionar entrega</button>
    <template data-tpl="itens"><div class="plan-row">
      <input type="number" name="item_qtd[]" data-k="quantidade" min="1" max="999" value="1" aria-label="Quantidade">
      <select name="item_tipo[]" data-k="tipo"><?php foreach (DELIVERY_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      <input type="text" name="item_desc[]" data-k="descricao" maxlength="160" placeholder="Detalhe (opcional)">
      <button type="button" class="icon-btn danger" data-delrow title="Remover"><?= icon('x', 14) ?></button></div></template></div>
  <label class="switch"><input type="checkbox" name="ativo" checked><span></span> Pacote ativo (disponível para novos contratos)</label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Salvar pacote</button></footer>
</form></dialog>
