<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
if (!$CONFIG) redirect(is_file(__DIR__ . '/install.php') ? 'install.php' : 'README.md');
require __DIR__ . '/src/actions.php';
start_session();

$NAV = [
    'dashboard'   => ['Início', 'home', null],
    'clientes'    => ['Clientes', 'briefcase', null],
    'senhas'      => ['Senhas', 'key', 'creds.view'],
    'vencimentos' => ['Vencimentos', 'calendar', 'billing.view'],
    'links'       => ['Pastas & Links', 'folder', null],
    'usuarios'    => ['Equipe', 'users', 'users'],
    'guia'        => ['Guia', 'book', null],
    'config'      => ['Configurações', 'settings', 'settings'],
];
$p = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'dashboard'));

/* ---------- Login / logout ---------- */
if ($p === 'logout') { audit('logout'); session_destroy(); redirect('index.php'); }
if (!user()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = !csrf_ok() ? 'Sessão expirada. Recarregue a página.' : (attempt_login(post('email'), (string) ($_POST['senha'] ?? '')) ?? '');
        if (!$error) redirect(url('dashboard', ['welcome' => 1]));
    }
    require __DIR__ . '/pages/login.php';
    exit;
}

/* ---------- AJAX: revelar senha ---------- */
if ($p === 'ajax') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok() || !can('creds.view')) { http_response_code(403); exit('{"erro":"negado"}'); }
    $c = row('SELECT id, titulo, senha_enc FROM credentials WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$c) { http_response_code(404); exit('{"erro":"não encontrado"}'); }
    audit('senha_revelada', $c['titulo']);
    exit(json_encode(['senha' => decrypt_secret($c['senha_enc'])]));
}

/* ---------- POST de ações ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = safe_return($_POST['return'] ?? '');
    try {
        if (!csrf_ok()) throw new RuntimeException('Sessão expirada. Recarregue a página e tente de novo.');
        $back = handle_action(post('action'));
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        flash('Erro inesperado ao salvar. Tente novamente.', 'err');
        error_log('[bayapp] ' . $ex);
    }
    redirect($back);
}

/* ---------- Renderização ---------- */
if (!isset($NAV[$p]) && $p !== 'cliente') $p = 'dashboard';
if (isset($NAV[$p]) && $NAV[$p][2] && !can($NAV[$p][2])) { flash('Seu perfil não acessa esta área.', 'err'); redirect(url('dashboard')); }

$me = user();
require __DIR__ . '/pages/_partials.php';
ob_start();
require __DIR__ . "/pages/$p.php";
$content = ob_get_clean();
require __DIR__ . '/pages/_layout.php';
