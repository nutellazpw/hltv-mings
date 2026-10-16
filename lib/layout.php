<?php
declare(strict_types=1);

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
<link rel="stylesheet" href="<?= $assets ?>/style.css?v=4">
<link rel="icon" href="<?= $assets ?>/favicon.png">
</head>
<body class="<?= $admin ? 'is-admin' : 'is-public' ?>">
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="<?= $admin ? 'index.php' : './' ?>">
      <img class="brand-logo" src="<?= $assets ?>/logo-hltv-awards.svg" alt="HLTV Awards presented by 1xBet">
      <span class="brand-sub">Mingle</span>
    </a>
    <?php if ($u): ?>
    <nav class="adminnav">
      <a href="index.php">Sign-ups</a>
      <a href="activities.php">Activities</a>
      <a href="groups.php">Groups</a>
      <a href="checkin.php">Check-in</a>
      <a href="documents.php">Documents</a>
      <?php if ($u['role'] === 'admin'): ?>
      <a href="users.php">Team</a>
      <a href="settings.php">Settings</a>
      <?php endif; ?>
      <a class="muted" href="logout.php">Log out (<?= e($u['name']) ?>)</a>
    </nav>
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
    <span>HLTV Awards Mingle</span>
    <span class="foot-by">Organised by <strong>Relog Media</strong></span>
  </div>
</footer>
<script src="<?= $assets ?>/app.js?v=4"></script>
</body>
</html><?php
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}
