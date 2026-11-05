<?php
$ret = url('seguranca');
$acc = row('SELECT totp_secret, totp_recovery FROM users WHERE id = ?', [$me['id']]);
$on = !empty($acc['totp_secret']);
$left = $on ? count(json_decode((string) $acc['totp_recovery'], true) ?: []) : 0;
$newSecret = !$on ? ($_SESSION['totp_new'] ?? null) : null;
$codes = $_SESSION['totp_codes'] ?? null;
unset($_SESSION['totp_codes']);
$hidden = fn(string $a) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="return" value="' . e($ret) . '">';
$locked = totp_must_enroll($me) || ($on && (setting('require_2fa') === 'all' || (setting('require_2fa') === 'admins' && $me['papel'] === 'admin')));
?>
<div class="sec-h big"><div><h3>Minha segurança</h3><p class="muted">Sua senha e a verificação em duas etapas.</p></div></div>

<?php if (totp_must_enroll($me)): ?><div class="flash err">A empresa exige a verificação em duas etapas. Ative-a abaixo para continuar usando o sistema.</div><?php endif; ?>

<?php if ($codes): ?>
<section class="card anim-up stack sec-card sec-codes"><h4><?= icon('key', 18) ?> Guarde seus códigos de recuperação</h4>
  <p class="muted">Cada código funciona uma única vez, se você perder o celular. Eles <b>não serão mostrados novamente</b>: guarde em um local seguro, fora deste computador.</p>
  <div class="rec-codes mono"><?php foreach ($codes as $c): ?><code><?= e($c) ?></code><?php endforeach; ?></div>
</section>
<?php endif; ?>

<section class="card anim-up stack sec-card">
  <div class="cfg-h"><h4><?= icon('shield', 18) ?> Verificação em duas etapas</h4>
    <span class="chip <?= $on ? 'tag-ok' : 'tag-none' ?>"><?= $on ? 'Ativa' : 'Desativada' ?></span></div>
  <?php if ($on): ?>
    <p class="muted">Além da senha, o login pede um código do seu aplicativo autenticador. Códigos de recuperação restantes: <b><?= $left ?></b>.</p>
    <form method="post" class="sec-inline"><?= $hidden('totp_codes') ?>
      <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
      <button class="btn">Gerar novos códigos de recuperação</button></form>
    <?php if (!$locked): ?>
    <form method="post" class="sec-inline"><?= $hidden('totp_disable') ?>
      <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
      <button class="btn danger-t" onclick="return confirm('Desativar a verificação em duas etapas?')">Desativar</button></form>
    <?php else: ?><p class="note">A empresa exige a verificação em duas etapas para o seu perfil, então ela não pode ser desativada.</p><?php endif; ?>
  <?php elseif ($newSecret): ?>
    <ol class="sec-steps">
      <li>Instale um aplicativo autenticador no celular (Google Authenticator, Microsoft Authenticator, Authy…).</li>
      <li>Escaneie o QR Code ou digite a chave manualmente.</li>
      <li>Digite o código de 6 dígitos que aparecer no aplicativo.</li>
    </ol>
    <div class="sec-qr"><?= qr_svg(totp_uri($newSecret, $me['email'])) ?>
      <div class="field"><label>Chave manual</label><div class="copyrow"><code id="totp-key"><?= e(trim(chunk_split($newSecret, 4, ' '))) ?></code></div></div></div>
    <form method="post" class="sec-inline"><?= $hidden('totp_confirm') ?>
      <label>Código do aplicativo<input name="codigo" required inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" class="mono" autofocus></label>
      <button class="btn primary">Confirmar e ativar</button></form>
  <?php else: ?>
    <p class="muted">Protege sua conta mesmo se alguém descobrir sua senha. Recomendado para quem acessa senhas de clientes.</p>
    <form method="post"><?= $hidden('totp_start') ?><button class="btn primary"><?= icon('shield', 16) ?> Ativar</button></form>
  <?php endif; ?>
</section>

<section class="card anim-up stack sec-card"><h4><?= icon('key', 18) ?> Alterar minha senha</h4>
  <p class="muted">Ao alterar, as outras sessões abertas nesta conta são encerradas.</p>
  <form method="post" class="form-grid"><?= $hidden('pwd_change') ?>
    <label>Senha atual<input type="password" name="atual" required autocomplete="current-password"></label>
    <label>Nova senha (mín. 8)<input type="password" name="nova" minlength="8" required autocomplete="new-password"></label>
    <div class="full"><button class="btn primary">Alterar senha</button></div></form>
</section>
