<?php
declare(strict_types=1);

const PERMS = [
    'admin'    => ['*'],
    'dev'      => ['clients.edit', 'creds.view', 'creds.edit', 'billing.view', 'billing.edit', 'links.edit'],
    'designer' => ['creds.view', 'creds.edit', 'billing.view', 'links.edit'],
];

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_name('bayapp');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT id, nome, email, papel, ativo FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$u || !$u['ativo']) { $u = null; unset($_SESSION['uid']); }
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
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    q('UPDATE users SET ultimo_login = ? WHERE id = ?', [now(), $u['id']]);
    audit('login', $u['email']);
    return null;
}
