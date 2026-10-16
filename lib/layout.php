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
<title><?= e($title) ?> · HLTV Awards Mingle</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $assets ?>/style.css?v=3">
<link rel="icon" href="<?= $assets ?>/favicon.png">
</head>
<body class="<?= $admin ? 'is-admin' : 'is-public' ?>">
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="<?= $admin ? 'index.php' : './' ?>">
      <img class="brand-logo" src="<?= $assets ?>/logo-hltv-awards.png" alt="HLTV Awards"
           onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
      <span class="brand-fallback">HLTV AWARDS</span>
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
    <span class="foot-by">organised by
      <img src="<?= $assets ?>/logo-relog.png" alt="Relog Media" onerror="this.replaceWith(document.createTextNode('Relog Media'))">
    </span>
  </div>
</footer>
<script src="<?= $assets ?>/app.js?v=3"></script>
</body>
</html><?php
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}
