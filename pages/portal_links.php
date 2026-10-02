<div class="sec-h big"><div><h3>Pastas &amp; Links</h3><p class="muted">Drive, Canva e Google Agenda do seu projeto.</p></div></div>
<?php if (!$links) echo empty_state('folder', 'Nenhum link ainda', 'Sua equipe vai disponibilizar aqui as pastas e agendas.'); ?>
<section class="grid cards"><?php foreach ($links as $i => $l): ?><div class="anim-up" style="--i:<?= min($i, 10) ?>"><?php render_link_card($l, $ret); ?></div><?php endforeach; ?></section>
