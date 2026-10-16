<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$_SESSION = [];
session_destroy();
redirect('login.php');
