<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

$t = (string)($_GET['t'] ?? '');
$s = preg_match('/^[a-f0-9]{40}$/', $t) ? q('SELECT * FROM signups WHERE token = ?', [$t])->fetch() : null;

if (!$s) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="card"><h2>Link not valid</h2><p>This status link is not valid. Please use the link from your email.</p></div>';
    page_footer();
    exit;
}

$prim = $s['primary_activity_id'] ? activity((int)$s['primary_activity_id']) : null;
$sec  = $s['secondary_activity_id'] ? activity((int)$s['secondary_activity_id']) : null;
$act  = $s['activity_id'] ? activity((int)$s['activity_id']) : null;
$grp  = $s['group_id'] ? q('SELECT * FROM groups_ WHERE id = ?', [$s['group_id']])->fetch() : null;
$mates = $grp ? q('SELECT first_name, last_name, result FROM signups WHERE group_id = ? AND status = \'accepted\' ORDER BY first_name', [$grp['id']])->fetchAll() : [];
$resultsLive = $act && (int)$act['results_published'] === 1;

page_header('My status');
?>
<section class="hero hero-sm">
  <p class="eyebrow">Your sign-up</p>
  <h1><?= e(full_name($s)) ?></h1>
</section>

<div class="card status-card">
  <div class="status-row">
    <span>Status</span>
    <?php if ($s['status'] === 'pending'): ?>
      <strong class="st st-pending">Waiting for confirmation</strong>
    <?php elseif ($s['status'] === 'accepted'): ?>
      <strong class="st st-accepted">Confirmed</strong>
    <?php else: ?>
      <strong class="st st-declined">Not confirmed</strong>
    <?php endif; ?>
  </div>

  <?php if ($s['status'] === 'pending'): ?>
    <div class="status-row"><span>1st choice</span><strong><?= e($prim['name'] ?? '—') ?></strong></div>
    <div class="status-row"><span>2nd choice</span><strong><?= e($sec['name'] ?? '—') ?></strong></div>
    <p class="muted">The organisers are reviewing sign-ups. You will get an email as soon as your spot is confirmed.</p>
  <?php elseif ($s['status'] === 'declined'): ?>
    <p>Unfortunately both of your chosen activities are fully booked. Thanks for your interest — see you at the HLTV Awards!</p>
  <?php else: ?>
    <div class="status-row"><span>Activity</span><strong><?= e($act['name'] ?? '—') ?></strong></div>
    <?php if (!empty($act['details'])): ?><div class="note"><?= nl2br(e($act['details'])) ?></div><?php endif; ?>
    <div class="status-row"><span>Group</span><strong><?= $grp ? e($grp['name']) : '<span class="muted">Not assigned yet</span>' ?></strong></div>
    <?php if ($grp && $grp['info']): ?><div class="note"><?= nl2br(e($grp['info'])) ?></div><?php endif; ?>
    <?php if ($s['checked_in']): ?><div class="status-row"><span>Check-in</span><strong class="st st-accepted">Checked in</strong></div><?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($grp): ?>
<div class="card">
  <h2><?= e($grp['name']) ?> <small><?= count($mates) ?> people</small></h2>
  <?php if ($resultsLive && $grp['result']): ?>
    <div class="result-box"><span>Group result</span><strong><?= nl2br(e($grp['result'])) ?></strong></div>
  <?php endif; ?>
  <ul class="mates">
    <?php foreach ($mates as $m): ?>
    <li>
      <span><?= e($m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.') ?></span>
      <?php if ($resultsLive && $m['result']): ?><em><?= e($m['result']) ?></em><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($resultsLive): ?>
  <p class="center"><a class="btn" href="results.php?a=<?= (int)$act['id'] ?>">All results for <?= e($act['name']) ?></a></p>
<?php endif;
page_footer();
