# BayApp — Gestão de clientes

Aplicativo em **PHP 8 + MySQL** (preto, branco e roxo) para agências: cadastro de equipe e clientes, cofre de senhas criptografado, vencimentos com alertas (30/15/7 dias) por e-mail e WhatsApp, e central de links do Drive, Canva e Google Agenda. Feito para rodar na **hospedagem compartilhada da Hostinger**, sem Node nem Composer.

## Navegação
O menu lateral tem só **Início, Clientes, Senhas, Equipe, Guia e Configurações**. Tudo o que é de um cliente fica nas abas da página dele (Pacote, Tarefas, Calendário, Pagamentos, Relatórios, Mensagens, Pastas & Links, Senhas e Portal). As visões de todos os clientes, o catálogo de pacotes e os vencimentos da própria empresa abrem pela faixa "Todos os clientes" no Início.

## Fluxo integrado (contrato → entregas → aprovação → resultado)
- **Entregas do pacote:** ao cadastrar um contrato em vigor com pacote, as entregas do mês (posts, carrosséis, reels, stories, vídeos) entram no calendário em rascunho, distribuídas nos dias úteis. O cron abre o mês seguinte sozinho no dia 1, uma única vez por contrato; contratos encerrados não geram ciclos. Na aba **Pacote** há botões para gerar o mês atual e o próximo, e o app avisa quando o mês passa do que o pacote prevê (demanda extra para orçar).
- **Aprovação do cliente:** mudar o post para "Aprovação do cliente" avisa o cliente por e-mail. No portal, a aba **Aprovações** mostra a arte e a legenda para aprovar ou pedir ajustes (com comentário). Sem aprovação da versão atual, o post não vai para "Aguardando agendamento", "Agendado" nem "Concluído". Se a aprovação veio por outro canal, a equipe registra no modal do post explicando como.
- **Versões:** mudar título, legenda ou arte cria uma nova versão; se o post já estava aprovado, ele volta para a aprovação do cliente. Mudar só data, horário ou canal não pede nova aprovação. A regra pode ser desligada em *Configurações → Segurança*.
- **Histórico:** cada post guarda quem gerou, editou, enviou, aprovou ou pediu ajustes, e em qual versão (aba Histórico do modal).
- **Resultado e lucratividade:** em *Relatórios → Financeiro* (para quem gerencia o financeiro): receitas recebidas, despesas pagas, resultado, margem, gráfico de receitas x despesas por mês, fluxo de caixa e lucratividade por cliente. As contas da empresa podem ser atribuídas a um cliente ("Custo de qual cliente?") para entrar no custo dele.

## Roteiro (próximas fases)
Este app continua em PHP + MySQL, que rodam na hospedagem compartilhada da Hostinger. Next.js, NestJS e PostgreSQL exigiriam um servidor próprio (VPS) e a reescrita do sistema inteiro, então ficam para quando a venda como SaaS for decidida. Em ordem de prioridade, ainda faltam:
1. **Serviços e simulador de pacotes:** preço, custo interno, prazo e revisões por serviço; valor do pacote com desconto e margem estimada.
2. **CRM comercial:** leads, funil, propostas e conversão da proposta aprovada em cliente e contrato.
3. **Produção:** projetos com etapas, horas trabalhadas, tarefas recorrentes, contagem de revisões e mais de uma função por usuário (ex.: administrador e comercial).
4. **Cadastro completo do cliente:** razão social, endereço, segmento e status de prospect, suspenso ou encerrado.
5. **Portal de solicitações:** briefing enviado pelo cliente e acompanhamento do atendimento.
6. **Integrações:** cobrança por Pix e boleto, Google Drive e Agenda, publicação e métricas pelas APIs oficiais das redes.
7. **PWA, inteligência artificial e estrutura multiagência (SaaS).**

## Funcionalidades
| Módulo | O que faz |
|---|---|
| **Equipe** | Usuários *Administrador*, *Designer* e *Desenvolvedor* (criar, editar, desativar, excluir) com permissões por perfil |
| **Clientes** | Cadastro em cards; página do cliente com abas Contrato & pacote · Calendário · Senhas · Serviços · Tarefas · Links · Portal |
| **Pacotes e contrato** | Pacotes com o que o cliente tem direito por mês (posts, stories, relatórios…); contrato em vigor com início, término, valor, cobrança e link do documento; uso do pacote no mês contado pelo calendário |
| **Calendário de postagem** | Grade mensal por cliente ou geral, com canal, formato, status, legenda e arte (link do Canva/Drive **ou upload de imagem PNG/JPG/WEBP**, com preview no modal); faixa com o **fluxo de status** do conteúdo (Rascunho → Conteúdo → Aprovação interna → Aprovação do cliente → Ajustes → Aguardando agendamento → Aprovado e agendado → Concluído) com contagem e filtro; modal de preview com informações, histórico, clonar e mudança de status; no celular, pontos coloridos por dia; botão **Baixar PDF** gera o mês (A4 deitado, estilo mLabs) só com as postagens prontas — aguardando agendamento, aprovado e agendado ou concluído — com miniaturas das artes e um detalhamento com legenda |
| **Senhas** | Por cliente e tipo (Instagram, site, hospedagem, domínio, e-mail…). Cadastro rápido: chips de tipo, gerador de senha forte, “colar tudo de uma vez”. Criptografia AES-256-GCM; ver/copiar fica registrado |
| **Pagamentos dos clientes** | Aba *Pagamentos* em cada cliente: vencimento **fixo** (1x ao ano) ou **variável** (mês a mês, valor muda) ou único; lista **em aberto** e **pagos**; calendário de pagamentos (global, por cliente e no portal); **boleto e quitação** anexados em vários arquivos (PDF, imagem, Word, Excel), com arquivos *internos* que o cliente não vê |
| **Vencimentos internos** | Contas da própria Bay Groups (hospedagem, domínios, ferramentas, impostos…) com recorrência, histórico de pagos e alertas |
| **Tarefas** | Quadro Kanban com link próprio, detalhes e checklist com check; clique no cartão para ler e marcar os itens |
| **Relatórios** | Financeiro (pago/previsto por mês, contrato desde, último mês pago, próximo vencimento) e Tarefas, separados, em gráficos de barras; PDF, Excel (CSV) e WhatsApp |
| **Alertas** | Cron diário envia avisos à equipe a **30, 15 e 7 dias** (uma vez por marco) por **e-mail** (SMTP) e **WhatsApp**, e lembretes de pagamento aos **clientes** (ex.: 5 dias antes, no dia e 3 dias depois). Histórico de envios em *Configurações* |
| **Pastas & Links** | Drive, Canva, Agenda, Docs, Figma — tipo detectado automaticamente; cada cliente aparece com as tarefas em aberto, e o modal da tarefa lista as pastas e links dele |
| **Portal do cliente** | `portal.php`: login próprio para clientes (vários usuários por cliente), somente leitura — vê vencimentos e pastas/links; **não** vê senhas nem observações internas. Criado na aba *Portal* de cada cliente |
| **Mensagens** | Chat entre cada cliente (no portal) e a equipe (no painel, menu *Mensagens*): uma conversa por cliente, indicador de não lidas no menu e na aba do navegador, indicador online/offline (a equipe vê o cliente; o cliente só vê o atendente que já respondeu na conversa e enquanto ela estiver aberta), atualização automática por polling (5 s com a aba aberta). Tabela `messages`, criada sozinha no primeiro acesso |
| **Tarefas** | Quadro Kanban (A fazer · Em andamento · Em revisão · Concluída) com arrastar-e-soltar, visão em lista, filtros por responsável/cliente/prioridade, prazo com destaque de atraso, aba *Tarefas* em cada cliente e “Minhas tarefas” no painel. O responsável recebe e-mail ao ser atribuído e um resumo diário (via `cron.php`) das tarefas atrasadas ou que vencem até amanhã. Tabela `tasks`, criada sozinha no primeiro acesso |
| **Relatórios** | Menu *Relatórios*: filtro por período e cliente, indicadores, gráficos (valor por tipo e por mês, clientes, tarefas por status/responsável) e tabelas de vencimentos e tarefas em atraso. **Baixar PDF**, **baixar Excel (CSV)** e **enviar resumo ao WhatsApp** (pela integração configurada, ou link `wa.me` para escolher o contato; com cliente filtrado abre a conversa dele). Dados financeiros só aparecem para quem tem acesso a *Vencimentos* |
| **Guia** | Página de passo a passo + tour interativo no primeiro acesso |

## Instalação na Hostinger (hPanel)
1. **Banco:** *Bancos de dados → MySQL* → crie banco + usuário e anote os dados.
2. **PHP:** *Avançado → Configuração do PHP* → use **PHP 8.1+** (extensões `pdo_mysql`, `curl`, `openssl`, `mbstring` já vêm ativas).
3. **Arquivos:** envie todo o conteúdo desta pasta para `public_html` (Gerenciador de Arquivos ou FTP).
4. **Instalador:** abra `https://seudominio.com/install.php`, informe os dados do MySQL e crie o administrador.
5. **Apague `install.php`** do servidor.
6. **SSL:** ative o SSL grátis. Depois de instalado, o app redireciona sozinho para HTTPS (para desligar, se o certificado der problema: `'force_https' => false` no `config.php`).
7. **Alertas:** em *Configurações* preencha o SMTP (Hostinger: `smtp.hostinger.com`, porta 465, SSL, com uma caixa de e-mail criada no hPanel), o WhatsApp e, se quiser, ligue os lembretes aos clientes; depois clique em **Enviar teste**.
8. **Cron:** *Avançado → Cron Jobs* → diário (ex.: 08:00) com o comando exibido em *Configurações*, algo como  
   `/usr/bin/php /home/uXXXX/domains/seudominio.com/public_html/cron.php`

> ⚠️ Faça backup do `config.php`: a `app_key` dentro dele é a chave que descriptografa as senhas. Sem ela, as senhas salvas não podem ser recuperadas.

## Segurança
- **Acesso por cliente:** em *Equipe*, edite o usuário e escolha "Todos os clientes" ou os clientes liberados. Quem foi criado antes desta versão continua vendo todos até você restringir; usuários novos começam sem nenhum. Sem acesso ao cliente, a pessoa só vê tarefas gerais e as atribuídas a ela.
- **Verificação em duas etapas:** cada pessoa ativa em *Minha segurança* (ícone de escudo no menu). Em *Configurações → Segurança* dá para torná-la obrigatória. Celular perdido: use um código de recuperação ou peça a um administrador. Se o único administrador perder o celular e os códigos, limpe `totp_secret` e `totp_recovery` do usuário no phpMyAdmin.
- **Esqueci a senha:** o link vai por e-mail e vale por 1 hora. Exige SMTP funcionando e que um administrador já tenha aberto *Configurações* ou entrado no app (é assim que o endereço do app fica salvo).
- **Sessão:** encerra após 4 horas sem uso; trocar a senha derruba as outras sessões.
- **Backups:** o cron diário salva o banco em `storage/backups` todo dia e, aos domingos, também os arquivos enviados. Baixe cópias em *Configurações → Backups*: elas ficam no mesmo servidor do app. Para restaurar: importe `database.sql` no phpMyAdmin (ele recria as tabelas), copie `uploads/` para `storage/uploads` e use o `config.php` original, pois a `app_key` não vai no backup.

## Portal do cliente
Em *Clientes → (cliente) → aba Portal*, crie o login e envie ao cliente o link `https://seudominio.com/portal.php` com e-mail e senha. Instalações antigas criam a tabela `client_users` automaticamente no primeiro acesso após a atualização.

## WhatsApp
Os números da equipe vão separados por vírgula (DDD + número; o 55 é colocado sozinho). Os lembretes aos clientes usam o WhatsApp (ou telefone) do cadastro do cliente.
- **Z-API:** ID da instância + token da instância (+ Client-Token, se a segurança da conta estiver ativa).
- **Evolution API:** URL do servidor + nome da instância + API key (endpoint `/message/sendText/{instância}`).
- **WhatsApp Cloud API (Meta):** ID do número + token permanente. Mensagens iniciadas pela empresa exigem um **modelo aprovado** (categoria Utilidade) com corpo `{{1}}`; o texto do aviso vai nessa variável.
- **Webhook genérico:** Make, n8n… recebem `POST` JSON `{"phone","number","message","text"}` (token opcional como `Authorization: Bearer`).
- **CallMeBot (grátis, só equipe):** envie “I allow callmebot to send me messages” ao número deles para obter a *apikey*. Só entrega no seu próprio número, então não serve para lembrar clientes.

## Arquivos enviados (boleto e quitação)
Ficam em `storage/uploads` (bloqueada ao acesso direto) com nome aleatório e só saem por download autenticado — equipe pelo painel, cliente apenas os do próprio cadastro e que não sejam *internos*. Aceita PDF, PNG/JPG/WEBP, Word e Excel, até 15 MB cada e 10 por envio (o limite do servidor, `post_max_size`, também vale). Faça backup dessa pasta junto com o banco.

## Visual desatualizado depois de atualizar?
O CSS e o JavaScript vão **embutidos dentro de cada página** (não são arquivos separados), porque hospedagens/CDNs costumam guardar `.css`/`.js` por muito tempo e entregar o PHP novo com o visual antigo. Basta enviar os arquivos do app e dar Ctrl+F5. Se o `assets/css/app.css` do servidor for de outra versão (por exemplo, a pasta `assets/` não foi enviada), aparece um aviso roxo no topo com o tamanho do arquivo encontrado. Ao mudar o CSS, atualize juntos `ASSET_CSS_VERSION` (em `src/helpers.php`) e `--bay-css` (no fim de `assets/css/app.css`).

## Segurança
Senhas de login com `password_hash`, CSRF em todos os formulários, consultas preparadas, limite de tentativas de login, cookies `HttpOnly/SameSite`, pastas `src/`, `pages/` e `storage/` bloqueadas via `.htaccess` e trilha de auditoria.

## Desenvolvimento local
```bash
php -S 127.0.0.1:8000     # precisa de um MySQL local; para testes sem MySQL use config.sample.php com driver sqlite
```
