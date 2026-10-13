<?php
declare(strict_types=1);
/* Chat entre clientes (portal) e equipe (painel): uma conversa por cliente, atualizada por polling. */

const CHAT_MAX = 2000;

/** Mensagens de clientes ainda não lidas pela equipe (opcionalmente de um cliente). */
function chat_unread_team(?int $clientId = null): int
{
    return (int) ($clientId
        ? val("SELECT COUNT(*) FROM messages WHERE sender = 'client' AND lida_em IS NULL AND client_id = ?", [$clientId])
        : val("SELECT COUNT(*) FROM messages WHERE sender = 'client' AND lida_em IS NULL"));
}

/** Respostas da equipe ainda não lidas pelo cliente. */
function chat_unread_client(int $clientId): int
{
    return (int) val("SELECT COUNT(*) FROM messages WHERE sender = 'team' AND lida_em IS NULL AND client_id = ?", [$clientId]);
}

/** Marca como lidas as mensagens do outro lado. $viewer: 'team' ou 'client'. */
function chat_mark_read(int $clientId, string $viewer): void
{
    $other = $viewer === 'team' ? 'client' : 'team';
    q('UPDATE messages SET lida_em = ? WHERE client_id = ? AND sender = ? AND lida_em IS NULL', [now(), $clientId, $other]);
}

/** Últimas mensagens (ou as novas depois de $after), em ordem cronológica. */
function chat_fetch(int $clientId, int $after = 0, int $limit = 200): array
{
    $rows = $after > 0
        ? rows('SELECT id, sender, autor, corpo, lida_em, criado_em FROM messages WHERE client_id = ? AND id > ? ORDER BY id LIMIT ' . $limit, [$clientId, $after])
        : array_reverse(rows('SELECT id, sender, autor, corpo, lida_em, criado_em FROM messages WHERE client_id = ? ORDER BY id DESC LIMIT ' . $limit, [$clientId]));
    return array_map(fn($m) => ['id' => (int) $m['id']] + $m, $rows);
}

function chat_send(int $clientId, string $sender, string $corpo, array $author): int
{
    $corpo = trim(str_replace("\r\n", "\n", $corpo));
    if ($corpo === '') throw new RuntimeException('Escreva uma mensagem.');
    if (mb_strlen($corpo) > CHAT_MAX) throw new RuntimeException('Mensagem muito longa (máx. ' . CHAT_MAX . ' caracteres).');
    return insert('messages', [
        'client_id' => $clientId, 'sender' => $sender,
        'user_id' => $sender === 'team' ? $author['id'] : null,
        'client_user_id' => $sender === 'client' ? $author['id'] : null,
        'autor' => mb_substr($author['nome'], 0, 120), 'corpo' => $corpo, 'criado_em' => now(),
    ]);
}

/** Lista de conversas para a equipe: mais recente primeiro, com não lidas e prévia. */
function chat_conversations(): array
{
    $list = rows("SELECT c.id, c.nome, c.empresa, MAX(m.id) AS last_id,
                  SUM(CASE WHEN m.sender = 'client' AND m.lida_em IS NULL THEN 1 ELSE 0 END) AS unread
                  FROM clients c JOIN messages m ON m.client_id = c.id GROUP BY c.id, c.nome, c.empresa ORDER BY last_id DESC");
    if (!$list) return [];
    $ids = array_map(fn($r) => (int) $r['last_id'], $list);
    $last = [];
    foreach (rows('SELECT id, sender, corpo, criado_em FROM messages WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $m) $last[(int) $m['id']] = $m;
    return array_map(function ($r) use ($last) {
        $m = $last[(int) $r['last_id']];
        return ['id' => (int) $r['id'], 'nome' => $r['nome'], 'empresa' => $r['empresa'], 'unread' => (int) $r['unread'],
            'preview' => ($m['sender'] === 'team' ? 'Você: ' : '') . mb_substr($m['corpo'], 0, 80), 'quando' => $m['criado_em']];
    }, $list);
}

/**
 * Endpoint JSON do chat. $viewer: 'team' (clientId vem da requisição) ou 'client' (clientId fixo da sessão).
 * GET a=poll&after=N | a=unread | a=list (equipe) · POST a=send&corpo=...
 */
function chat_api(string $viewer, ?int $clientId, array $author): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = static function (array $d, int $code = 200): never { http_response_code($code); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    $unread = static fn(?int $cid) => $viewer === 'team' ? chat_unread_team() : chat_unread_client((int) $cid);
    try {
        $a = (string) ($_GET['a'] ?? $_POST['a'] ?? '');
        if ($a === 'unread') $out(['unread' => $unread($clientId)]);
        if ($a === 'list' && $viewer === 'team') $out(['conversas' => chat_conversations(), 'unread' => chat_unread_team()]);

        if ($viewer === 'team') $clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
        if (!$clientId || !row('SELECT id FROM clients WHERE id = ?', [$clientId])) $out(['erro' => 'Cliente não encontrado.'], 404);

        if ($a === 'send') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) $out(['erro' => 'Sessão expirada. Recarregue a página.'], 403);
            $id = chat_send($clientId, $viewer, (string) ($_POST['corpo'] ?? ''), $author);
            $out(['messages' => chat_fetch($clientId, $id - 1, 1), 'unread' => $unread($clientId)]);
        }
        if ($a === 'poll') {
            chat_mark_read($clientId, $viewer);
            $out(['messages' => chat_fetch($clientId, (int) ($_GET['after'] ?? 0)), 'unread' => $unread($clientId)]);
        }
        $out(['erro' => 'Ação inválida.'], 400);
    } catch (RuntimeException $ex) {
        $out(['erro' => $ex->getMessage()], 422);
    } catch (Throwable $ex) {
        error_log('[bayapp] chat: ' . $ex);
        $out(['erro' => 'Erro inesperado.'], 500);
    }
}
