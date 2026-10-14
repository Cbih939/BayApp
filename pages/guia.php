<?php
$steps = [
  ['briefcase', 'clientes', '1. Cadastre o cliente', 'Em <b>Clientes</b> clique em “Novo cliente”. Só o nome é obrigatório. Cada cliente ganha uma página com abas de contrato, calendário, senhas, pagamentos, tarefas e links.'],
  ['package', 'pacotes', '2. Crie os pacotes', 'Em <b>Pacotes</b> defina o que cada plano garante por mês (ex.: 12 posts, 4 stories, 1 relatório). Depois vincule o pacote ao contrato do cliente.'],
  ['file', 'clientes', '3. Registre o contrato', 'Na aba <b>Contrato &amp; pacote</b> do cliente informe início, término, valor, cobrança e o link do contrato. Ali você vê também o que o cliente tem direito e quanto já foi usado no mês.'],
  ['cal', 'calendario', '4. Planeje o calendário de postagem', 'No <b>Calendário</b> (ou na aba do cliente) clique em “Adicionar” no dia, escolha canal, formato, legenda e o link da arte. Acompanhe o <b>fluxo</b> do conteúdo (Rascunho → Conteúdo → Aprovação interna → Aprovação do cliente → Ajustes → Aguardando agendamento → Aprovado e agendado → Concluído) na faixa de status acima do calendário; clique num status para filtrar. Clique no post para ler, mudar o status, clonar ou editar.'],
  ['key', 'senhas', '5. Guarde os acessos', 'Em “Novo acesso” escolha o tipo (Instagram, site, hospedagem…) — título e link se preenchem sozinhos. Senhas ficam criptografadas.'],
  ['card', 'clientes', '6. Registre os pagamentos do cliente', 'Na aba <b>Pagamentos</b> do cliente, “Novo serviço”: escolha <b>vencimento fixo</b> (1x ao ano, ex.: domínio) ou <b>variável</b> (mês a mês, ex.: hospedagem com valor que muda). Veja o que está <b>em aberto</b> e o que já foi <b>pago</b>.'],
  ['clip', 'pagamentos', '7. Anexe boleto e quitação', 'No <b>Calendário de pagamentos</b> (ou na aba do cliente) clique no pagamento e envie o boleto e, depois de pago, a quitação — pode enviar vários arquivos. O cliente baixa tudo no portal; arquivos “Interno” só a equipe vê.'],
  ['wallet', 'vencimentos', '8. Controle os vencimentos da Bay Groups', 'Em <b>Vencimentos</b> ficam as contas da própria empresa (hospedagem, domínios, ferramentas, impostos…), com alertas de <b>30, 15 e 7 dias</b> por e-mail e WhatsApp.'],
  ['bell', 'config', '9. Ative os alertas', 'Em <b>Configurações</b> informe e-mails e WhatsApp, crie o cron job diário na Hostinger e use “Enviar teste”.'],
  ['tasks', 'tarefas', '10. Organize as tarefas', 'Crie cartões com cliente, responsável, prazo, <b>link</b> e <b>checklist</b>. Clique na tarefa para ler os detalhes, ver as <b>pastas e links do cliente</b> e marcar os itens; arraste entre as colunas para mudar o status.'],
  ['chart', 'relatorios', '11. Acompanhe os relatórios', 'Em <b>Relatórios</b> escolha <b>Financeiro</b> (pagamentos dos clientes, vencimentos internos, início do contrato, último mês pago e próximo vencimento) ou <b>Tarefas</b>, em gráficos de barras com PDF e Excel.'],
  ['folder', 'links', '12. Pastas, links e tarefas juntos', 'Em <b>Pastas &amp; Links</b> cada cliente mostra o Drive, o Canva e a Agenda ao lado das tarefas em aberto dele.'],
  ['users', 'clientes', '13. Libere o portal do cliente', 'Dentro do cliente, aba <b>Portal</b>: crie um login para ele acompanhar pagamentos (com boleto e quitação) e links em <code>portal.php</code>. Senhas e observações internas nunca aparecem.'],
  ['users', 'usuarios', '14. Monte a equipe', 'Admin cria usuários <b>Administrador</b>, <b>Designer</b> ou <b>Desenvolvedor</b>, cada um com permissões próprias.'],
];
?>
<section class="hero anim-up"><div><p class="eyebrow">Guia de uso</p><h1>Do zero ao primeiro alerta em 14 passos</h1>
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
