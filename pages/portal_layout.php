<!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="robots" content="noindex,nofollow">
<title><?= e($tabs[$p][0]) ?> — Portal <?= e($empresa) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script></head>
<body class="portal">
<header class="ptop">
  <a class="brand" href="portal.php"><span class="logo">B</span> <span><?= e($empresa) ?></span></a>
  <nav class="pnav"><?php foreach ($tabs as $slug => [$label, $ico]): ?>
    <a href="portal.php?p=<?= $slug ?>" class="<?= $p === $slug ? 'active' : '' ?>"><?= icon($ico, 18) ?><span><?= e($label) ?></span></a><?php endforeach; ?></nav>
  <div class="pme"><span class="avatar sm"><?= e(initials($me['nome'])) ?></span><b class="pname"><?= e($me['nome']) ?></b>
    <button class="icon-btn" id="themeBtn" title="Tema"><?= icon('sun') ?></button>
    <button class="icon-btn" data-open="dlg-pwd" title="Alterar senha"><?= icon('shield') ?></button>
    <a class="icon-btn" href="portal.php?p=sair" title="Sair"><?= icon('logout') ?></a></div>
</header>
<main class="content pcontent"><?= $content ?></main>
<div class="toasts" id="toasts"><?php foreach (pull_flash() as [$type, $msg]): ?><div class="toast <?= e($type) ?>"><?= e($msg) ?></div><?php endforeach; ?></div>
<dialog id="dlg-pwd" class="modal"><form method="post" class="stack"><?= csrf_field() ?>
  <header class="modal-h"><h3>Alterar minha senha</h3><button type="button" class="icon-btn" data-close><?= icon('x') ?></button></header>
  <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
  <label>Nova senha (mín. 8)<input type="password" name="nova" minlength="8" required autocomplete="new-password"></label>
  <footer class="modal-f"><button type="button" class="btn ghost" data-close>Cancelar</button><button class="btn primary">Alterar</button></footer>
</form></dialog>
<script src="assets/js/app.js"></script>
</body></html>
