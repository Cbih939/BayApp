<?php
$steps = [
  ['briefcase', 'clientes', '1. Cadastre o cliente', 'Em <b>Clientes</b> clique em “Novo cliente”. Só o nome é obrigatório. Cada cliente ganha uma página com abas de senhas, vencimentos e links.'],
  ['key', 'senhas', '2. Guarde os acessos', 'Em “Novo acesso” escolha o tipo (Instagram, site, hospedagem…) — título e link se preenchem sozinhos. Use <b>Gerar senha</b> e <b>Colar tudo de uma vez</b> para agilizar. Senhas ficam criptografadas; clique no olho para ver e na folha para copiar.'],
  ['calendar', 'vencimentos', '3. Programe vencimentos', 'Cadastre domínio, hospedagem, e-mail, SSL… com data e valor. Marque “repete” (mensal/anual) e, ao pagar, a próxima data é criada sozinha.'],
  ['bell', 'config', '4. Ative os alertas', 'Em <b>Configurações</b> informe e-mails e WhatsApp, crie o cron job diário na Hostinger e use “Enviar teste”. Avisos saem com <b>30, 15 e 7 dias</b> de antecedência.'],
  ['folder', 'links', '5. Centralize pastas e links', 'Cole links do Google Drive, Canva e Google Agenda. O tipo é identificado automaticamente e o card abre o link em uma nova aba.'],
  ['users', 'clientes', '6. Libere o portal do cliente', 'Dentro do cliente, aba <b>Portal</b>: crie um login para ele acompanhar vencimentos e acessar pastas e links em <code>portal.php</code>. Senhas e observações internas nunca aparecem.'],
  ['users', 'usuarios', '7. Monte a equipe', 'Admin cria usuários <b>Administrador</b>, <b>Designer</b> ou <b>Desenvolvedor</b>, cada um com permissões próprias.'],
];
?>
<section class="hero anim-up"><div><p class="eyebrow">Guia de uso</p><h1>Do zero ao primeiro alerta em 7 passos</h1>
  <p class="muted">Siga a ordem abaixo ou faça o tour interativo pelo menu lateral.</p></div>
  <button class="btn primary" id="startTour2"><?= icon('play', 18) ?> Iniciar tour guiado</button></section>
<section class="grid cards guide"><?php foreach ($steps as $i => [$ico, $to, $t, $d]): if (isset($NAV[$to]) && $NAV[$to][2] && !can($NAV[$to][2])) continue; ?>
  <a class="card tilt anim-up" style="--i:<?= $i ?>" href="<?= url($to) ?>"><span class="stat-ico"><?= icon($ico, 22) ?></span><h4><?= $t ?></h4><p class="muted"><?= $d ?></p><span class="link">Abrir →</span></a>
<?php endforeach; ?></section>
<section class="card anim-up"><h3>Dicas rápidas</h3><ul class="checklist">
  <li>Use o campo <b>“Filtrar cards”</b> no topo de qualquer tela para achar algo na hora.</li>
  <li>Cores dos vencimentos: <span class="chip tag-d30">16–30 dias</span> <span class="chip tag-d15">8–15 dias</span> <span class="chip tag-d7">até 7 dias</span> <span class="chip tag-over">vencido</span></li>
  <li>Toda visualização de senha e envio de alerta fica registrado no histórico.</li>
  <li>Use o botão ☀ no menu lateral para alternar entre tema escuro e claro.</li></ul></section>
