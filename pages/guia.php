<?php
$steps = [
  ['briefcase', 'clientes', '1. Cadastre o cliente', 'Em <b>Clientes</b> clique em “Novo cliente”. Só o nome é obrigatório. Ao abrir o cliente, tudo dele fica nas abas: pacote, tarefas, calendário, pagamentos, relatórios, mensagens, pastas e links, senhas e portal.'],
  ['package', 'pacotes', '2. Crie os pacotes', 'Defina o que cada plano garante por mês (ex.: 12 posts, 4 stories, 1 relatório). O link “Gerenciar os pacotes” fica na aba <b>Pacote</b> do cliente e no Início.'],
  ['file', 'clientes', '3. Registre o contrato', 'Na aba <b>Pacote</b> do cliente informe início, término, valor, cobrança e o link do contrato. Ali você vê o que o cliente tem direito e quanto já foi usado no mês.'],
  ['tasks', 'clientes', '4. Organize as tarefas', 'Na aba <b>Tarefas</b> crie tarefas com responsável, prazo, link e checklist. A lista mostra uma linha por tarefa: clique para abrir os detalhes. Se preferir, use a visão em quadro para arrastar entre colunas.'],
  ['cal', 'clientes', '5. Planeje o calendário', 'Na aba <b>Calendário</b> clique em “Adicionar” no dia, escolha canal, formato, legenda e arte, e acompanhe o fluxo de aprovação do conteúdo.'],
  ['card', 'clientes', '6. Registre os pagamentos', 'Na aba <b>Pagamentos</b>, “Novo serviço”: vencimento fixo (1x ao ano), variável (mês a mês) ou único. Clique no pagamento para anexar boleto e quitação; o cliente baixa no portal.'],
  ['chart', 'clientes', '7. Acompanhe os relatórios', 'Na aba <b>Relatórios</b> veja financeiro e tarefas do cliente, com PDF, Excel e envio por WhatsApp.'],
  ['chat', 'clientes', '8. Converse com o cliente', 'A aba <b>Mensagens</b> é o chat com o portal. O número ao lado de <b>Clientes</b> no menu avisa quando há mensagem nova.'],
  ['key', 'clientes', '9. Guarde links e senhas', 'As abas <b>Pastas &amp; Links</b> e <b>Senhas</b> guardam o Drive, o Canva e os acessos do cliente, com as senhas criptografadas.'],
  ['users', 'clientes', '10. Libere o portal do cliente', 'Na aba <b>Portal</b> crie um login para o cliente acompanhar pagamentos e links. Senhas e observações internas nunca aparecem.'],
  ['home', 'dashboard', '11. Veja todos os clientes de uma vez', 'No <b>Início</b>, a faixa “Todos os clientes” abre as visões gerais: todas as tarefas, calendário, pagamentos, relatórios e mensagens. Os <b>vencimentos da empresa</b> (contas da própria agência) também ficam lá.'],
  ['bell', 'config', '12. Ative os alertas', 'Em <b>Configurações</b> informe e-mails e WhatsApp, crie o cron job diário na Hostinger e use “Enviar teste”.'],
  ['users', 'usuarios', '13. Monte a equipe', 'Em <b>Equipe</b> crie usuários e escolha quais clientes cada um pode ver.'],
];
?>
<section class="hero"><div><h1>Guia de uso</h1><p class="muted">Siga a ordem abaixo ou faça o tour pelo menu.</p></div>
  <button class="btn primary" id="startTour2"><?= icon('play', 16) ?> Iniciar tour guiado</button></section>
<section class="grid cards guide"><?php foreach ($steps as [$ico, $to, $t, $d]): if (isset($NAV[$to]) && $NAV[$to][2] && !can($NAV[$to][2])) continue; ?>
  <a class="card" href="<?= url($to) ?>"><span class="stat-ico"><?= icon($ico, 20) ?></span><h4><?= $t ?></h4><p class="muted"><?= $d ?></p><span class="link">Abrir →</span></a>
<?php endforeach; ?></section>
<section class="card"><h3>Dicas rápidas</h3><ul class="checklist">
  <li>Use o campo <b>“Filtrar nesta página”</b> no topo para achar algo na hora.</li>
  <li>Cores dos vencimentos: <span class="chip tag-d30">16–30 dias</span> <span class="chip tag-d15">8–15 dias</span> <span class="chip tag-d7">até 7 dias</span> <span class="chip tag-over">vencido</span></li>
  <li>Toda visualização de senha e envio de alerta fica registrado no histórico.</li>
  <li>Use o botão de sol e lua no menu lateral para alternar entre tema escuro e claro.</li></ul></section>
