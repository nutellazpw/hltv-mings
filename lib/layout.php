<?php
declare(strict_types=1);

function rlg_logo(): string
{
    static $svg = null;
    return $svg ??= (string)@file_get_contents(__DIR__ . '/rlg-logo.svg');
}

function page_header(string $title, bool $admin = false): void
{
    $assets = $admin ? '../assets' : 'assets';
    $u = $admin ? current_user() : null;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#181818">
<title><?= e($title) ?> · HLTV Awards Mingle</title>
<link rel="preload" href="<?= $assets ?>/fonts/orbitron-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $assets ?>/style.css?v=8">
<link rel="icon" href="<?= $assets ?>/favicon.png">
</head>
<body class="<?= $admin ? 'is-admin' : 'is-public' ?>">
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="<?= $admin ? 'index.php' : './' ?>">
      <img class="brand-logo" src="<?= $assets ?>/logo-hltv-awards.svg" alt="HLTV Awards presented by 1xBet">
      <span class="brand-sub">Mingle</span>
    </a>
    <?php if (!$admin): ?>
    <a class="organiser" href="https://relog.gg" target="_blank" rel="noopener" title="Organised by Relog Media">
      <span>Organised by</span><?= rlg_logo() ?>
    </a>
    <?php endif; ?>
    <?php if ($u):
      $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
      $items = $u['role'] === 'admin'
        ? ['index.php' => 'Sign-ups', 'activities.php' => 'Activities', 'groups.php' => 'Groups', 'checkin.php' => 'Check-in', 'documents.php' => 'Documents', 'users.php' => 'Team', 'settings.php' => 'Settings']
        : ['checkin.php' => 'Check-in', 'groups.php' => 'Results', 'activities.php' => 'Activity', 'documents.php' => 'Documents'];
      $current = $items[$here] ?? 'Menu'; ?>
    <details class="navwrap">
      <summary class="navtoggle" aria-label="Menu"><span class="navtoggle-label"><?= e($current) ?></span><span class="burger" aria-hidden="true"><i></i><i></i><i></i></span></summary>
      <nav class="adminnav">
        <?php foreach ($items as $file => $label): ?>
          <a href="<?= $file ?>"<?= $file === $here ? ' class="on" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="navuser" href="logout.php"><span><?= e($u['name']) ?></span> Log out</a>
      </nav>
    </details>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach;
}

function page_footer(bool $admin = false): void
{
    $assets = $admin ? '../assets' : 'assets';
    ?>
</main>
<footer class="foot">
  <div class="wrap foot-in">
    <span class="foot-event">HLTV Awards Mingle</span>
    <a class="foot-by" href="https://relog.gg" target="_blank" rel="noopener">
      <span>Organised &amp; produced by</span><?= rlg_logo() ?><span class="foot-url">relog.gg</span>
    </a>
  </div>
</footer>
<script src="<?= $assets ?>/app.js?v=5"></script>
</body>
</html><?php
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}
