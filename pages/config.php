<?php
$cronToken = setting('cron_token');
if ($cronToken === '') { $cronToken = bin2hex(random_bytes(16)); set_setting('cron_token', $cronToken); }
$ret = url('config');
$wa = setting('wa_provider', 'nenhum');
$base = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$cronPath = dirname(__DIR__) . '/cron.php';
?>
<div class="sec-h big"><div><h3>Configurações</h3><p class="muted">Alertas de vencimento por e-mail e WhatsApp.</p></div></div>
<section class="card anim-up"><form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="settings_save"><input type="hidden" name="return" value="<?= e($ret) ?>">
  <h4>Geral</h4>
  <div class="form-grid">
    <label>Nome da empresa<input name="empresa_nome" value="<?= e(setting('empresa_nome', 'BayApp')) ?>"></label>
    <label>Remetente dos e-mails<input type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="alertas@seudominio.com"></label>
  </div>
  <label>E-mails que recebem os alertas (separe por vírgula)<input name="alert_emails" value="<?= e(setting('alert_emails')) ?>" placeholder="voce@empresa.com, equipe@empresa.com"></label>
  <h4>WhatsApp</h4>
  <div class="form-grid">
    <label>Provedor<select name="wa_provider">
      <option value="nenhum" <?= $wa === 'nenhum' ? 'selected' : '' ?>>Desativado</option>
      <option value="callmebot" <?= $wa === 'callmebot' ? 'selected' : '' ?>>CallMeBot</option>
      <option value="webhook" <?= $wa === 'webhook' ? 'selected' : '' ?>>Webhook (Z-API, Evolution, Make, n8n…)</option>
    </select></label>
    <label>Número (com DDI, ex.: 5511999999999)<input name="wa_phone" value="<?= e(setting('wa_phone')) ?>"></label>
    <label>API key / token<input name="wa_apikey" value="<?= e(setting('wa_apikey')) ?>" autocomplete="off"></label>
    <label>URL do webhook<input name="wa_webhook_url" value="<?= e(setting('wa_webhook_url')) ?>" placeholder="https://…"></label>
  </div>
  <div><button class="btn primary" type="submit">Salvar configurações</button></div>
</form></section>
<section class="card anim-up" style="margin-top:18px"><h4>Testar e executar</h4>
  <p class="muted" style="margin-bottom:12px">Salve antes de testar. O teste envia uma mensagem de exemplo; “Verificar agora” roda a rotina diária.</p>
  <div class="row" style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach (['alert_test' => 'Enviar teste', 'alert_run' => 'Verificar vencimentos agora'] as $act => $label): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn" type="submit"><?= e($label) ?></button></form>
    <?php endforeach; ?>
  </div></section>
<section class="card anim-up" style="margin-top:18px"><h4>Cron job diário (Hostinger)</h4>
  <p class="muted" style="margin-bottom:10px">hPanel → Avançado → Cron Jobs → 1x ao dia (ex.: 08:00) com o comando:</p>
  <div class="copyrow"><code>/usr/bin/php <?= e($cronPath) ?></code></div>
  <p class="muted" style="margin:12px 0 10px">Ou chame por URL:</p>
  <div class="copyrow"><code><?= e($base . '/cron.php?token=' . $cronToken) ?></code></div>
</section>
