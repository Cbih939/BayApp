# BayApp — Gestão de clientes

Aplicativo em **PHP 8 + MySQL** (preto, branco e roxo) para agências: cadastro de equipe e clientes, cofre de senhas criptografado, vencimentos com alertas (30/15/7 dias) por e-mail e WhatsApp, e central de links do Drive, Canva e Google Agenda. Feito para rodar na **hospedagem compartilhada da Hostinger**, sem Node nem Composer.

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
| **Alertas** | Cron diário envia avisos a **30, 15 e 7 dias** (uma vez por marco) por **e-mail** e **WhatsApp** |
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
6. **SSL:** ative o SSL grátis e descomente o redirecionamento HTTPS no `.htaccess`.
7. **Alertas:** em *Configurações* informe e-mails/WhatsApp e clique em **Enviar teste**.
8. **Cron:** *Avançado → Cron Jobs* → diário (ex.: 08:00) com o comando exibido em *Configurações*, algo como  
   `/usr/bin/php /home/uXXXX/domains/seudominio.com/public_html/cron.php`

> ⚠️ Faça backup do `config.php`: a `app_key` dentro dele é a chave que descriptografa as senhas. Sem ela, as senhas salvas não podem ser recuperadas.

## Portal do cliente
Em *Clientes → (cliente) → aba Portal*, crie o login e envie ao cliente o link `https://seudominio.com/portal.php` com e-mail e senha. Instalações antigas criam a tabela `client_users` automaticamente no primeiro acesso após a atualização.

## WhatsApp
- **CallMeBot (grátis):** envie “I allow callmebot to send me messages” ao número deles no WhatsApp para obter a *apikey* e informe seu número + apikey.
- **Webhook:** Z-API, Evolution API, Make, n8n… recebem `POST` JSON `{"phone","number","message","text"}` (token opcional como `Authorization: Bearer`).

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
