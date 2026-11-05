<?php
declare(strict_types=1);

/* Backups em storage/backups (bloqueada ao acesso direto): banco todo dia e, aos domingos, também os arquivos enviados. */

const BACKUP_KEEP_DB = 14;
const BACKUP_KEEP_FULL = 4;
/** Dados que não valem restaurar (a estrutura da tabela vai no backup). */
const BACKUP_SKIP_DATA = ['login_attempts', 'password_resets', 'chat_presence'];

function backup_dir(): string
{
    $d = APP_ROOT . '/storage/backups';
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) throw new RuntimeException('Não foi possível criar a pasta storage/backups. Verifique as permissões.');
    return $d;
}

/** Backups existentes, do mais novo para o mais antigo. */
function backup_list(): array
{
    $out = [];
    foreach (glob(APP_ROOT . '/storage/backups/bayapp-*.tar.gz') ?: [] as $f) {
        $out[] = ['nome' => basename($f), 'completo' => str_contains(basename($f), '-completo-'), 'tamanho' => (int) filesize($f), 'quando' => (int) filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['quando'] <=> $a['quando'] ?: strcmp($b['nome'], $a['nome']));
    return $out;
}

/** Caminho de um backup pelo nome, sem aceitar nada fora da pasta. */
function backup_path(string $nome): ?string
{
    if (!preg_match('/^bayapp-(banco|completo)-\d{8}-\d{6}-[a-f0-9]{8}\.tar\.gz$/', $nome)) return null;
    $f = APP_ROOT . '/storage/backups/' . $nome;
    return is_file($f) ? $f : null;
}

function backup_sql_value(PDO $pdo, $v): string
{
    if ($v === null) return 'NULL';
    return (is_int($v) || is_float($v)) ? (string) $v : $pdo->quote((string) $v);
}

/** Escreve o dump do banco em um arquivo .sql. */
function backup_dump_sql(string $path): void
{
    $pdo = db();
    $sqlite = (cfg('db')['driver'] ?? 'mysql') === 'sqlite';
    $fh = fopen($path, 'wb');
    if (!$fh) throw new RuntimeException('Não foi possível escrever o backup.');
    fwrite($fh, '-- Backup do BayApp · ' . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, $sqlite ? "PRAGMA foreign_keys = OFF;\n" : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n");
    $tables = $sqlite
        ? array_column(rows("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name')
        : array_map(fn($r) => array_values($r)[0], rows("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
    foreach ($tables as $t) {
        $q = $sqlite ? '"' . $t . '"' : '`' . $t . '`';
        $create = $sqlite ? (string) val('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $t]) : (string) (row("SHOW CREATE TABLE $q")['Create Table'] ?? '');
        fwrite($fh, "\nDROP TABLE IF EXISTS $q;\n$create;\n");
        if (in_array($t, BACKUP_SKIP_DATA, true)) continue;
        $st = $pdo->query("SELECT * FROM $q");
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            fwrite($fh, "INSERT INTO $q (" . implode(',', array_map(fn($c) => $sqlite ? "\"$c\"" : "`$c`", array_keys($r))) . ') VALUES ('
                . implode(',', array_map(fn($v) => backup_sql_value($pdo, $v), array_values($r))) . ");\n");
        }
    }
    if ($sqlite) {
        foreach (rows("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL") as $i) fwrite($fh, $i['sql'] . ";\n");
    } else {
        fwrite($fh, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    }
    fclose($fh);
}

/** Acrescenta um arquivo ao tar.gz aberto (formato ustar). */
function tar_add($gz, string $name, string $path): void
{
    $size = (int) filesize($path);
    $head = str_pad($name, 100, "\0") . sprintf("%07o\0%07o\0%07o\0%011o\0%011o\0", 0640, 0, 0, $size, (int) filemtime($path))
        . '        0' . str_repeat("\0", 100) . "ustar\00000" . str_repeat("\0", 247);
    $sum = array_sum(array_map('ord', str_split($head)));
    $head = substr_replace($head, sprintf("%06o\0 ", $sum), 148, 8);
    gzwrite($gz, $head);
    $in = fopen($path, 'rb');
    if (!$in) throw new RuntimeException("Não foi possível ler $name.");
    while (!feof($in)) { $chunk = fread($in, 1048576); if ($chunk !== false && $chunk !== '') gzwrite($gz, $chunk); }
    fclose($in);
    if ($size % 512) gzwrite($gz, str_repeat("\0", 512 - $size % 512));
}

/** Gera um backup e aplica a retenção. Retorna o nome do arquivo. */
function backup_create(bool $completo): string
{
    @set_time_limit(0);
    $dir = backup_dir();
    $nome = 'bayapp-' . ($completo ? 'completo' : 'banco') . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.tar.gz';
    $sql = "$dir/.tmp-" . bin2hex(random_bytes(6)) . '.sql';
    $txt = "$dir/.tmp-" . bin2hex(random_bytes(6)) . '.txt';
    $part = "$dir/$nome.part";
    try {
        backup_dump_sql($sql);
        file_put_contents($txt, "Backup do BayApp gerado em " . date('d/m/Y H:i') . ".\n\n"
            . "database.sql: importe em um banco MySQL vazio (phpMyAdmin > Importar). Ele recria todas as tabelas.\n"
            . ($completo ? "uploads/: copie o conteudo para storage/uploads do app.\n" : "Este backup traz so o banco. Os arquivos enviados estao no backup completo mais recente.\n")
            . "\nA app_key do config.php NAO faz parte do backup, de proposito: sem ela nao ha como ler as senhas salvas.\nGuarde o config.php em um lugar seguro, separado deste arquivo.\n");
        $gz = gzopen($part, 'wb6');
        if (!$gz) throw new RuntimeException('Não foi possível criar o arquivo de backup.');
        tar_add($gz, 'LEIA-ME.txt', $txt);
        tar_add($gz, 'database.sql', $sql);
        if ($completo) foreach (glob(APP_ROOT . '/storage/uploads/*') ?: [] as $f) if (is_file($f)) tar_add($gz, 'uploads/' . basename($f), $f);
        gzwrite($gz, str_repeat("\0", 1024));
        gzclose($gz);
        if (!rename($part, "$dir/$nome")) throw new RuntimeException('Não foi possível finalizar o backup.');
    } finally {
        foreach ([$sql, $txt, $part] as $f) if (is_file($f)) @unlink($f);
    }
    backup_prune();
    set_setting('backup_ultimo', now());
    set_setting('backup_erro', '');
    return $nome;
}

function backup_prune(): void
{
    $seen = ['banco' => 0, 'completo' => 0];
    foreach (backup_list() as $b) {
        $k = $b['completo'] ? 'completo' : 'banco';
        if (++$seen[$k] > ($b['completo'] ? BACKUP_KEEP_FULL : BACKUP_KEEP_DB)) @unlink(APP_ROOT . '/storage/backups/' . $b['nome']);
    }
}

/** Rotina do cron: no máximo um backup por dia. Em caso de falha, registra e avisa a equipe por e-mail. */
function run_backup(): string
{
    if (substr(setting('backup_ultimo'), 0, 10) === date('Y-m-d')) return 'já feito hoje';
    $temCompleto = (bool) array_filter(backup_list(), fn($b) => $b['completo']);
    try {
        return backup_create(date('w') === '0' || !$temCompleto);
    } catch (Throwable $ex) {
        error_log('[bayapp] backup: ' . $ex);
        set_setting('backup_erro', mb_substr($ex->getMessage(), 0, 240));
        send_mail(alert_recipients(), '⚠️ Backup falhou — ' . setting('empresa_nome', 'BayApp'), mail_layout('O backup automático falhou',
            '<p style="margin:0">Motivo: ' . e($ex->getMessage()) . '</p><p style="margin:10px 0 0">Veja em Configurações → Backups.</p>'));
        return 'falhou: ' . $ex->getMessage();
    }
}
