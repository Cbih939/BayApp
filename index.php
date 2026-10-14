<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
if (!$CONFIG) redirect(is_file(__DIR__ . '/install.php') ? 'install.php' : 'README.md');
require __DIR__ . '/src/actions.php';
start_session();

$NAV = [
    'dashboard'   => ['Início', 'home', null],
    'clientes'    => ['Clientes', 'briefcase', null],
    'pacotes'     => ['Pacotes', 'package', 'clients.edit'],
    'senhas'      => ['Senhas', 'key', 'creds.view'],
    'tarefas'     => ['Tarefas', 'tasks', null],
    'calendario'  => ['Calendário', 'cal', null],
    'vencimentos' => ['Serviços', 'wallet', 'billing.view'],
    'relatorios'  => ['Relatórios', 'chart', null],
    'chat'        => ['Mensagens', 'chat', null],
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

/* ---------- AJAX: chat (polling) ---------- */
if ($p === 'chat_api') chat_api('team', null, user());

/* ---------- AJAX: tarefas (mover no quadro, marcar/adicionar item do checklist) ---------- */
if ($p === 'task_api') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { http_response_code(403); exit('{"erro":"negado"}'); }
    try {
        $a = post('a', 'move');
        if ($a === 'move') { task_move_to((int) ($_POST['id'] ?? 0), post('status')); exit('{"ok":true}'); }
        if ($a === 'item_toggle') {
            $it = row('SELECT id, task_id FROM task_items WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            if (!$it) throw new RuntimeException('Item não encontrado.');
            update('task_items', (int) $it['id'], ['feito' => post('feito') === '1' ? 1 : 0]);
        } elseif ($a === 'item_add') {
            $tid = (int) ($_POST['task_id'] ?? 0); $txt = mb_substr(post('texto'), 0, 300);
            if (!row('SELECT id FROM tasks WHERE id = ?', [$tid])) throw new RuntimeException('Tarefa não encontrada.');
            if ($txt === '') throw new RuntimeException('Digite o item.');
            $newId = insert('task_items', ['task_id' => $tid, 'texto' => $txt, 'feito' => 0, 'ordem' => (int) val('SELECT COALESCE(MAX(ordem), 0) + 1 FROM task_items WHERE task_id = ?', [$tid])]);
            $it = ['task_id' => $tid];
        } else throw new RuntimeException('Ação inválida.');
        update('tasks', (int) $it['task_id'], ['atualizado_em' => now()]);
        $all = rows('SELECT feito FROM task_items WHERE task_id = ?', [$it['task_id']]);
        exit(json_encode(['ok' => true, 'id' => $newId ?? null, 'done' => count(array_filter($all, fn($r) => $r['feito'])), 'total' => count($all)]));
    } catch (RuntimeException $ex) { http_response_code(422); exit(json_encode(['erro' => $ex->getMessage()])); }
}

/* ---------- Download de relatório (PDF / CSV) ---------- */
if ($p === 'relatorio_export') {
    $f = report_filters($_GET);
    $r = report_build($f);
    $name = 'relatorio-' . $f['kind'] . '-' . $f['from'] . '_' . $f['to'];
    audit('report_export', strtoupper((string) ($_GET['fmt'] ?? 'pdf')) . ' ' . $f['from'] . ' a ' . $f['to']);
    if (($_GET['fmt'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        exit(report_csv($r));
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
    exit(report_pdf($r));
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
