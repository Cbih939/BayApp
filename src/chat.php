<?php
declare(strict_types=1);
/* Chat entre clientes (portal) e equipe (painel): uma conversa por cliente, atualizada por polling. */

const CHAT_MAX = 2000;
const PRESENCE_TTL = 35;      // segundos sem sinal até virar offline (polling de 5 s na conversa, 15 s no resto do portal)
const ATTEND_WINDOW = 600;    // a equipe só aparece online se respondeu nesta conversa nos últimos 10 min

function presence_cut(): string { return date('Y-m-d H:i:s', time() - PRESENCE_TTL); }

/** Registra sinal de vida ('team' = atendente na conversa do cliente; 'client' = cliente com o portal aberto). */
function chat_touch(int $clientId, string $side, array $who): void
{
    $n = q('UPDATE chat_presence SET seen_at = ?, nome = ? WHERE client_id = ? AND side = ? AND user_id = ?',
        [now(), mb_substr($who['nome'], 0, 120), $clientId, $side, $who['id']])->rowCount();
    if (!$n && !val('SELECT COUNT(*) FROM chat_presence WHERE client_id = ? AND side = ? AND user_id = ?', [$clientId, $side, $who['id']])) {
        insert('chat_presence', ['client_id' => $clientId, 'side' => $side, 'user_id' => $who['id'], 'nome' => mb_substr($who['nome'], 0, 120), 'seen_at' => now()]);
    }
    if (random_int(1, 50) === 1) q('DELETE FROM chat_presence WHERE seen_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

/** O cliente está com o portal aberto agora? (visível só para a equipe) */
function chat_client_online(int $clientId): bool
{
    return (bool) val("SELECT COUNT(*) FROM chat_presence WHERE client_id = ? AND side = 'client' AND seen_at >= ?", [$clientId, presence_cut()]);
}

/** Ids de clientes online agora. */
function chat_online_ids(): array
{
    return array_map('intval', array_column(rows("SELECT DISTINCT client_id FROM chat_presence WHERE side = 'client' AND seen_at >= ?", [presence_cut()]), 'client_id'));
}

/**
 * Nome do atendente que o cliente pode ver online, ou null. Só aparece quem está com a conversa aberta
 * E já respondeu (ou iniciou) nela há pouco: quem apenas abriu a conversa do cliente não aparece.
 */
function chat_attendant(int $clientId): ?string
{
    $n = val("SELECT p.nome FROM chat_presence p WHERE p.client_id = ? AND p.side = 'team' AND p.seen_at >= ?
              AND EXISTS (SELECT 1 FROM messages m WHERE m.client_id = p.client_id AND m.sender = 'team' AND m.user_id = p.user_id AND m.criado_em >= ?)
              ORDER BY p.seen_at DESC LIMIT 1", [$clientId, presence_cut(), date('Y-m-d H:i:s', time() - ATTEND_WINDOW)]);
    return $n === null ? null : (string) $n;
}

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
    $online = chat_online_ids();
    return array_map(function ($r) use ($last, $online) {
        $m = $last[(int) $r['last_id']];
        return ['id' => (int) $r['id'], 'nome' => $r['nome'], 'empresa' => $r['empresa'], 'unread' => (int) $r['unread'], 'online' => in_array((int) $r['id'], $online, true),
            'preview' => ($m['sender'] === 'team' ? 'Você: ' : '') . mb_substr($m['corpo'], 0, 80), 'quando' => $m['criado_em']];
    }, $list);
}

/** Presença que o próprio lado pode ver: a equipe vê o cliente; o cliente só vê o atendente que já respondeu. */
function chat_status(string $viewer, int $clientId): array
{
    return $viewer === 'team' ? ['online' => chat_client_online($clientId)] : ['atendente' => chat_attendant($clientId)];
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
        if ($a === 'unread') {
            if ($viewer === 'client') chat_touch((int) $clientId, 'client', $author);
            $out(['unread' => $unread($clientId)]);
        }
        if ($a === 'list' && $viewer === 'team') $out(['conversas' => chat_conversations(), 'unread' => chat_unread_team()]);

        if ($viewer === 'team') $clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
        if (!$clientId || !row('SELECT id FROM clients WHERE id = ?', [$clientId])) $out(['erro' => 'Cliente não encontrado.'], 404);

        if ($a === 'send') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) $out(['erro' => 'Sessão expirada. Recarregue a página.'], 403);
            $id = chat_send($clientId, $viewer, (string) ($_POST['corpo'] ?? ''), $author);
            chat_touch($clientId, $viewer, $author);
            $out(['messages' => chat_fetch($clientId, $id - 1, 1), 'unread' => $unread($clientId)] + chat_status($viewer, $clientId));
        }
        if ($a === 'poll') {
            chat_touch($clientId, $viewer, $author);
            chat_mark_read($clientId, $viewer);
            $out(['messages' => chat_fetch($clientId, (int) ($_GET['after'] ?? 0)), 'unread' => $unread($clientId)] + chat_status($viewer, $clientId));
        }
        $out(['erro' => 'Ação inválida.'], 400);
    } catch (RuntimeException $ex) {
        $out(['erro' => $ex->getMessage()], 422);
    } catch (Throwable $ex) {
        error_log('[bayapp] chat: ' . $ex);
        $out(['erro' => 'Erro inesperado.'], 500);
    }
}
