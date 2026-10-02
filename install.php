<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
start_session();

$lock = APP_ROOT . '/storage/installed.lock';
if (is_file($lock) || $CONFIG) { http_response_code(403); exit('Já instalado. Apague este arquivo (install.php) do servidor.'); }

$err = ''; $v = ['host' => 'localhost', 'name' => '', 'user' => '', 'nome' => '', 'email' => '', 'empresa' => 'Minha Agência', 'driver' => 'mysql'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $err = 'Sessão expirada, tente novamente.'; }
    else {
        foreach ($v as $k => $_) $v[$k] = post($k, $v[$k]);
        $pass = post('pass'); $senha = (string) ($_POST['senha'] ?? '');
        try {
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Informe um e-mail válido para o administrador.');
            if (strlen($senha) < 8) throw new Exception('A senha do administrador precisa ter ao menos 8 caracteres.');
            $driver = $v['driver'] === 'sqlite' ? 'sqlite' : 'mysql';
            $db = ['driver' => $driver];
            if ($driver === 'sqlite') {
                $db['path'] = APP_ROOT . '/storage/bayapp.sqlite';
            } else {
                $db += ['host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $pass];
            }
            $key = base64_encode(random_bytes(32));
            $conf = ['db' => $db, 'app_key' => $key, 'timezone' => 'America/Sao_Paulo'];
            $GLOBALS['CONFIG'] = $conf;
            create_schema(db(), $driver);
            insert('users', ['nome' => $v['nome'] ?: 'Administrador', 'email' => strtolower($v['email']),
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT), 'papel' => 'admin', 'ativo' => 1, 'criado_em' => now()]);
            set_setting('empresa_nome', $v['empresa']);
            set_setting('schema_v', SCHEMA_VERSION);
            set_setting('cron_token', bin2hex(random_bytes(16)));
            set_setting('alert_emails', strtolower($v['email']));
            $php = "<?php\nreturn " . var_export($conf, true) . ";\n";
            if (file_put_contents(APP_ROOT . '/config.php', $php) === false) throw new Exception('Não foi possível gravar config.php (verifique permissões).');
            @chmod(APP_ROOT . '/config.php', 0640);
            file_put_contents($lock, now());
            $done = true;
        } catch (Throwable $ex) {
            $err = ($ex instanceof PDOException ? 'Erro de banco de dados: ' : '') . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="pt-BR" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalação — BayApp</title><link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png"><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body"><div class="blob b1"></div><div class="blob b2"></div>
<main class="auth-card wide anim-up">
<?php if (!empty($done)): ?>
  <div class="brand big"><?= brand_logo() ?></div>
  <h1>Tudo pronto! 🎉</h1>
  <p class="muted">O banco foi criado e o administrador cadastrado.</p>
  <ul class="checklist">
    <li><b>Apague o arquivo <code>install.php</code></b> do servidor agora.</li>
    <li>Faça login e abra <b>Configurações</b> para ligar os alertas de e-mail/WhatsApp.</li>
    <li>Crie o <b>Cron Job</b> diário no hPanel (o comando está em Configurações).</li>
    <li>Guarde um backup de <code>config.php</code>: a chave dentro dele descriptografa as senhas.</li>
  </ul>
  <a class="btn primary" href="index.php">Entrar no sistema →</a>
<?php else: ?>
  <div class="brand big"><?= brand_logo() ?></div>
  <h1>Instalação</h1><p class="muted">Preencha os dados do MySQL criado no hPanel e o primeiro administrador.</p>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="driver" value="mysql">
    <h3 class="full">Banco de dados (MySQL)</h3>
    <label>Servidor<input name="host" value="<?= e($v['host']) ?>" required></label>
    <label>Nome do banco<input name="name" value="<?= e($v['name']) ?>" placeholder="u123456789_bayapp" required></label>
    <label>Usuário<input name="user" value="<?= e($v['user']) ?>" required></label>
    <label>Senha do banco<input type="password" name="pass" autocomplete="new-password"></label>
    <h3 class="full">Administrador e empresa</h3>
    <label>Nome da empresa<input name="empresa" value="<?= e($v['empresa']) ?>" required></label>
    <label>Seu nome<input name="nome" value="<?= e($v['nome']) ?>" required></label>
    <label>E-mail (login)<input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <label>Senha (mín. 8)<input type="password" name="senha" minlength="8" autocomplete="new-password" required></label>
    <button class="btn primary full">Instalar</button>
  </form>
<?php endif; ?>
</main></body></html>
