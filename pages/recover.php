<!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Recuperar senha — <?= e($empresa) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png"><link rel="apple-touch-icon" href="assets/img/favicon.png"><?= inline_css() ?>
<script>try{var t=localStorage.getItem('bay-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script></head>
<body class="auth-body"><?= css_stale_banner() ?><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
<main class="auth-card anim-up">
  <div class="brand big"><?= brand_logo($empresa) ?></div>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($mode === 'pedir'): ?>
    <h1>Esqueceu a senha?</h1>
    <p class="muted">Informe seu e-mail de acesso. Se ele estiver cadastrado, enviaremos um link para criar uma nova senha.</p>
    <form method="post" class="stack"><?= csrf_field() ?>
      <label>E-mail<input type="email" name="email" required autofocus autocomplete="username"></label>
      <button class="btn primary">Enviar link</button>
    </form>
  <?php elseif ($mode === 'enviado'): ?>
    <h1>Confira seu e-mail</h1>
    <p class="muted">Se esse e-mail estiver cadastrado, o link chega em instantes e vale por 1 hora. Olhe também a caixa de spam.</p>
  <?php elseif ($mode === 'nova'): ?>
    <h1>Criar nova senha</h1>
    <p class="muted">Ao salvar, todas as sessões abertas nesta conta serão encerradas.</p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <label>Nova senha (mín. 8)<input type="password" name="nova" minlength="8" required autofocus autocomplete="new-password"></label>
      <label>Repita a nova senha<input type="password" name="nova2" minlength="8" required autocomplete="new-password"></label>
      <button class="btn primary">Salvar nova senha</button>
    </form>
  <?php elseif ($mode === 'pronto'): ?>
    <h1>Senha alterada</h1>
    <p class="muted">Tudo certo. Agora é só entrar com a nova senha.</p>
  <?php else: ?>
    <h1>Link inválido</h1>
    <p class="muted">Este link expirou ou já foi usado. Peça um novo.</p>
    <a class="btn" href="<?= e($script) ?>?p=esqueci">Pedir novo link</a>
  <?php endif; ?>
  <p class="muted small"><a class="link" href="<?= e($script) ?>">← Voltar para o login</a></p>
</main></body></html>
