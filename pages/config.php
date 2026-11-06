<?php
$cronToken = setting('cron_token');
if ($cronToken === '') { $cronToken = bin2hex(random_bytes(16)); set_setting('cron_token', $cronToken); }
$ret = url('config');
$wa = setting('wa_provider', 'nenhum');
$sec = setting('smtp_secure', 'ssl');
$base = request_base_url();
if ($base !== '' && setting('app_url') !== $base) set_setting('app_url', $base);   // o cron usa para montar o link do portal
$cronPath = dirname(__DIR__) . '/cron.php';
$cliOn = setting('cli_notify') === '1';
$cliPrev = $cliOn ? run_client_alerts(true)['clientes'] : 0;
$log = rows('SELECT * FROM notify_log ORDER BY id DESC LIMIT 60');
$chk = fn(string $k, string $d = '0') => setting($k, $d) === '1' ? 'checked' : '';
$rule2fa = setting('require_2fa');
$no2fa = array_column(rows('SELECT nome FROM users WHERE ativo = 1 AND totp_secret IS NULL ORDER BY nome'), 'nome');
$backups = backup_list();
$bkLast = setting('backup_ultimo');
$bkOld = $bkLast === '' || time() - strtotime($bkLast) > 3 * 86400;
$secretField = function (string $k): string {
    $saved = setting($k) !== '';
    return '<input type="password" name="' . $k . '" autocomplete="new-password" placeholder="' . ($saved ? '•••••••• salvo; vazio mantém' : '') . '">'
        . ($saved ? '<span class="sec-clear"><input type="checkbox" name="' . $k . '_clear"> remover o valor salvo</span>' : '');
};
?>
<div class="sec-h big"><div><h3>Configurações</h3><p class="muted">Avisos por e-mail e WhatsApp para a equipe e para os clientes.</p></div></div>
<form method="post" class="stack"><?= csrf_field() ?>
  <input type="hidden" name="action" value="settings_save"><input type="hidden" name="return" value="<?= e($ret) ?>">
<section class="card anim-up stack">
  <h4>Geral</h4>
  <div class="form-grid">
    <label>Nome da empresa<input name="empresa_nome" value="<?= e(setting('empresa_nome', 'BayApp')) ?>"></label>
    <label>E-mails da equipe que recebem os alertas (separe por vírgula)<input name="alert_emails" value="<?= e(setting('alert_emails')) ?>" placeholder="voce@empresa.com, equipe@empresa.com"></label>
  </div>
</section>

<section class="card anim-up stack">
  <div class="cfg-h"><h4><?= icon('shield', 18) ?> Segurança</h4>
    <span class="chip <?= request_is_https() ? 'tag-ok' : 'tag-over' ?>"><?= request_is_https() ? 'Conexão HTTPS' : 'Sem HTTPS' ?></span></div>
  <div class="form-grid">
    <label>Exigir verificação em duas etapas<select name="require_2fa">
      <?php foreach (['' => 'Opcional', 'admins' => 'Obrigatória para administradores', 'all' => 'Obrigatória para toda a equipe'] as $k => $v): ?><option value="<?= $k ?>" <?= $rule2fa === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></label>
    <div class="field"><label>Ainda sem verificação em duas etapas</label><p class="note"><?= $no2fa ? e(implode(', ', $no2fa)) : 'Toda a equipe ativa já usa.' ?></p></div>
  </div>
  <label class="switch"><input type="checkbox" name="aprovacao_cliente" <?= setting('aprovacao_cliente', '1') === '1' ? 'checked' : '' ?>><span></span> Exigir a aprovação do cliente antes de agendar ou publicar um conteúdo</label>
  <p class="note">Ative a sua em <a class="link" href="<?= url('seguranca') ?>">Minha segurança</a> antes de tornar obrigatória. Quem não tiver ativado será levado para essa tela no próximo acesso.</p>
</section>

<section class="card anim-up stack">
  <div class="cfg-h"><h4><?= icon('mail', 18) ?> E-mail (SMTP)</h4>
    <span class="chip <?= smtp_configured() ? 'tag-ok' : 'tag-d15' ?>"><?= smtp_configured() ? 'SMTP ativo' : 'Usando mail() do PHP' ?></span></div>
  <p class="muted">Na Hostinger: hPanel → E-mails → crie uma caixa (ex.: alertas@seudominio.com) e use <code>smtp.hostinger.com</code>, porta <code>465</code>, SSL. Sem SMTP o envio usa o <code>mail()</code> do PHP, que costuma cair no spam.</p>
  <div class="form-grid">
    <label>Servidor SMTP<input name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.hostinger.com"></label>
    <div class="grid2"><label>Porta<input name="smtp_port" inputmode="numeric" value="<?= e(setting('smtp_port', '465')) ?>"></label>
      <label>Segurança<select name="smtp_secure">
        <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Nenhuma'] as $k => $v): ?><option value="<?= $k ?>" <?= $sec === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select></label></div>
    <label>Usuário (e-mail completo)<input name="smtp_user" value="<?= e(setting('smtp_user')) ?>" autocomplete="off" placeholder="alertas@seudominio.com"></label>
    <label>Senha <?php if (setting('smtp_pass') !== ''): ?><small class="muted">— salva; deixe vazio para manter</small><?php endif; ?>
      <input type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= setting('smtp_pass') !== '' ? '••••••••' : '' ?>"></label>
    <label>Remetente <small class="muted">(vazio = o usuário do SMTP)</small><input type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="alertas@seudominio.com"></label>
  </div>
</section>

<section class="card anim-up stack">
  <div class="cfg-h"><h4><?= icon('phone', 18) ?> WhatsApp</h4>
    <span class="chip <?= whatsapp_ready() ? 'tag-ok' : 'tag-none' ?>"><?= whatsapp_ready() ? 'Configurado' : 'Não configurado' ?></span></div>
  <div class="form-grid">
    <label>Provedor<select name="wa_provider" id="wa-provider">
      <?php foreach (WA_PROVIDERS as $k => $v): ?><option value="<?= $k ?>" <?= $wa === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></label>
    <label data-wa="zapi evolution meta webhook callmebot">Números da equipe (com DDD, separe por vírgula)<input name="wa_phone" value="<?= e(setting('wa_phone')) ?>" placeholder="5511999999999, 5511988888888"></label>
    <label data-wa="evolution webhook"><span data-wa="evolution">URL do servidor Evolution</span><span data-wa="webhook">URL do webhook</span>
      <input name="wa_webhook_url" value="<?= e(setting('wa_webhook_url')) ?>" placeholder="https://…"></label>
    <label data-wa="zapi evolution meta"><span data-wa="zapi">ID da instância</span><span data-wa="evolution">Nome da instância</span><span data-wa="meta">ID do número (Phone number ID)</span>
      <input name="wa_instance" value="<?= e(setting('wa_instance')) ?>" autocomplete="off"></label>
    <label data-wa="zapi evolution meta webhook callmebot"><span data-wa="zapi">Token da instância</span><span data-wa="evolution">API key</span><span data-wa="meta">Token de acesso permanente</span><span data-wa="webhook">Token (opcional, enviado como Bearer)</span><span data-wa="callmebot">API key do CallMeBot</span>
      <?= $secretField('wa_apikey') ?></label>
    <label data-wa="zapi">Client-Token <small class="muted">(Segurança da conta na Z-API, se ativado)</small><?= $secretField('wa_client_token') ?></label>
    <label data-wa="meta">Modelo de mensagem aprovado <small class="muted">(com uma variável {{1}})</small><input name="wa_meta_template" value="<?= e(setting('wa_meta_template')) ?>" placeholder="aviso_bayapp"></label>
    <label data-wa="meta">Idioma do modelo<input name="wa_meta_lang" value="<?= e(setting('wa_meta_lang', 'pt_BR')) ?>"></label>
  </div>
  <p class="note" data-wa="meta">A Meta só entrega mensagens iniciadas pela empresa usando um modelo aprovado. Crie no Gerenciador do WhatsApp um modelo de categoria “Utilidade” com o corpo <code>{{1}}</code>; o texto do aviso vai nessa variável.</p>
  <p class="note" data-wa="callmebot">O CallMeBot só entrega no número de quem gerou a chave: serve para a equipe, não para lembrar clientes.</p>
  <p class="note" data-wa="webhook">O webhook recebe um POST JSON com <code>phone</code>, <code>number</code>, <code>message</code> e <code>text</code>.</p>
</section>

<section class="card anim-up stack">
  <div class="cfg-h"><h4><?= icon('bell', 18) ?> Lembretes para os clientes</h4>
    <span class="chip <?= $cliOn ? 'tag-ok' : 'tag-none' ?>"><?= $cliOn ? 'Ligado' : 'Desligado' ?></span></div>
  <p class="muted">Avisa o próprio cliente sobre os pagamentos, pelo e-mail e WhatsApp do cadastro dele, com link para o portal. Cada serviço pode ser excluído em “Lembrar o cliente”.</p>
  <div class="switches">
    <label class="switch"><input type="checkbox" name="cli_notify" <?= $chk('cli_notify') ?>><span></span> Enviar lembretes aos clientes</label>
    <label class="switch"><input type="checkbox" name="cli_email" <?= $chk('cli_email', '1') ?>><span></span> <?= icon('mail', 16) ?> E-mail</label>
    <label class="switch"><input type="checkbox" name="cli_whatsapp" <?= $chk('cli_whatsapp', '1') ?>><span></span> <?= icon('phone', 16) ?> WhatsApp</label>
  </div>
  <label>Quando avisar (dias em relação ao vencimento; negativo = depois de vencer)<input name="cli_marcos" value="<?= e(implode(', ', client_marcos())) ?>" placeholder="5, 0, -3"></label>
  <p class="note">Ex.: <code>5, 0, -3</code> = 5 dias antes, no dia e 3 dias depois se continuar em aberto. Atrasos com mais de uma semana além do último marco não são avisados.<?php if ($cliOn): ?> Hoje <?= $cliPrev ?> cliente(s) receberiam lembrete.<?php endif; ?></p>
</section>
<div><button class="btn primary" type="submit">Salvar configurações</button></div>
</form>

<section class="card anim-up" style="margin-top:18px">
  <div class="cfg-h"><h4><?= icon('folder', 18) ?> Backups</h4>
    <span class="chip <?= setting('backup_erro') !== '' || $bkOld ? 'tag-over' : 'tag-ok' ?>"><?= $bkLast !== '' ? 'Último: ' . e(date('d/m/Y H:i', strtotime($bkLast))) : 'Nenhum backup ainda' ?></span></div>
  <p class="muted" style="margin:6px 0 12px">O cron diário salva o banco todo dia e, aos domingos, também os arquivos enviados. Guardamos <?= BACKUP_KEEP_DB ?> backups de banco e <?= BACKUP_KEEP_FULL ?> completos. <b>Eles ficam neste mesmo servidor</b>: baixe uma cópia regularmente e guarde o <code>config.php</code> separado, porque sem a <code>app_key</code> dele as senhas salvas não podem ser lidas.</p>
  <?php if (setting('backup_erro') !== ''): ?><div class="flash err">Último erro: <?= e(setting('backup_erro')) ?></div><?php endif; ?>
  <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="backup_run"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn" type="submit">Gerar backup completo agora</button></form>
  <?php if ($backups): ?><div class="rep-table cfg-log"><table><thead><tr><th>Quando</th><th>Tipo</th><th>Tamanho</th><th></th></tr></thead><tbody>
    <?php foreach ($backups as $b): ?><tr>
      <td class="mono"><?= e(date('d/m/Y H:i', $b['quando'])) ?></td><td><?= $b['completo'] ? 'Completo' : 'Só banco' ?></td><td><?= e(fmt_size($b['tamanho'])) ?></td>
      <td class="bk-act"><a class="btn sm" href="<?= e(url('backup_download', ['f' => $b['nome']])) ?>">Baixar</a>
        <form method="post" onsubmit="return confirm('Excluir este backup?')"><?= csrf_field() ?><input type="hidden" name="action" value="backup_delete"><input type="hidden" name="f" value="<?= e($b['nome']) ?>"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn ghost sm" type="submit">Excluir</button></form></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="card anim-up" style="margin-top:18px"><h4>Testar e executar</h4>
  <p class="muted" style="margin:6px 0 12px">Salve antes de testar. Sem destino, o teste vai para a equipe; com um e-mail ou número, vai só para ele. “Verificar agora” roda a rotina diária (equipe e clientes).</p>
  <div class="row-btns">
    <form method="post" class="cfg-test"><?= csrf_field() ?><input type="hidden" name="action" value="alert_test"><input type="hidden" name="return" value="<?= e($ret) ?>">
      <input name="test_to" placeholder="E-mail ou WhatsApp (opcional)"><button class="btn" type="submit">Enviar teste</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="alert_run"><input type="hidden" name="return" value="<?= e($ret) ?>"><button class="btn" type="submit">Verificar vencimentos agora</button></form>
  </div></section>

<section class="card anim-up" style="margin-top:18px"><h4>Histórico de envios</h4>
  <?php if (!$log): ?><p class="muted" style="margin-top:8px">Nenhum envio registrado ainda.</p><?php else: ?>
  <div class="rep-table cfg-log"><table><thead><tr><th>Quando</th><th>Canal</th><th>Para</th><th>Assunto</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($log as $l): ?><tr>
      <td class="mono"><?= e(date('d/m H:i', strtotime($l['criado_em']))) ?></td>
      <td><?= icon($l['canal'] === 'email' ? 'mail' : 'phone', 15) ?> <?= $l['canal'] === 'email' ? 'E-mail' : 'WhatsApp' ?></td>
      <td class="cfg-dest"><?= e($l['destino']) ?></td><td><?= e($l['assunto']) ?></td>
      <td><span class="chip <?= $l['status'] === 'ok' ? 'tag-ok' : 'tag-over' ?>" title="<?= e($l['detalhe']) ?>"><?= $l['status'] === 'ok' ? 'Enviado' : 'Falhou' ?></span>
        <?php if ($l['status'] !== 'ok'): ?><small class="muted cfg-err"><?= e($l['detalhe']) ?></small><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="card anim-up" style="margin-top:18px"><h4>Cron job diário (Hostinger)</h4>
  <p class="muted" style="margin-bottom:10px">hPanel → Avançado → Cron Jobs → 1x ao dia (ex.: 08:00) com o comando:</p>
  <div class="copyrow"><code>/usr/bin/php <?= e($cronPath) ?></code></div>
  <p class="muted" style="margin:12px 0 10px">Ou chame por URL:</p>
  <div class="copyrow"><code><?= e($base . '/cron.php?token=' . $cronToken) ?></code></div>
</section>
<script>
(() => {
  const sel = document.getElementById('wa-provider');
  const sync = () => document.querySelectorAll('[data-wa]').forEach(el => { el.hidden = !el.dataset.wa.split(' ').includes(sel.value); });
  sel.addEventListener('change', sync); sync();
})();
</script>
