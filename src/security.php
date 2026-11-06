<?php
declare(strict_types=1);

/* HTTPS e cabeçalhos, sessão por inatividade, recuperação de senha e verificação em duas etapas (TOTP). */

const SESSION_IDLE = 4 * 3600;          // encerra a sessão após 4 h sem nenhuma requisição
const PRE2FA_TTL = 300;                 // segundos para digitar o código depois da senha
const PWRESET_TTL = 3600;               // validade do link de redefinição
const PWRESET_MAX_HOUR = 3;             // links por conta, por hora
const TOTP_MAX_FAILS = 10;              // códigos errados por conta em 15 min
const TOTP_RECOVERY_CODES = 10;

/* ---------- HTTPS e cabeçalhos ---------- */

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_local_host(string $host): bool
{
    $h = strtolower((string) preg_replace('/:\d+$/', '', $host));
    return in_array($h, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($h, '.localhost') || str_ends_with($h, '.test');
}

/** Endereço do app a partir da requisição atual (usado só em telas de quem já está logado). */
function request_base_url(): string
{
    if (!isset($_SERVER['HTTP_HOST'])) return '';
    return (request_is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}

/** Redireciona para HTTPS (exceto em localhost ou com 'force_https' => false no config.php) e envia os cabeçalhos de segurança. */
function web_security(?array $config): void
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $local = $host === '' || is_local_host($host);
    if ($config && ($config['force_https'] ?? true) && !$local && !request_is_https() && preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) {
        $safe = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true);
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, $safe ? 301 : 308);
        exit;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
    if (!$local && request_is_https()) header('Strict-Transport-Security: max-age=15552000');
}

/* ---------- recuperação de senha ---------- */

function pwreset_table(string $kind): string
{
    return $kind === 'client' ? 'client_users' : 'users';
}

/** Conta ativa pelo e-mail. */
function pwreset_account(string $kind, string $email): ?array
{
    if ($kind === 'client') return row("SELECT cu.id, cu.nome, cu.email FROM client_users cu JOIN clients c ON c.id = cu.client_id WHERE cu.email = ? AND cu.ativo = 1 AND c.status = 'ativo'", [$email]);
    return row('SELECT id, nome, email FROM users WHERE email = ? AND ativo = 1', [$email]);
}

/**
 * Envia o link de redefinição. Nunca revela se o e-mail existe. O link usa o endereço salvo do app (app_url),
 * nunca o Host da requisição, para que um cabeçalho forjado não gere link para outro site.
 */
function pwreset_request(string $kind, string $email, string $script): void
{
    $email = strtolower(trim($email));
    $a = filter_var($email, FILTER_VALIDATE_EMAIL) ? pwreset_account($kind, $email) : null;
    if (!$a) return;
    q('DELETE FROM password_resets WHERE expira_em < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $recent = (int) val('SELECT COUNT(*) FROM password_resets WHERE kind = ? AND account_id = ? AND criado_em >= ?', [$kind, $a['id'], date('Y-m-d H:i:s', time() - 3600)]);
    if ($recent >= PWRESET_MAX_HOUR) return;
    if (app_url() === '') { notify_log('email', $email, 'Redefinição de senha', false, 'endereço do app desconhecido: um administrador precisa abrir Configurações'); return; }
    $token = bin2hex(random_bytes(32));
    insert('password_resets', ['kind' => $kind, 'account_id' => (int) $a['id'], 'token_hash' => hash('sha256', $token),
        'expira_em' => date('Y-m-d H:i:s', time() + PWRESET_TTL), 'usado_em' => null, 'criado_em' => now()]);
    $empresa = setting('empresa_nome', 'BayApp');
    send_mail([$email], "Redefinir sua senha — $empresa", mail_layout('Redefinir sua senha',
        '<p style="margin:0 0 8px">Olá, ' . e(explode(' ', $a['nome'])[0]) . '!</p><p style="margin:0">Recebemos um pedido para redefinir a sua senha. O link vale por 1 hora e só pode ser usado uma vez.</p>'
        . mail_button(app_url() . "/$script?p=redefinir&t=$token", 'Criar nova senha'),
        'Se não foi você, ignore este e-mail: sua senha continua a mesma.'));
    audit($kind === 'client' ? 'portal_pwreset_pedido' : 'pwreset_pedido', $email);
}

function pwreset_find(string $kind, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return row('SELECT * FROM password_resets WHERE kind = ? AND token_hash = ? AND usado_em IS NULL AND expira_em >= ?', [$kind, hash('sha256', $token), now()]);
}

/** Troca a senha, encerra as sessões abertas e invalida os demais links. A verificação em duas etapas continua ativa. */
function pwreset_finish(string $kind, array $reset, string $senha): void
{
    $table = pwreset_table($kind);
    $id = (int) $reset['account_id'];
    q("UPDATE $table SET senha_hash = ?, sess_ver = sess_ver + 1 WHERE id = ?", [password_hash($senha, PASSWORD_DEFAULT), $id]);
    q('UPDATE password_resets SET usado_em = ? WHERE kind = ? AND account_id = ? AND usado_em IS NULL', [now(), $kind, $id]);
    $a = row("SELECT nome, email FROM $table WHERE id = ?", [$id]);
    audit($kind === 'client' ? 'portal_pwreset_feito' : 'pwreset_feito', (string) ($a['email'] ?? "#$id"));
    if ($a) send_mail([$a['email']], 'Sua senha foi alterada — ' . setting('empresa_nome', 'BayApp'), mail_layout('Sua senha foi alterada',
        '<p style="margin:0">A senha da sua conta acabou de ser redefinida. Se não foi você, fale com a equipe imediatamente.</p>'));
}

/** Telas públicas "esqueci a senha" e "criar nova senha" (equipe e portal). */
function recover_page(string $kind, string $p, string $script): never
{
    header('Cache-Control: no-store');
    $empresa = setting('empresa_nome', 'BayApp');
    $token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
    $mode = $p === 'redefinir' ? 'nova' : 'pedir';
    $error = '';
    $reset = $mode === 'nova' ? pwreset_find($kind, $token) : null;
    if ($mode === 'nova' && !$reset) $mode = 'invalido';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_ok()) $error = 'Sessão expirada. Recarregue a página.';
        elseif ($mode === 'pedir') {
            $ip = client_ip();
            q('DELETE FROM login_attempts WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 900)]);
            if ((int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]) < 8) {
                insert('login_attempts', ['ip' => $ip, 'email' => 'reset', 'criado_em' => now()]);
                pwreset_request($kind, post('email'), $script);
            }
            $mode = 'enviado';   // mesma resposta em todos os casos
        } elseif ($mode === 'nova') {
            $n = (string) ($_POST['nova'] ?? '');
            if (strlen($n) < 8) $error = 'A nova senha precisa ter ao menos 8 caracteres.';
            elseif ($n !== (string) ($_POST['nova2'] ?? '')) $error = 'As duas senhas não são iguais.';
            else { pwreset_finish($kind, $reset, $n); $mode = 'pronto'; }
        }
    }
    require APP_ROOT . '/pages/recover.php';
    exit;
}

/* ---------- verificação em duas etapas (TOTP, RFC 6238) ---------- */

function base32_encode(string $bin): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $abc[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $b32): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper($b32)) as $c) {
        $v = strpos($abc, $c);
        if ($v !== false) $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $secret, int $counter): string
{
    $h = hash_hmac('sha1', pack('J', $counter), base32_decode($secret), true);
    $o = ord($h[19]) & 0x0F;
    $n = (unpack('N', substr($h, $o, 4))[1] & 0x7FFFFFFF) % 1000000;
    return str_pad((string) $n, 6, '0', STR_PAD_LEFT);
}

/** Retorna o contador do código válido (tolera 30 s para cada lado) ou null. Código já usado não vale de novo. */
function totp_match(string $secret, string $code, int $last = 0): ?int
{
    $now = (int) floor(time() / 30);
    for ($c = $now - 1; $c <= $now + 1; $c++) {
        if ($c > $last && hash_equals(totp_code($secret, $c), $code)) return $c;
    }
    return null;
}

function totp_uri(string $secret, string $email): string
{
    $issuer = setting('empresa_nome', 'BayApp');
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
}

function recovery_hash(string $code): string
{
    return hash_hmac('sha256', strtoupper(str_replace('-', '', $code)), app_key());
}

/** Gera os códigos de recuperação. Retorna [códigos para mostrar uma vez, JSON com os hashes para salvar]. */
function recovery_generate(): array
{
    $codes = [];
    for ($i = 0; $i < TOTP_RECOVERY_CODES; $i++) {
        $c = base32_encode(random_bytes(7));
        $codes[] = substr($c, 0, 5) . '-' . substr($c, 5, 5);
    }
    return [$codes, json_encode(array_map('recovery_hash', $codes))];
}

function totp_must_enroll(array $u): bool
{
    if (!empty($u['totp_secret'])) return false;
    $rule = setting('require_2fa');
    return $rule === 'all' || ($rule === 'admins' && $u['papel'] === 'admin');
}

/** Segunda etapa do login. Retorna mensagem de erro ou null em caso de sucesso. */
function attempt_2fa(string $code): ?string
{
    $pre = $_SESSION['pre2fa'] ?? null;
    if (!$pre || time() - $pre['at'] > PRE2FA_TTL) { unset($_SESSION['pre2fa']); return 'O tempo acabou. Entre novamente com sua senha.'; }
    $u = row('SELECT * FROM users WHERE id = ? AND ativo = 1', [$pre['uid']]);
    if (!$u || !$u['totp_secret']) { unset($_SESSION['pre2fa']); return 'Entre novamente com sua senha.'; }
    $ip = client_ip();
    $tag = '2fa:' . $u['email'];
    q('DELETE FROM login_attempts WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 900)]);
    if ((int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]) >= 8 || (int) val('SELECT COUNT(*) FROM login_attempts WHERE email = ?', [$tag]) >= TOTP_MAX_FAILS) {
        return 'Muitas tentativas. Aguarde 15 minutos.';
    }
    $code = strtoupper(preg_replace('/\s+/', '', $code));
    $ok = false;
    if (preg_match('/^\d{6}$/', $code)) {
        $c = totp_match(decrypt_secret($u['totp_secret']), $code, (int) $u['totp_last']);
        if ($c !== null) { update('users', (int) $u['id'], ['totp_last' => $c]); $ok = true; }
    } elseif ($code !== '') {
        $hashes = json_decode((string) $u['totp_recovery'], true) ?: [];
        $i = array_search(recovery_hash($code), $hashes, true);
        if ($i !== false) {
            unset($hashes[$i]);
            update('users', (int) $u['id'], ['totp_recovery' => json_encode(array_values($hashes))]);
            audit('2fa_codigo_recuperacao', $u['email'] . ' · restam ' . count($hashes));
            $ok = true;
        }
    }
    if (!$ok) {
        insert('login_attempts', ['ip' => $ip, 'email' => $tag, 'criado_em' => now()]);
        return 'Código incorreto.';
    }
    q('DELETE FROM login_attempts WHERE ip = ? OR email = ?', [$ip, $tag]);
    login_finish($u);
    return null;
}
