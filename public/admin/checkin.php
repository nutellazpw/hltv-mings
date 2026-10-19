<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();

$allowed = allowed_activity_ids();
$acts = array_values(array_filter(activities(), fn($a) => in_array((int)$a['id'], $allowed, true)));
$aid = (int)($_GET['a'] ?? ($acts[0]['id'] ?? 0));
if ($aid) require_activity($aid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sid = (int)($_POST['id'] ?? 0);
    $s = q('SELECT * FROM hm_signups WHERE id = ? AND status = \'accepted\'', [$sid])->fetch();
    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if (!$s || !can_access_activity((int)$s['activity_id'])) {
        if ($ajax) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
        http_response_code(403); exit('No access.');
    }
    $in = !empty($_POST['in']) ? 1 : 0;
    q('UPDATE hm_signups SET checked_in = ?, checked_in_at = ?, checked_in_by = ? WHERE id = ?',
      [$in, $in ? date('Y-m-d H:i:s') : null, $in ? $me['id'] : null, $sid]);
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'in' => $in, 'csrf' => csrf_token()]);
        exit;
    }
    redirect('checkin.php?a=' . (int)$s['activity_id']);
}

$act = $aid ? activity($aid) : null;
$groups = $act ? q('SELECT * FROM hm_groups WHERE activity_id = ? ORDER BY id', [$aid])->fetchAll() : [];
$people = $act ? q('SELECT * FROM hm_signups WHERE activity_id = ? AND status = \'accepted\' ORDER BY first_name, last_name', [$aid])->fetchAll() : [];
$byGroup = [];
foreach ($people as $p) $byGroup[(int)$p['group_id']][] = $p;
$inCount = count(array_filter($people, fn($p) => $p['checked_in']));

page_header('Check-in', true);
?>
<div class="pagehead"><h1>Check-in</h1></div>
<?php if (!$acts): ?><div class="card"><p class="muted">You are not assigned to any activity yet. Ask a main admin.</p></div><?php page_footer(true); exit; endif; ?>

<nav class="tabs">
  <?php foreach ($acts as $a): ?><a class="<?= (int)$a['id'] === $aid ? 'on' : '' ?>" href="?a=<?= (int)$a['id'] ?>"><?= e($a['name']) ?></a><?php endforeach; ?>
</nav>

<div class="card checkhead">
  <div><strong id="incount"><?= $inCount ?></strong> / <?= count($people) ?> checked in</div>
  <input type="search" id="ci-search" placeholder="Search name or phone…" autocomplete="off">
</div>
<?php if ($act && $act['details']): ?><div class="note"><?= nl2br(e($act['details'])) ?></div><?php endif; ?>

<?php
$sections = [];
foreach ($groups as $g) $sections[] = [$g, $byGroup[(int)$g['id']] ?? []];
if (!empty($byGroup[0])) $sections[] = [['name' => 'No group yet', 'info' => null], $byGroup[0]];
foreach ($sections as [$g, $list]): if (!$list) continue; ?>
<div class="card ci-group">
  <h2><?= e($g['name']) ?> <small><?= count($list) ?></small></h2>
  <?php if (!empty($g['info'])): ?><p class="small muted"><?= nl2br(e($g['info'])) ?></p><?php endif; ?>
  <ul class="ci-list">
  <?php foreach ($list as $p): ?>
    <li class="ci<?= $p['checked_in'] ? ' is-in' : '' ?>" data-search="<?= e(mb_strtolower(full_name($p) . ' ' . $p['phone'] . ' ' . $p['email'] . ' ' . ($p['discord'] ?? ''))) ?>">
      <div class="ci-who">
        <strong><?= e(full_name($p)) ?></strong>
        <span class="small muted"><a href="tel:<?= e($p['phone']) ?>"><?= e($p['phone']) ?></a><?= !empty($p['discord']) ? ' · <span class="discord">' . e($p['discord']) . '</span>' : '' ?> · <?= e($p['email']) ?></span>
      </div>
      <form method="post" class="ci-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="in" value="<?= $p['checked_in'] ? '0' : '1' ?>">
        <button class="btn ci-btn"><?= $p['checked_in'] ? '✔ Checked in' : 'Check in' ?></button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>
<?php if (!$people): ?><div class="card"><p class="muted">No accepted guests in this activity yet.</p></div><?php endif;
page_footer(true);
