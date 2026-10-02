<!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar — <?= e(setting('empresa_nome', 'BayApp')) ?></title>
<link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<main class="auth-card anim-up">
  <div class="brand big"><span class="logo">B</span> <?= e(setting('empresa_nome', 'BayApp')) ?></div>
  <h1>Bem-vindo de volta</h1>
  <p class="muted">Gestão de clientes, acessos e vencimentos em um só lugar.</p>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack"><?= csrf_field() ?>
    <label>E-mail<input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Senha<input type="password" name="senha" required autocomplete="current-password"></label>
    <button class="btn primary">Entrar <?= icon('logout', 18) ?></button>
  </form>
</main></body></html>
