<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$cfgFile = APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Missing config.php — copy config.sample.php to config.php and fill it in.');
}
$GLOBALS['CFG'] = require $cfgFile;

date_default_timezone_set('Europe/Belgrade');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name('hltvmingle');
session_set_cookie_params(['samesite' => 'Lax', 'path' => '/']);
session_start();

require __DIR__ . '/helpers.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/layout.php';
