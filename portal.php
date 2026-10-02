<?php
declare(strict_types=1);
/** Portal do cliente: acesso somente leitura aos vencimentos e links do próprio cliente. */
require __DIR__ . '/src/bootstrap.php';
if (!$CONFIG) redirect('index.php');
start_session();

$empresa = setting('empresa_nome', 'BayApp');
$p = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'inicio'));

if ($p === 'sair') { audit('portal_logout'); unset($_SESSION['cuid']); redirect('portal.php'); }

$me = portal_user();
if (!$me) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = !csrf_ok() ? 'Sessão expirada. Recarregue a página.' : (attempt_portal_login(post('email'), (string) ($_POST['senha'] ?? '')) ?? '');
        if (!$error) redirect('portal.php');
    }
    require __DIR__ . '/pages/portal_login.php';
    exit;
}

/* Troca de senha do próprio cliente */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sessão expirada. Recarregue a página.');
        $cur = (string) ($_POST['atual'] ?? ''); $new = (string) ($_POST['nova'] ?? '');
        $r = row('SELECT senha_hash FROM client_users WHERE id = ?', [$me['id']]);
        if (!password_verify($cur, $r['senha_hash'])) throw new RuntimeException('Senha atual incorreta.');
        if (strlen($new) < 8) throw new RuntimeException('A nova senha precisa ter ao menos 8 caracteres.');
        update('client_users', (int) $me['id'], ['senha_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        flash('Senha alterada com sucesso.');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    }
    redirect('portal.php?p=' . $p);
}

if (!in_array($p, ['inicio', 'vencimentos', 'links'], true)) $p = 'inicio';
require __DIR__ . '/pages/_partials.php';

$cid = (int) $me['client_id'];
/* Dados internos (observações, canais de alerta) nunca vão para o cliente. */
$bills = array_map(fn($b) => ['notas' => null, 'avisar_email' => 0, 'avisar_whatsapp' => 0, 'fornecedor' => $b['fornecedor']] + $b,
    rows('SELECT * FROM billings WHERE client_id = ? ORDER BY vencimento', [$cid]));
$pending = array_values(array_filter($bills, fn($b) => $b['status'] === 'pendente'));
$links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$cid]);
$ret = 'portal.php';

$tabs = ['inicio' => ['Início', 'home'], 'vencimentos' => ['Vencimentos', 'calendar'], 'links' => ['Pastas & Links', 'folder']];
ob_start();
require __DIR__ . "/pages/portal_$p.php";
$content = ob_get_clean();
require __DIR__ . '/pages/portal_layout.php';
