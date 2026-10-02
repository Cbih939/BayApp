<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$cfgFile = APP_ROOT . '/config.php';
$CONFIG = is_file($cfgFile) ? (require $cfgFile) : null;
date_default_timezone_set($CONFIG['timezone'] ?? 'America/Sao_Paulo');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/crypto.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/notify.php';

if ($CONFIG) ensure_schema();
