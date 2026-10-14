<?php
declare(strict_types=1);

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45); }

function url(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(['p' => $page] + $params);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/** Aceita apenas destinos internos. */
function safe_return(?string $r): string
{
    return ($r && preg_match('~^index\.php\?p=[a-z_]+[^\r\n]*$~', $r)) ? $r : url('dashboard');
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function pull_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    return is_string($t) && hash_equals(csrf_token(), $t);
}

function post(string $k, string $d = ''): string { return trim((string) ($_POST[$k] ?? $d)); }
function post_or_null(string $k): ?string { $v = post($k); return $v === '' ? null : $v; }

function days_until(string $date): int
{
    return (int) (new DateTime('today'))->diff(new DateTime($date))->format('%r%a');
}

function fmt_date(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fmt_money($v): string { return $v === null || $v === '' ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.'); }
function ago(string $dt): string
{
    $s = time() - strtotime($dt);
    if ($s < 60) return 'agora';
    if ($s < 3600) return floor($s / 60) . ' min atrás';
    if ($s < 86400) return floor($s / 3600) . ' h atrás';
    return floor($s / 86400) . ' d atrás';
}

function add_months(string $date, int $n): string
{
    $d = new DateTime($date);
    $day = (int) $d->format('j');
    $d->modify('first day of this month')->modify(sprintf('%+d month', $n));
    $d->setDate((int) $d->format('Y'), (int) $d->format('n'), min($day, (int) $d->format('t')));
    return $d->format('Y-m-d');
}

function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    return mb_strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
}

function only_digits(?string $v): string { return preg_replace('/\D+/', '', (string) $v); }

/* ---------- Catálogos ---------- */
/** Senha forte sem caracteres ambíguos. */
function gen_password(int $len = 12): string
{
    $a = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return $s;
}

const AUDIT_LABELS = [
    'login' => 'Login (equipe)', 'logout' => 'Saída', 'portal_login' => 'Login (portal)', 'senha_revelada' => 'Senha revelada',
    'user_save' => 'Usuário salvo', 'user_delete' => 'Usuário excluído', 'user_toggle' => 'Usuário ativado/desativado',
    'user_reset' => 'Senha redefinida', 'portal_user_save' => 'Acesso do portal salvo', 'portal_user_delete' => 'Acesso do portal removido',
    'portal_user_toggle' => 'Acesso do portal ativado/desativado', 'portal_user_reset' => 'Senha do portal redefinida',
    'client_save' => 'Cliente salvo', 'client_delete' => 'Cliente excluído', 'cred_save' => 'Acesso salvo', 'cred_delete' => 'Acesso excluído',
    'bill_save' => 'Vencimento salvo', 'bill_pay' => 'Vencimento pago', 'bill_delete' => 'Vencimento excluído',
    'report_export' => 'Relatório baixado', 'report_whatsapp' => 'Relatório enviado ao WhatsApp',
];

const ROLES = ['admin' => 'Administrador', 'designer' => 'Designer', 'dev' => 'Desenvolvedor'];

const CRED_CATS = [
    'instagram' => ['Instagram', 'https://www.instagram.com/accounts/login/'],
    'facebook'  => ['Facebook', 'https://www.facebook.com/login'],
    'tiktok'    => ['TikTok', 'https://www.tiktok.com/login'],
    'linkedin'  => ['LinkedIn', 'https://www.linkedin.com/login'],
    'youtube'   => ['YouTube', 'https://studio.youtube.com'],
    'google'    => ['Google / Gmail', 'https://accounts.google.com'],
    'site'      => ['Site / WordPress', ''],
    'hospedagem' => ['Hospedagem / cPanel', ''],
    'dominio'   => ['Domínio (Registro.br…)', 'https://registro.br'],
    'email'     => ['E-mail corporativo', ''],
    'canva'     => ['Canva', 'https://www.canva.com/login'],
    'outro'     => ['Outro', ''],
];

const BILL_TYPES = [
    'redes_sociais' => 'Gestão de redes sociais', 'campanha' => 'Campanhas / tráfego pago', 'site' => 'Criação de site',
    'design' => 'Design e identidade visual', 'consultoria' => 'Consultoria / mentoria',
    'dominio' => 'Domínio', 'hospedagem' => 'Hospedagem', 'email' => 'E-mail / Workspace',
    'ssl' => 'Certificado SSL', 'ferramenta' => 'Ferramenta / Licença', 'anuncios' => 'Anúncios (verba)', 'outro' => 'Outro',
];

/** Agrupamento do seletor "Tipo de serviço". */
const BILL_GROUPS = [
    'Marketing e criação' => ['redes_sociais', 'campanha', 'anuncios', 'design', 'site', 'consultoria'],
    'Infraestrutura' => ['dominio', 'hospedagem', 'email', 'ssl', 'ferramenta'],
    'Outros' => ['outro'],
];

/** Periodicidade do serviço: rótulo e meses entre cobranças. */
const REC_LABELS = ['nenhuma' => 'Pagamento único', 'mensal' => 'Ao mês', 'trimestral' => 'Ao trimestre', 'semestral' => 'Ao semestre', 'anual' => 'Ao ano'];
const REC_MONTHS = ['mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];

/* Calendário de postagem */
const POST_CHANNELS = ['instagram' => ['Instagram', 'IG'], 'facebook' => ['Facebook', 'FB'], 'tiktok' => ['TikTok', 'TT'],
    'linkedin' => ['LinkedIn', 'IN'], 'youtube' => ['YouTube', 'YT'], 'outro' => ['Outro', '··']];
const POST_FORMATS = ['post' => 'Post (feed)', 'carrossel' => 'Carrossel', 'reels' => 'Reels / vídeo curto', 'stories' => 'Stories', 'video' => 'Vídeo longo'];
const POST_STATUS = ['planejado' => 'Planejado', 'aprovado' => 'Aprovado', 'publicado' => 'Publicado', 'cancelado' => 'Cancelado'];
const MONTHS_PT = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

/** O que o pacote pode incluir por mês (os 5 primeiros são contados pelo calendário). */
const DELIVERY_TYPES = ['post' => 'Posts (feed)', 'carrossel' => 'Carrosséis', 'reels' => 'Reels / vídeos curtos', 'stories' => 'Stories', 'video' => 'Vídeos longos',
    'campanha' => 'Campanhas', 'reuniao' => 'Reuniões', 'relatorio' => 'Relatórios', 'outro' => 'Outro'];

/** Vencimentos internos da Bay Groups (contas que a própria empresa paga). */
const INTERNAL_CATS = ['hospedagem' => 'Hospedagem / servidores', 'dominio' => 'Domínios', 'ferramenta' => 'Ferramentas e licenças', 'anuncios' => 'Anúncios / marketing',
    'imposto' => 'Impostos e taxas', 'aluguel' => 'Aluguel e contas fixas', 'equipe' => 'Equipe / prestadores', 'outro' => 'Outros'];

/** Como o cliente paga: fixo (1x ao ano), variável (mês a mês, valor muda) ou pagamento único. */
const BILL_MODELS = ['fixo' => 'Vencimento fixo (1x ao ano)', 'variavel' => 'Vencimento variável (mês a mês)', 'unico' => 'Pagamento único'];
const BILL_MODEL_SHORT = ['fixo' => 'Fixo · 1x ao ano', 'variavel' => 'Variável · mês a mês', 'unico' => 'Pagamento único'];

/** Arquivos anexados a um pagamento do cliente. */
const FILE_KINDS = ['boleto' => 'Boleto', 'quitacao' => 'Quitação / comprovante', 'outro' => 'Outro arquivo', 'interno' => 'Interno (só a equipe vê)'];

const LINK_TYPES = [
    'drive' => 'Google Drive', 'canva' => 'Canva', 'agenda' => 'Google Agenda',
    'docs' => 'Docs / Planilhas', 'figma' => 'Figma', 'outro' => 'Outro',
];

function guess_link_type(string $url): string
{
    $u = strtolower($url);
    return match (true) {
        str_contains($u, 'drive.google') => 'drive',
        str_contains($u, 'canva.com') => 'canva',
        str_contains($u, 'calendar.google') => 'agenda',
        str_contains($u, 'docs.google') || str_contains($u, 'sheets.google') => 'docs',
        str_contains($u, 'figma.com') => 'figma',
        default => 'outro',
    };
}

/** URL de um asset estático com ?v=mtime, para o navegador/CDN não servir CSS antigo após um deploy. */
function asset(string $path): string
{
    $f = APP_ROOT . '/' . $path;
    return $path . (is_file($f) ? '?v=' . filemtime($f) : '');
}

/** Logo da marca: versão branca (tema escuro) e azul-escuro (tema claro); o CSS alterna conforme o tema. */
function brand_logo(string $alt = 'BayGroups'): string
{
    $a = e($alt);
    return '<img class="logo-img logo-w" width="204" height="30" src="assets/img/logo.png" alt="' . $a . '">'
         . '<img class="logo-img logo-d" width="204" height="30" src="assets/img/logo-dark.png" alt="" aria-hidden="true">';
}

function icon(string $n, int $s = 20): string
{
    static $i = [
        'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M21 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2m-4 4l3 3m-6-1l2 2"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'sparkles' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>',
        'dice' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><circle cx="16" cy="16" r="1"/><circle cx="12" cy="12" r="1"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
        'play' => '<path d="M5 3l14 9-14 9z"/>',
        'tasks' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l3 3 5-6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 9v9M17 5v13"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'package' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'copy2' => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M4 16V5a2 2 0 0 1 2-2h11"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'chevl' => '<path d="M15 18l-6-6 6-6"/>', 'chevr' => '<path d="M9 18l6-6-6-6"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'clip' => '<path d="M21.4 11.1l-9.2 9.2a5.5 5.5 0 0 1-7.8-7.8l9.2-9.2a3.7 3.7 0 0 1 5.2 5.2l-9.2 9.2a1.8 1.8 0 0 1-2.6-2.6l8.5-8.5"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'wallet' => '<path d="M20 12V8a2 2 0 0 0-2-2H5a2 2 0 0 1 0-4h13M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
    ];
    return '<svg class="ico" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($i[$n] ?? '') . '</svg>';
}
