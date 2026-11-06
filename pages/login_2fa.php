<!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Verificação em duas etapas — <?= e(setting('empresa_nome', 'BayApp')) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png"><link rel="apple-touch-icon" href="assets/img/favicon.png"><?= inline_css() ?>
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script></head>
<body class="auth-body"><?= css_stale_banner() ?><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<main class="auth-card anim-up">
  <div class="brand big"><?= brand_logo(setting('empresa_nome', 'BayGroups')) ?></div>
  <h1>Verificação em duas etapas</h1>
  <p class="muted">Digite o código de 6 dígitos do seu aplicativo autenticador. Sem o celular? Use um código de recuperação.</p>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack"><?= csrf_field() ?>
    <label>Código<input name="codigo" required autofocus autocomplete="one-time-code" inputmode="text" maxlength="11" class="mono"></label>
    <button class="btn primary">Confirmar</button>
  </form>
  <p class="muted small"><a class="link" href="<?= url('logout') ?>">← Voltar para o login</a></p>
</main></body></html>
