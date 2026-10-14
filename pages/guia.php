<?php
$steps = [
  ['briefcase', 'clientes', '1. Cadastre o cliente', 'Em <b>Clientes</b> clique em “Novo cliente”. Só o nome é obrigatório. Cada cliente ganha uma página com abas de contrato, calendário, senhas, serviços, tarefas e links.'],
  ['package', 'pacotes', '2. Crie os pacotes', 'Em <b>Pacotes</b> defina o que cada plano garante por mês (ex.: 12 posts, 4 stories, 1 relatório). Depois vincule o pacote ao contrato do cliente.'],
  ['file', 'clientes', '3. Registre o contrato', 'Na aba <b>Contrato &amp; pacote</b> do cliente informe início, término, valor, cobrança (ao mês, trimestre, semestre ou ano) e o link do contrato. Ali você vê também o que o cliente tem direito e quanto já foi usado no mês.'],
  ['cal', 'calendario', '4. Planeje o calendário de postagem', 'No <b>Calendário</b> (ou na aba do cliente) clique em “Adicionar” no dia, escolha canal, formato, legenda e o link da arte (Canva/Drive). Clique no post para ler o conteúdo, clonar ou marcar como publicado.'],
  ['key', 'senhas', '5. Guarde os acessos', 'Em “Novo acesso” escolha o tipo (Instagram, site, hospedagem…) — título e link se preenchem sozinhos. Use <b>Gerar senha</b> e <b>Colar tudo de uma vez</b> para agilizar. Senhas ficam criptografadas.'],
  ['wallet', 'vencimentos', '6. Cadastre os serviços', 'Em <b>Serviços</b> clique em “Novo serviço”: gestão de redes sociais, campanhas, site, domínio, hospedagem… com valor, vencimento e cobrança. Ao pagar, o histórico é guardado e o próximo vencimento é criado sozinho.'],
  ['bell', 'config', '7. Ative os alertas', 'Em <b>Configurações</b> informe e-mails e WhatsApp, crie o cron job diário na Hostinger e use “Enviar teste”. Avisos saem com <b>30, 15 e 7 dias</b> de antecedência.'],
  ['tasks', 'tarefas', '8. Organize as tarefas', 'Crie cartões com cliente, responsável, prazo, <b>link</b> e <b>checklist</b>. Clique na tarefa para ler os detalhes e marcar os itens; arraste entre as colunas para mudar o status.'],
  ['chart', 'relatorios', '9. Acompanhe os relatórios', 'Em <b>Relatórios</b> escolha <b>Financeiro</b> (pagamentos, previsão, início do contrato, último mês pago e próximo vencimento) ou <b>Tarefas</b>, ambos em gráficos de barras com PDF e Excel.'],
  ['folder', 'links', '10. Centralize pastas e links', 'Cole links do Google Drive, Canva e Google Agenda. O tipo é identificado automaticamente.'],
  ['users', 'clientes', '11. Libere o portal do cliente', 'Dentro do cliente, aba <b>Portal</b>: crie um login para ele acompanhar serviços e links em <code>portal.php</code>. Senhas e observações internas nunca aparecem.'],
  ['users', 'usuarios', '12. Monte a equipe', 'Admin cria usuários <b>Administrador</b>, <b>Designer</b> ou <b>Desenvolvedor</b>, cada um com permissões próprias.'],
];
?>
<section class="hero anim-up"><div><p class="eyebrow">Guia de uso</p><h1>Do zero ao primeiro alerta em 12 passos</h1>
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
