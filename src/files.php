<?php
declare(strict_types=1);

/* Anexos dos pagamentos (boleto, quitação…). Ficam em storage/uploads (fora do acesso direto) e só saem por download autenticado. */

const UPLOAD_MAX_BYTES = 15 * 1024 * 1024;
const UPLOAD_MAX_FILES = 10;
const UPLOAD_TYPES = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

function upload_dir(): string
{
    $d = APP_ROOT . '/storage/uploads';
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) throw new RuntimeException('Não foi possível criar a pasta de arquivos (storage/uploads). Verifique as permissões.');
    return $d;
}

function fmt_size(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
}

/** Reorganiza $_FILES['arquivos'] (um ou vários) em uma lista simples. */
function uploaded_list(string $field = 'arquivos'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Salva os arquivos enviados. Retorna [quantidade salva, lista de erros]. */
function save_uploads(array $cycle, string $kind, ?int $uid): array
{
    $list = array_values(array_filter(uploaded_list(), fn($f) => ($f['error'] ?? 0) !== UPLOAD_ERR_NO_FILE));
    if (!$list) throw new RuntimeException('Escolha ao menos um arquivo para enviar.');
    if (count($list) > UPLOAD_MAX_FILES) throw new RuntimeException('Envie no máximo ' . UPLOAD_MAX_FILES . ' arquivos por vez.');
    $dir = upload_dir();
    $fi = new finfo(FILEINFO_MIME_TYPE);
    [$ok, $err] = [0, []];
    foreach ($list as $f) {
        $nome = mb_substr(preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '', (string) $f['name']), 0, 200);
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if ($f['error'] !== UPLOAD_ERR_OK) { $err[] = "$nome: " . ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'maior que o limite do servidor' : 'falha no envio'); continue; }
        if (!isset(UPLOAD_TYPES[$ext])) { $err[] = "$nome: tipo não permitido (use PDF, imagem, Word ou Excel)"; continue; }
        if ($f['size'] > UPLOAD_MAX_BYTES) { $err[] = "$nome: maior que " . fmt_size(UPLOAD_MAX_BYTES); continue; }
        if (!is_uploaded_file($f['tmp_name'])) { $err[] = "$nome: envio inválido"; continue; }
        $real = (string) $fi->file($f['tmp_name']);
        if (($ext === 'pdf' && $real !== 'application/pdf') || (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) && strpos($real, 'image/') !== 0)) {
            $err[] = "$nome: o conteúdo não corresponde ao tipo do arquivo"; continue;
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $moved = @move_uploaded_file($f['tmp_name'], "$dir/$stored");
        if (!$moved) { $err[] = "$nome: não foi possível salvar"; continue; }
        insert('files', ['billing_id' => $cycle['billing_id'], 'client_id' => $cycle['client_id'], 'competencia' => $cycle['competencia'], 'kind' => $kind,
            'nome' => $nome, 'arquivo' => $stored, 'mime' => UPLOAD_TYPES[$ext], 'tamanho' => (int) $f['size'], 'user_id' => $uid, 'criado_em' => now()]);
        $ok++;
    }
    return [$ok, $err];
}

/** Apaga do disco os arquivos de uma lista de registros (as linhas somem por cascata ou delete). */
function files_unlink(array $rows): void
{
    foreach ($rows as $r) {
        $path = APP_ROOT . '/storage/uploads/' . basename((string) $r['arquivo']);
        if (is_file($path)) @unlink($path);
    }
}

/** Envia o arquivo ao navegador. PDF/imagem abrem na tela; o restante baixa. */
function serve_file(array $f): never
{
    $path = APP_ROOT . '/storage/uploads/' . basename((string) $f['arquivo']);
    if (!is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
    $ext = strtolower(pathinfo($f['arquivo'], PATHINFO_EXTENSION));
    $inline = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true);
    $safe = preg_replace('/[^\w.\- ()]+/u', '_', (string) $f['nome']);
    header('Content-Type: ' . (UPLOAD_TYPES[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');
    readfile($path);
    exit;
}
