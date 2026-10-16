<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

/** Errors are written to storage/error.log; visitors only see a short, safe message. */
function app_fail(string $publicMsg, ?Throwable $e = null): void
{
    if ($e) {
        @file_put_contents(APP_ROOT . '/storage/error.log',
            '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n",
            FILE_APPEND);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
       . '<div style="font:16px/1.5 system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 16px;color:#222">'
       . '<h1 style="font-size:22px">Something went wrong</h1><p>' . htmlspecialchars($publicMsg, ENT_QUOTES, 'UTF-8') . '</p></div>';
    exit;
}
set_exception_handler(function (Throwable $e) {
    $msg = 'Please try again in a moment.';
    if ($e instanceof ParseError && str_ends_with($e->getFile(), 'config.php')) {
        $msg = 'config.php has a syntax error (line ' . $e->getLine() . '). Details are in storage/error.log.';
    } elseif ($e instanceof PDOException) {
        $msg = 'Database problem. Details are in storage/error.log on the server.';
    }
    app_fail($msg, $e);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @file_put_contents(APP_ROOT . '/storage/error.log',
            '[' . date('c') . '] FATAL: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . "\n", FILE_APPEND);
    }
});

$cfgFile = APP_ROOT . '/config.php';
if (!is_file($cfgFile)) {
    app_fail('Missing config.php — copy config.sample.php to config.php and fill it in.');
}
$GLOBALS['CFG'] = require $cfgFile;
if (!is_array($GLOBALS['CFG'])) {
    app_fail('config.php must return an array (start from config.sample.php).');
}

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
