<?php
declare(strict_types=1);
/** Portal do cliente: acesso somente leitura aos pagamentos (com boleto e quitação), pastas e links do próprio cliente. */
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

if ($p === 'chat_api') chat_api('client', (int) $me['client_id'], $me);
if ($p === 'vencimentos') $p = 'pagamentos';   // nome antigo da aba

/* Download de boleto/quitação: só arquivos do próprio cliente e que não sejam internos */
if ($p === 'arquivo') {
    $f = row("SELECT * FROM files WHERE id = ? AND client_id = ? AND kind <> 'interno'", [(int) ($_GET['id'] ?? 0), (int) $me['client_id']]);
    if (!$f) { http_response_code(404); exit('Arquivo não encontrado.'); }
    audit('portal_download', $f['nome']);
    serve_file($f);
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

if (!in_array($p, ['inicio', 'pagamentos', 'links', 'chat'], true)) $p = 'inicio';
require __DIR__ . '/pages/_partials.php';

$cid = (int) $me['client_id'];
/* Pagamentos do cliente (sem observações internas, canais de alerta nem arquivos internos). */
$open = cycle_open_events($cid, true);
$paid = cycle_paid_events($cid, true, null, null, 36);
$links = rows('SELECT * FROM links WHERE client_id = ? ORDER BY tipo, titulo', [$cid]);
$ret = 'portal.php';

$tabs = ['inicio' => ['Início', 'home'], 'pagamentos' => ['Pagamentos', 'card'], 'links' => ['Pastas & Links', 'folder'], 'chat' => ['Mensagens', 'chat']];
$chatUnread = chat_unread_client($cid);
ob_start();
require __DIR__ . "/pages/portal_$p.php";
$content = ob_get_clean();
require __DIR__ . '/pages/portal_layout.php';
