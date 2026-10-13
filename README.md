# BayApp — Gestão de clientes

Aplicativo em **PHP 8 + MySQL** (preto, branco e roxo) para agências: cadastro de equipe e clientes, cofre de senhas criptografado, vencimentos com alertas (30/15/7 dias) por e-mail e WhatsApp, e central de links do Drive, Canva e Google Agenda. Feito para rodar na **hospedagem compartilhada da Hostinger**, sem Node nem Composer.

## Funcionalidades
| Módulo | O que faz |
|---|---|
| **Equipe** | Usuários *Administrador*, *Designer* e *Desenvolvedor* (criar, editar, desativar, excluir) com permissões por perfil |
| **Clientes** | Cadastro em cards; página do cliente com abas Senhas · Vencimentos · Links |
| **Senhas** | Por cliente e tipo (Instagram, site, hospedagem, domínio, e-mail…). Cadastro rápido: chips de tipo, gerador de senha forte, “colar tudo de uma vez”. Criptografia AES-256-GCM; ver/copiar fica registrado |
| **Vencimentos** | Domínios, hospedagem, e-mail, SSL etc. com valor, recorrência (mensal/anual) e botão “pago” que já agenda o próximo ciclo |
| **Alertas** | Cron diário envia avisos a **30, 15 e 7 dias** (uma vez por marco) por **e-mail** e **WhatsApp** |
| **Pastas & Links** | Drive, Canva, Agenda, Docs, Figma — tipo detectado automaticamente |
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

## Segurança
Senhas de login com `password_hash`, CSRF em todos os formulários, consultas preparadas, limite de tentativas de login, cookies `HttpOnly/SameSite`, pastas `src/`, `pages/` e `storage/` bloqueadas via `.htaccess` e trilha de auditoria.

## Desenvolvimento local
```bash
php -S 127.0.0.1:8000     # precisa de um MySQL local; para testes sem MySQL use config.sample.php com driver sqlite
```
