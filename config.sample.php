<?php
// Copy to config.php (same folder) and fill in. config.php is never committed.
return [
    'base_url' => 'https://hltv.relog.gg',   // no trailing slash

    'db' => [
        'host' => 'localhost',
        'name' => 'CPANELUSER_hltvmingle',
        'user' => 'CPANELUSER_hltvmingle',
        'pass' => '',
    ],

    'smtp' => [
        'host'      => 'mail.relog.gg',
        'port'      => 465,
        'secure'    => 'ssl',               // 'ssl' (465) or 'tls' (587)
        'user'      => 'hltv-mingle@relog.gg',
        'pass'      => '',
        'from'      => 'hltv-mingle@relog.gg',
        'from_name' => 'HLTV Awards Mingle',
    ],

    // Set to true only while testing: emails are written to storage/mail.log instead of being sent.
    'mail_dry_run' => false,
];
