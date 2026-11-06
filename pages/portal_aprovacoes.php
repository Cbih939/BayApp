<?php
/* $p é a aba aberta do portal: aqui os conteúdos usam outros nomes de variável. */
$pend = rows("SELECT * FROM posts WHERE client_id = ? AND status = 'aprov_cli' ORDER BY dia, id", [$cid]);
$next = rows("SELECT * FROM posts WHERE client_id = ? AND status IN ('aguardando', 'agendado') AND dia >= ? ORDER BY dia, id LIMIT 30", [$cid, date('Y-m-d')]);
$ev = post_events_for(array_merge(array_column($pend, 'id'), array_column($next, 'id')));
$media = function (array $it): void {
    if ($it['imagem']) { echo '<img src="portal.php?p=postimg&amp;id=' . (int) $it['id'] . '" alt="Arte do conteúdo" loading="lazy">'; return; }
    if ($it['midia_url']) { echo '<a class="pv-ph" href="' . e($it['midia_url']) . '" target="_blank" rel="noopener noreferrer"><b>Abrir a arte</b><small class="muted">' . e(parse_url($it['midia_url'], PHP_URL_HOST) ?: '') . '</small></a>'; return; }
    echo '<div class="pv-ph none">Arte ainda não anexada</div>';
};
?>
<div class="sec-h big"><div><h3>Aprovações</h3><p class="muted">Veja cada conteúdo antes de ser publicado. Aprove ou peça ajustes: tudo fica registrado.</p></div></div>
<?php if (!$pend) echo empty_state('check', 'Nada para aprovar agora', 'Quando a equipe enviar um conteúdo para você aprovar, ele aparece aqui e você recebe um e-mail.'); ?>
<div class="appr-list">
<?php foreach ($pend as $post): $hist = $ev[(int) $post['id']] ?? []; ?>
  <article class="card appr">
    <div class="appr-media"><?php $media($post); ?></div>
    <div class="appr-main">
      <p class="eyebrow"><?= e((POST_CHANNELS[$post['canal']][0] ?? $post['canal']) . ' · ' . (POST_FORMATS[$post['formato']] ?? $post['formato'])) ?></p>
      <h4><?= e($post['titulo'] ?: 'Conteúdo') ?></h4>
      <div class="chips"><span class="chip"><?= icon('calendar', 13) ?> <?= fmt_date($post['dia']) ?><?= $post['hora'] ? ' às ' . e($post['hora']) : '' ?></span><?php if ((int) $post['versao'] > 1): ?><span class="chip tag-d30">versão <?= (int) $post['versao'] ?></span><?php endif; ?></div>
      <p class="appr-caption"><?= $post['legenda'] ? nl2br(e($post['legenda'])) : '<span class="muted">Sem legenda.</span>' ?></p>
      <?php $notes = array_filter($hist, fn($h) => in_array($h['tipo'], ['ajustes', 'aprovado'], true)); if ($notes): ?>
        <details class="appr-hist"><summary>Histórico (<?= count($notes) ?>)</summary><ul class="timeline"><?php foreach ($notes as $h): ?>
          <li><b><?= e($h['label']) ?> · v<?= (int) $h['versao'] ?></b><small class="muted"><?= e($h['autor']) ?> · <?= e(date('d/m H:i', strtotime($h['criado_em']))) ?></small><?php if ($h['detalhe']): ?><p class="note"><?= e($h['detalhe']) ?></p><?php endif; ?></li><?php endforeach; ?></ul></details>
      <?php endif; ?>
      <form method="post" class="appr-form"><?= csrf_field() ?><input type="hidden" name="action" value="decidir"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><input type="hidden" name="versao" value="<?= (int) $post['versao'] ?>">
        <textarea name="comentario" rows="2" maxlength="2000" placeholder="Comentário (obrigatório para pedir ajustes)"></textarea>
        <div class="appr-btns"><button class="btn" name="decisao" value="ajustes"><?= icon('edit', 15) ?> Pedir ajustes</button>
          <button class="btn primary" name="decisao" value="aprovado"><?= icon('check', 15) ?> Aprovar</button></div></form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if ($next): ?>
<div class="sec-h"><h3>Próximas publicações aprovadas</h3></div>
<div class="card rep-table"><table><thead><tr><th>Data</th><th>Conteúdo</th><th>Canal</th><th>Situação</th></tr></thead><tbody>
  <?php foreach ($next as $n): ?><tr><td><?= fmt_date($n['dia']) ?></td><td><?= e($n['titulo'] ?: (POST_FORMATS[$n['formato']] ?? '')) ?></td><td><?= e(POST_CHANNELS[$n['canal']][0] ?? $n['canal']) ?></td><td><?= e(POST_STATUS[$n['status']] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
