<?php
declare(strict_types=1);

const PERMS = [
    'admin'    => ['*'],
    'dev'      => ['clients.edit', 'creds.view', 'creds.edit', 'billing.view', 'billing.edit', 'links.edit', 'posts.edit'],
    'designer' => ['clients.edit', 'creds.view', 'creds.edit', 'creds.delete', 'billing.view', 'links.edit', 'links.delete', 'posts.edit'],
];

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE);
    session_name('bayapp');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => request_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['visto']) && time() - $_SESSION['visto'] > SESSION_IDLE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['visto'] = time();
}

function user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT id, nome, email, papel, ativo, acesso_todos, sess_ver, totp_secret FROM users WHERE id = ?', [$_SESSION['uid']]);
        // sess_ver muda quando a senha é trocada: derruba as sessões abertas com a senha antiga
        if (!$u || !$u['ativo'] || (int) ($_SESSION['sv'] ?? 1) !== (int) $u['sess_ver']) { $u = null; unset($_SESSION['uid'], $_SESSION['sv']); }
    }
    return $u;
}

function can(string $perm): bool
{
    $u = user();
    if (!$u) return false;
    $p = PERMS[$u['papel']] ?? [];
    return in_array('*', $p, true) || in_array($perm, $p, true);
}

function require_can(string $perm): void
{
    if (!can($perm)) {
        flash('Você não tem permissão para esta ação.', 'err');
        redirect(url('dashboard'));
    }
}

/* ---------------- Acesso por cliente ---------------- */

/**
 * IDs dos clientes que o usuário logado pode ver. null = todos: administrador, usuário com "todos os clientes"
 * e rotinas sem usuário da equipe (cron e portal, que já filtram pelo próprio cliente).
 */
function allowed_clients(): ?array
{
    $u = user();
    if (!$u || $u['papel'] === 'admin' || (int) $u['acesso_todos'] === 1) return null;
    static $ids = [];
    return $ids[$u['id']] ??= array_map('intval', array_column(rows('SELECT client_id FROM user_clients WHERE user_id = ?', [$u['id']]), 'client_id'));
}

/** Registro sem cliente (tarefa ou link "geral") é visível a todos. */
function client_allowed(?int $cid): bool
{
    $ids = allowed_clients();
    return $ids === null || !$cid || in_array($cid, $ids, true);
}

function require_client(?int $cid): void
{
    if (!client_allowed($cid)) throw new RuntimeException('Você não tem acesso a este cliente.');
}

/** Confere o cliente do registro antes de alterá-lo. $table é sempre um nome fixo do código. */
function require_client_of(string $table, int $id): void
{
    if ($id) require_client((int) val("SELECT client_id FROM $table WHERE id = ?", [$id]) ?: null);
}

/** Trecho SQL " AND col IN (...)" com os clientes permitidos; vazio quando o usuário vê todos. */
function client_scope(string $col, bool $orNull = false): string
{
    $ids = allowed_clients();
    if ($ids === null) return '';
    $in = $ids ? "$col IN (" . implode(',', $ids) . ')' : '1 = 0';
    return $orNull ? " AND ($col IS NULL OR $in)" : " AND $in";
}

/** Tarefas visíveis: sem cliente, de cliente permitido ou atribuídas ao próprio usuário (alias t). */
function task_scope(): string
{
    $ids = allowed_clients();
    if ($ids === null) return '';
    return ' AND (t.client_id IS NULL' . ($ids ? ' OR t.client_id IN (' . implode(',', $ids) . ')' : '') . ' OR t.assignee_id = ' . (int) user()['id'] . ')';
}

function require_task(int $id): void
{
    $t = row('SELECT client_id, assignee_id FROM tasks WHERE id = ?', [$id]);
    if (!$t) throw new RuntimeException('Tarefa não encontrada.');
    if ((int) $t['assignee_id'] !== (int) (user()['id'] ?? 0)) require_client($t['client_id'] === null ? null : (int) $t['client_id']);
}

/** Clientes visíveis para os seletores de filtro. */
function visible_clients(bool $onlyActive = false): array
{
    return rows('SELECT id, nome FROM clients WHERE 1 = 1' . ($onlyActive ? " AND status = 'ativo'" : '') . client_scope('id') . ' ORDER BY nome');
}

/** Retorna mensagem de erro ou null em caso de sucesso. */
function attempt_login(string $email, string $pass): ?string
{
    $ip = client_ip();
    q('DELETE FROM login_attempts WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 900)]);
    if ((int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]) >= 8) {
        return 'Muitas tentativas. Aguarde 15 minutos.';
    }
    $u = row('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
    if (!$u || !$u['ativo'] || !password_verify($pass, $u['senha_hash'])) {
        insert('login_attempts', ['ip' => $ip, 'email' => $email, 'criado_em' => now()]);
        return 'E-mail ou senha incorretos.';
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    if ($u['totp_secret']) {   // senha certa: falta a segunda etapa
        session_regenerate_id(true);
        unset($_SESSION['uid'], $_SESSION['cuid'], $_SESSION['sv']);
        $_SESSION['pre2fa'] = ['uid' => (int) $u['id'], 'at' => time()];
        return null;
    }
    login_finish($u);
    return null;
}

function login_finish(array $u): void
{
    session_regenerate_id(true);
    unset($_SESSION['cuid'], $_SESSION['pre2fa'], $_SESSION['csv']);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['sv'] = (int) $u['sess_ver'];
    q('UPDATE users SET ultimo_login = ? WHERE id = ?', [now(), $u['id']]);
    // o link de recuperação de senha precisa do endereço do app; só um administrador logado pode defini-lo
    if ($u['papel'] === 'admin' && setting('app_url') === '') set_setting('app_url', request_base_url());
    audit('login', $u['email']);
}

/* ---------------- Portal do cliente ---------------- */

/** Usuário do portal logado (sessão separada da equipe), ou null. */
function portal_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['cuid'])) {
        $u = row('SELECT cu.id, cu.nome, cu.email, cu.client_id, cu.ativo, cu.sess_ver, c.nome AS cliente, c.empresa, c.status
                  FROM client_users cu JOIN clients c ON c.id = cu.client_id WHERE cu.id = ?', [$_SESSION['cuid']]);
        if (!$u || !$u['ativo'] || $u['status'] !== 'ativo' || (int) ($_SESSION['csv'] ?? 1) !== (int) $u['sess_ver']) { $u = null; unset($_SESSION['cuid'], $_SESSION['csv']); }
    }
    return $u;
}

/** Retorna mensagem de erro ou null em caso de sucesso. */
function attempt_portal_login(string $email, string $pass): ?string
{
    $ip = client_ip();
    q('DELETE FROM login_attempts WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 900)]);
    if ((int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]) >= 8) return 'Muitas tentativas. Aguarde 15 minutos.';
    $u = row('SELECT cu.*, c.status FROM client_users cu JOIN clients c ON c.id = cu.client_id WHERE cu.email = ?', [strtolower($email)]);
    if (!$u || !$u['ativo'] || $u['status'] !== 'ativo' || !password_verify($pass, $u['senha_hash'])) {
        insert('login_attempts', ['ip' => $ip, 'email' => $email, 'criado_em' => now()]);
        return 'E-mail ou senha incorretos.';
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    session_regenerate_id(true);
    unset($_SESSION['uid'], $_SESSION['sv'], $_SESSION['pre2fa']);
    $_SESSION['cuid'] = (int) $u['id'];
    $_SESSION['csv'] = (int) $u['sess_ver'];
    q('UPDATE client_users SET ultimo_login = ? WHERE id = ?', [now(), $u['id']]);
    audit('portal_login', $u['email']);
    return null;
}
