<?php
$me = user();
$title = $p === 'cliente' ? 'Cliente' : $NAV[$p][0];
$due = (int) val("SELECT COUNT(*) FROM billings WHERE status = 'pendente' AND vencimento <= ?", [date('Y-m-d', strtotime('+7 day'))]);
?><!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> — <?= e(setting('empresa_nome', 'BayApp')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png"><link rel="apple-touch-icon" href="assets/img/favicon.png">
<link rel="stylesheet" href="assets/css/app.css">
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
</head><body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= url() ?>"><?= brand_logo(setting('empresa_nome', 'BayGroups')) ?></a>
    <nav>
      <?php foreach ($NAV as $slug => [$label, $ico, $perm]): if ($perm && !can($perm)) continue; ?>
        <a href="<?= url($slug) ?>" data-tour="<?= $slug ?>" class="<?= $p === $slug || ($p === 'cliente' && $slug === 'clientes') ? 'active' : '' ?>">
          <?= icon($ico) ?><span><?= e($label) ?></span>
          <?php if ($slug === 'vencimentos' && $due): ?><em class="badge pulse"><?= $due ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <button class="btn ghost sm full" id="startTour"><?= icon('play', 16) ?> Tour guiado</button>
      <div class="me"><span class="avatar"><?= e(initials($me['nome'])) ?></span>
        <div><b><?= e($me['nome']) ?></b><small><?= e(ROLES[$me['papel']]) ?></small></div></div>
      <div class="side-btns"><button class="icon-btn" id="themeBtn" title="Tema claro/escuro"><?= icon('sun') ?></button>
        <button class="icon-btn" data-open="dlg-pwd" title="Alterar minha senha"><?= icon('shield') ?></button>
        <a class="icon-btn" href="<?= url('logout') ?>" title="Sair"><?= icon('logout') ?></a></div>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <main class="main">
    <header class="topbar"><button class="icon-btn menu-btn" id="menuBtn"><?= icon('menu') ?></button>
      <h2 class="page-title"><?= e($title) ?></h2>
      <div class="searchbox"><?= icon('search', 18) ?><input type="search" id="quickFilter" placeholder="Filtrar cards desta página…"></div></header>
    <div class="content" id="content"><?= $content ?></div>
  </main>
</div>

<div class="toasts" id="toasts">
  <?php foreach (pull_flash() as [$type, $msg]): ?><div class="toast <?= e($type) ?>"><?= e($msg) ?></div><?php endforeach; ?>
</div>

<dialog id="dlg-pwd" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="pwd_change"><input type="hidden" name="return" value="<?= e(url($p)) ?>">
  <header class="modal-h"><h3>Alterar minha senha</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
  <label>Nova senha (mín. 8)<input type="password" name="nova" minlength="8" required autocomplete="new-password"></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Alterar</button></footer>
</form></dialog>

<div class="tour" id="tour" hidden><div class="tour-spot" id="tourSpot"></div>
  <div class="tour-box" id="tourBox"><b id="tourTitle"></b><p id="tourText"></p>
    <div class="tour-nav"><button class="btn ghost sm" id="tourSkip">Pular</button><span id="tourStep" class="muted"></span><button class="btn primary sm" id="tourNext">Próximo</button></div></div></div>
<script src="assets/js/app.js"></script>
</body></html>
