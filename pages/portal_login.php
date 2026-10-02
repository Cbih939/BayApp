<!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Portal do cliente — <?= e($empresa) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script></head>
<body class="auth-body"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<main class="auth-card anim-up">
  <div class="brand big"><span class="logo">B</span> <?= e($empresa) ?></div>
  <p class="eyebrow">Portal do cliente</p>
  <h1>Acompanhe sua conta</h1>
  <p class="muted">Veja vencimentos e acesse suas pastas e links em um só lugar.</p>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack"><?= csrf_field() ?>
    <label>E-mail<input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Senha<input type="password" name="senha" required autocomplete="current-password"></label>
    <button class="btn primary">Entrar <?= icon('logout', 18) ?></button>
  </form>
  <p class="muted small">Ainda não tem acesso? Peça à equipe da <?= e($empresa) ?>.</p>
</main></body></html>
