<?php $att = chat_attendant($cid); ?>
<div class="sec-h"><div><h3>Fale com a equipe</h3><p class="muted">Tire dúvidas e peça ajustes. Respondemos por aqui.</p></div>
  <span class="presence" id="presence" data-viewer="client"><i class="dot <?= $att ? 'on' : '' ?>"></i><span><?= $att ? e(explode(' ', $att)[0]) . ' está online' : 'Equipe offline' ?></span></span></div>
<?php render_chat_box($cid, 'client', 'portal.php?p=chat_api', 'Ainda não há mensagens. Escreva abaixo para falar com a equipe da ' . $empresa . '.'); ?>
