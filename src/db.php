<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg('db');
    if (!$c) throw new RuntimeException('Aplicativo não instalado.');
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts + [PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function insert(string $table, array $data): int
{
    $cols = implode(',', array_keys($data));
    $ph = implode(',', array_fill(0, count($data), '?'));
    q("INSERT INTO $table ($cols) VALUES ($ph)", $data);
    return (int) db()->lastInsertId();
}

function update(string $table, int $id, array $data): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}

/** Configurações lidas uma vez por requisição; set_setting() mantém o cache em dia. */
function &settings_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT chave, valor FROM settings') as $r) $cache[$r['chave']] = (string) $r['valor'];
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    return settings_cache()[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    if (val('SELECT COUNT(*) FROM settings WHERE chave = ?', [$key])) {
        q('UPDATE settings SET valor = ? WHERE chave = ?', [$value, $key]);
    } else {
        q('INSERT INTO settings (chave, valor) VALUES (?, ?)', [$key, $value]);
    }
    $cache = &settings_cache();
    $cache[$key] = $value;
}

function audit(string $acao, string $detalhe = ''): void
{
    try {
        insert('audit', [
            'user_id' => $_SESSION['uid'] ?? null,
            'acao' => $acao,
            'detalhe' => mb_substr($detalhe, 0, 250),
            'ip' => client_ip(),
            'criado_em' => now(),
        ]);
    } catch (Throwable $e) {
    }
}

/** Esquema portável MySQL/SQLite. */
function schema_sql(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $eng = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $t = [
        "CREATE TABLE IF NOT EXISTS users (
            id $pk, nome VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
            senha_hash VARCHAR(255) NOT NULL, papel VARCHAR(20) NOT NULL DEFAULT 'designer',
            telefone VARCHAR(40) NULL, ativo INT NOT NULL DEFAULT 1,
            ultimo_login VARCHAR(19) NULL, criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS clients (
            id $pk, nome VARCHAR(160) NOT NULL, empresa VARCHAR(160) NULL, email VARCHAR(190) NULL,
            telefone VARCHAR(40) NULL, whatsapp VARCHAR(40) NULL, documento VARCHAR(30) NULL,
            site VARCHAR(255) NULL, notas TEXT NULL, status VARCHAR(12) NOT NULL DEFAULT 'ativo',
            criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS credentials (
            id $pk, client_id INT NOT NULL, categoria VARCHAR(40) NOT NULL, titulo VARCHAR(160) NOT NULL,
            url VARCHAR(500) NULL, login VARCHAR(255) NULL, senha_enc TEXT NULL, notas TEXT NULL,
            criado_por INT NULL, criado_em VARCHAR(19) NOT NULL, atualizado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS billings (
            id $pk, client_id INT NOT NULL, tipo VARCHAR(40) NOT NULL, descricao VARCHAR(200) NOT NULL,
            fornecedor VARCHAR(120) NULL, valor DECIMAL(12,2) NULL, vencimento VARCHAR(10) NOT NULL,
            recorrencia VARCHAR(10) NOT NULL DEFAULT 'nenhuma', status VARCHAR(10) NOT NULL DEFAULT 'pendente',
            pago_em VARCHAR(19) NULL, avisar_email INT NOT NULL DEFAULT 1, avisar_whatsapp INT NOT NULL DEFAULT 1, avisar_cliente INT NOT NULL DEFAULT 1,
            notas TEXT NULL, modelo VARCHAR(10) NULL, criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS alert_log (
            id $pk, billing_id INT NOT NULL, vencimento VARCHAR(10) NOT NULL, marco INT NOT NULL,
            canal VARCHAR(12) NOT NULL, status VARCHAR(8) NOT NULL, detalhe VARCHAR(250) NULL,
            enviado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (billing_id) REFERENCES billings(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS links (
            id $pk, client_id INT NULL, tipo VARCHAR(20) NOT NULL, titulo VARCHAR(160) NOT NULL,
            url VARCHAR(700) NOT NULL, notas VARCHAR(500) NULL, criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS settings (chave VARCHAR(60) PRIMARY KEY, valor TEXT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS audit (
            id $pk, user_id INT NULL, acao VARCHAR(60) NOT NULL, detalhe VARCHAR(250) NULL,
            ip VARCHAR(45) NULL, criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id $pk, ip VARCHAR(45) NOT NULL, email VARCHAR(190) NULL, criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS client_users (
            id $pk, client_id INT NOT NULL, nome VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
            senha_hash VARCHAR(255) NOT NULL, ativo INT NOT NULL DEFAULT 1,
            ultimo_login VARCHAR(19) NULL, criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS messages (
            id $pk, client_id INT NOT NULL, sender VARCHAR(8) NOT NULL, user_id INT NULL, client_user_id INT NULL,
            autor VARCHAR(120) NOT NULL, corpo TEXT NOT NULL, lida_em VARCHAR(19) NULL, criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS chat_presence (
            id $pk, client_id INT NOT NULL, side VARCHAR(8) NOT NULL, user_id INT NOT NULL,
            nome VARCHAR(120) NOT NULL, seen_at VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS tasks (
            id $pk, titulo VARCHAR(200) NOT NULL, descricao TEXT NULL, link VARCHAR(700) NULL, client_id INT NULL, assignee_id INT NULL,
            criado_por INT NULL, prioridade VARCHAR(8) NOT NULL DEFAULT 'media', status VARCHAR(8) NOT NULL DEFAULT 'a_fazer',
            prazo VARCHAR(10) NULL, concluida_em VARCHAR(19) NULL, criado_em VARCHAR(19) NOT NULL, atualizado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
            FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS task_items (
            id $pk, task_id INT NOT NULL, texto VARCHAR(300) NOT NULL, feito INT NOT NULL DEFAULT 0, ordem INT NOT NULL DEFAULT 0,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS plans (
            id $pk, nome VARCHAR(120) NOT NULL, descricao VARCHAR(300) NULL, valor DECIMAL(12,2) NULL,
            ativo INT NOT NULL DEFAULT 1, criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS plan_items (
            id $pk, plan_id INT NOT NULL, tipo VARCHAR(20) NOT NULL, quantidade INT NOT NULL DEFAULT 1, descricao VARCHAR(160) NULL,
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS contracts (
            id $pk, client_id INT NOT NULL, plan_id INT NULL, inicio VARCHAR(10) NOT NULL, fim VARCHAR(10) NULL,
            valor DECIMAL(12,2) NULL, periodicidade VARCHAR(10) NOT NULL DEFAULT 'mensal', url VARCHAR(700) NULL, notas TEXT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'ativo', criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS posts (
            id $pk, client_id INT NOT NULL, dia VARCHAR(10) NOT NULL, hora VARCHAR(5) NULL, canal VARCHAR(12) NOT NULL,
            formato VARCHAR(12) NOT NULL DEFAULT 'post', titulo VARCHAR(200) NULL, legenda TEXT NULL, midia_url VARCHAR(700) NULL, imagem VARCHAR(60) NULL, imagem_nome VARCHAR(255) NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'rascunho', publicado_em VARCHAR(19) NULL, criado_por INT NULL,
            criado_em VARCHAR(19) NOT NULL, atualizado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS payments (
            id $pk, billing_id INT NOT NULL, client_id INT NOT NULL, competencia VARCHAR(7) NOT NULL, vencimento VARCHAR(10) NULL,
            valor DECIMAL(12,2) NULL, pago_em VARCHAR(19) NOT NULL, user_id INT NULL,
            FOREIGN KEY (billing_id) REFERENCES billings(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS internal_bills (
            id $pk, categoria VARCHAR(20) NOT NULL DEFAULT 'outro', descricao VARCHAR(200) NOT NULL, fornecedor VARCHAR(120) NULL,
            valor DECIMAL(12,2) NULL, vencimento VARCHAR(10) NOT NULL, recorrencia VARCHAR(10) NOT NULL DEFAULT 'nenhuma',
            status VARCHAR(10) NOT NULL DEFAULT 'pendente', pago_em VARCHAR(19) NULL, avisar_email INT NOT NULL DEFAULT 1,
            avisar_whatsapp INT NOT NULL DEFAULT 1, notas TEXT NULL, criado_em VARCHAR(19) NOT NULL){$eng}",
        "CREATE TABLE IF NOT EXISTS internal_payments (
            id $pk, bill_id INT NOT NULL, competencia VARCHAR(7) NOT NULL, vencimento VARCHAR(10) NULL, valor DECIMAL(12,2) NULL,
            pago_em VARCHAR(19) NOT NULL, user_id INT NULL,
            FOREIGN KEY (bill_id) REFERENCES internal_bills(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS internal_alert_log (
            id $pk, bill_id INT NOT NULL, vencimento VARCHAR(10) NOT NULL, marco INT NOT NULL, canal VARCHAR(12) NOT NULL,
            status VARCHAR(8) NOT NULL, detalhe VARCHAR(250) NULL, enviado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (bill_id) REFERENCES internal_bills(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS files (
            id $pk, billing_id INT NOT NULL, client_id INT NOT NULL, competencia VARCHAR(7) NOT NULL, kind VARCHAR(10) NOT NULL DEFAULT 'boleto',
            nome VARCHAR(255) NOT NULL, arquivo VARCHAR(60) NOT NULL, mime VARCHAR(100) NULL, tamanho INT NOT NULL DEFAULT 0,
            user_id INT NULL, criado_em VARCHAR(19) NOT NULL,
            FOREIGN KEY (billing_id) REFERENCES billings(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE){$eng}",
        "CREATE TABLE IF NOT EXISTS notify_log (
            id $pk, canal VARCHAR(12) NOT NULL, destino VARCHAR(250) NOT NULL, assunto VARCHAR(200) NULL,
            status VARCHAR(8) NOT NULL, detalhe VARCHAR(250) NULL, criado_em VARCHAR(19) NOT NULL){$eng}",
        'CREATE INDEX idx_cred_client ON credentials(client_id)',
        'CREATE INDEX idx_bill_venc ON billings(vencimento)',
        'CREATE INDEX idx_bill_client ON billings(client_id)',
        'CREATE INDEX idx_link_client ON links(client_id)',
        'CREATE INDEX idx_alert_bill ON alert_log(billing_id)',
        'CREATE INDEX idx_login_ip ON login_attempts(ip)',
        'CREATE INDEX idx_msg_client ON messages(client_id, id)',
        'CREATE INDEX idx_msg_unread ON messages(sender, lida_em)',
        'CREATE INDEX idx_task_assignee ON tasks(assignee_id, status)',
        'CREATE INDEX idx_task_client ON tasks(client_id)',
        'CREATE INDEX idx_presence ON chat_presence(client_id, side, user_id)',
        'CREATE INDEX idx_titem_task ON task_items(task_id)',
        'CREATE INDEX idx_files_cycle ON files(billing_id, competencia)',
        'CREATE INDEX idx_files_client ON files(client_id)',
        'CREATE INDEX idx_ibill_venc ON internal_bills(vencimento)',
        'CREATE INDEX idx_ipay_bill ON internal_payments(bill_id)',
        'CREATE INDEX idx_post_client ON posts(client_id, dia)',
        'CREATE INDEX idx_post_dia ON posts(dia)',
        'CREATE INDEX idx_contract_client ON contracts(client_id)',
        'CREATE INDEX idx_pay_client ON payments(client_id, competencia)',
        'CREATE INDEX idx_pay_bill ON payments(billing_id)',
        'CREATE INDEX idx_notify_when ON notify_log(criado_em)',
    ];
    return $t;
}

const SCHEMA_VERSION = '10';

/** Atualiza instalações antigas (cria tabelas novas) uma única vez. */
function ensure_schema(): void
{
    if (setting('schema_v') === SCHEMA_VERSION) return;
    create_schema(db(), cfg('db')['driver'] ?? 'mysql');
    $v = (int) setting('schema_v', '0');
    if ($v < 6) backfill_payments();
    if ($v < 7) backfill_payment_dates();
    if ($v < 8) migrate_post_status();
    set_setting('schema_v', SCHEMA_VERSION);
}

function create_schema(PDO $pdo, string $driver): void
{
    // Coluna nova em instalações antigas (falha silenciosa se já existir).
    foreach (['ALTER TABLE tasks ADD COLUMN link VARCHAR(700) NULL', 'ALTER TABLE billings ADD COLUMN modelo VARCHAR(10) NULL',
        'ALTER TABLE payments ADD COLUMN vencimento VARCHAR(10) NULL',
        'ALTER TABLE posts ADD COLUMN imagem VARCHAR(60) NULL', 'ALTER TABLE posts ADD COLUMN imagem_nome VARCHAR(255) NULL',
        'ALTER TABLE billings ADD COLUMN avisar_cliente INT NOT NULL DEFAULT 1'] as $alter) {
        try { $pdo->exec($alter); } catch (PDOException $e) {}
    }

    foreach (schema_sql($driver) as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // índices já existentes em reinstalações
            if (stripos($sql, 'CREATE INDEX') !== 0) throw $e;
        }
    }
}
