<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$cfgFile = APP_ROOT . '/config.php';
$CONFIG = is_file($cfgFile) ? (require $cfgFile) : null;
date_default_timezone_set($CONFIG['timezone'] ?? 'America/Sao_Paulo');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/crypto.php';
require __DIR__ . '/security.php';
require __DIR__ . '/qrcode.php';
require __DIR__ . '/backup.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/notify.php';
require __DIR__ . '/chat.php';
require __DIR__ . '/tasks.php';
require __DIR__ . '/plans.php';
require __DIR__ . '/calendar.php';
require __DIR__ . '/files.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/internal.php';
require __DIR__ . '/reports.php';
require __DIR__ . '/workflow.php';

if (PHP_SAPI !== 'cli') web_security($CONFIG);
if ($CONFIG) ensure_schema();
