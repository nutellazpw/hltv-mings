<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();
if ($me['role'] !== 'admin') redirect('checkin.php');

$acts = activities();
$byId = [];
foreach ($acts as $a) $byId[(int)$a['id']] = $a;

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $do = (string)($_POST['do'] ?? '');
    $notify = !empty($_POST['notify']);
    $s = q('SELECT * FROM hm_signups WHERE id = ?', [$id])->fetch();

    if ($s) {
        if ($do === 'accept_primary' || $do === 'accept_secondary' || $do === 'accept_other') {
            $aid = match ($do) {
                'accept_primary' => (int)$s['primary_activity_id'],
                'accept_secondary' => (int)$s['secondary_activity_id'],
                default => (int)($_POST['activity_id'] ?? 0),
            };
            $a = $aid ? activity($aid) : null;
            $alreadyThere = $s['status'] === 'accepted' && (int)$s['activity_id'] === $aid;
            if (!$a) {
                flash('That activity does not exist.', 'err');
            } elseif (!$alreadyThere && !has_room($a)) {
                flash($a['name'] . ' is full (' . capacity_label($a) . '). Raise the limit in Activities or pick another option.', 'err');
            } else {
                $keepGroup = $alreadyThere ? $s['group_id'] : null;
                q('UPDATE hm_signups SET status = \'accepted\', activity_id = ?, group_id = ? WHERE id = ?', [$aid, $keepGroup, $id]);
                $s = q('SELECT * FROM hm_signups WHERE id = ?', [$id])->fetch();
                $sent = $notify ? mail_accepted($s) : null;
                flash(full_name($s) . ' accepted for ' . $a['name'] . ($notify ? ($sent ? ' — email sent.' : ' — EMAIL FAILED, check Settings → Mail log.') : '.'), $sent === false ? 'err' : 'ok');
            }
        } elseif ($do === 'decline') {
            q('UPDATE hm_signups SET status = \'declined\', activity_id = NULL, group_id = NULL, checked_in = 0 WHERE id = ?', [$id]);
            $sent = $notify ? mail_declined($s) : null;
            flash(full_name($s) . ' declined' . ($notify ? ($sent ? ' — email sent.' : ' — EMAIL FAILED.') : '.'), $sent === false ? 'err' : 'ok');
        } elseif ($do === 'reset') {
            q('UPDATE hm_signups SET status = \'pending\', activity_id = NULL, group_id = NULL, checked_in = 0 WHERE id = ?', [$id]);
            flash(full_name($s) . ' moved back to pending (no email sent).');
        } elseif ($do === 'resend') {
            $ok = match ($s['status']) {
                'accepted' => $s['group_id'] ? mail_group($s) : mail_accepted($s),
                'declined' => mail_declined($s),
                default => mail_signup_received($s),
            };
            flash($ok ? 'Email re-sent to ' . $s['email'] . '.' : 'Email failed — check Settings → Mail log.', $ok ? 'ok' : 'err');
        } elseif ($do === 'edit') {
            $fn = trim((string)$_POST['first_name']); $ln = trim((string)$_POST['last_name']);
            $em = mb_strtolower(trim((string)$_POST['email'])); $ph = trim((string)$_POST['phone']);
            $p1 = (int)$_POST['primary'] ?: null; $p2 = (int)$_POST['secondary'] ?: null;
            if ($fn === '' || $ln === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                flash('Name and a valid email are required.', 'err');
            } else {
                try {
                    q('UPDATE hm_signups SET first_name=?, last_name=?, email=?, phone=?, primary_activity_id=?, secondary_activity_id=? WHERE id=?',
                      [$fn, $ln, $em, $ph, $p1, $p2, $id]);
                    flash('Saved.');
                } catch (PDOException $ex) {
                    flash('Another sign-up already uses that email.', 'err');
                }
            }
        } elseif ($do === 'delete') {
            q('DELETE FROM hm_signups WHERE id = ?', [$id]);
            flash(full_name($s) . ' deleted.');
        }
    }
    redirect('index.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'a', 'q']))));
}

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hltv-mingle-signups-' . date('Ymd-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['First name', 'Last name', 'Email', 'Phone', '1st choice', '2nd choice', 'Status', 'Assigned activity', 'Group', 'Checked in', 'Result', 'Signed up']);
    $rows = q('SELECT s.*, g.name AS group_name FROM hm_signups s LEFT JOIN hm_groups g ON g.id = s.group_id ORDER BY s.created_at')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['first_name'], $r['last_name'], $r['email'], $r['phone'],
            $byId[(int)$r['primary_activity_id']]['name'] ?? '', $byId[(int)$r['secondary_activity_id']]['name'] ?? '',
            $r['status'], $byId[(int)$r['activity_id']]['name'] ?? '', $r['group_name'] ?? '',
            $r['checked_in'] ? 'yes' : 'no', $r['result'] ?? '', $r['created_at']]);
    }
    exit;
}

/* ---------- List ---------- */
$fStatus = (string)($_GET['status'] ?? 'pending');
$fAct = (int)($_GET['a'] ?? 0);
$fQ = trim((string)($_GET['q'] ?? ''));

$where = []; $params = [];
if (in_array($fStatus, ['pending', 'accepted', 'declined'], true)) { $where[] = 's.status = ?'; $params[] = $fStatus; }
if ($fAct) { $where[] = '(s.primary_activity_id = ? OR s.secondary_activity_id = ? OR s.activity_id = ?)'; array_push($params, $fAct, $fAct, $fAct); }
if ($fQ !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)'; $like = '%' . $fQ . '%'; array_push($params, $like, $like, $like, $like); }
$rows = q('SELECT s.*, g.name AS group_name FROM hm_signups s LEFT JOIN hm_groups g ON g.id = s.group_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.created_at ASC', $params)->fetchAll();

$counts = ['all' => 0, 'pending' => 0, 'accepted' => 0, 'declined' => 0];
foreach (q('SELECT status, COUNT(*) c FROM hm_signups GROUP BY status')->fetchAll() as $c) { $counts[$c['status']] = (int)$c['c']; $counts['all'] += (int)$c['c']; }

page_header('Sign-ups', true);
?>
<div class="pagehead">
  <h1>Sign-ups</h1>
  <a class="btn" href="?export=1">Export CSV</a>
</div>

<div class="capgrid">
  <?php foreach ($acts as $a): $full = !has_room($a); ?>
  <a class="cap<?= $full ? ' cap-full' : '' ?><?= $fAct === (int)$a['id'] ? ' on' : '' ?>" href="?status=<?= e($fStatus) ?>&a=<?= (int)$a['id'] ?>">
    <span><?= e($a['name']) ?></span><strong><?= e(capacity_label($a)) ?></strong>
    <?php if (!$a['is_open']): ?><small>closed</small><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get">
  <nav class="tabs">
    <?php foreach (['pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined', 'all' => 'All'] as $k => $lbl): ?>
      <a class="<?= $fStatus === $k ? 'on' : '' ?>" href="?<?= e(http_build_query(['status' => $k, 'a' => $fAct ?: null, 'q' => $fQ ?: null])) ?>"><?= $lbl ?> <span><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <input type="hidden" name="status" value="<?= e($fStatus) ?>">
  <select name="a" onchange="this.form.submit()">
    <option value="0">All activities</option>
    <?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $fAct === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
  </select>
  <input type="search" name="q" value="<?= e($fQ) ?>" placeholder="Search name, email, phone">
  <button class="btn" type="submit">Filter</button>
</form>

<?php if (!$rows): ?>
  <div class="card"><p class="muted">No sign-ups here.</p></div>
<?php endif; ?>

<div class="rows">
<?php foreach ($rows as $r):
  $p1 = $byId[(int)$r['primary_activity_id']] ?? null;
  $p2 = $byId[(int)$r['secondary_activity_id']] ?? null;
  $cur = $byId[(int)$r['activity_id']] ?? null; ?>
  <div class="row card">
    <div class="row-main">
      <div class="who">
        <strong><?= e(full_name($r)) ?></strong> <?= status_badge($r['status']) ?>
        <?php if ($r['checked_in']): ?><span class="badge badge-in">Checked in</span><?php endif; ?>
        <div class="small muted"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a> · <a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a> · <?= e(date('d.m. H:i', strtotime($r['created_at']))) ?></div>
      </div>
      <div class="choices small">
        <div><span class="muted">1st</span> <?= e($p1['name'] ?? '—') ?> <?php if ($p1): ?><span class="<?= has_room($p1) ? 'muted' : 'full' ?>">(<?= e(capacity_label($p1)) ?>)</span><?php endif; ?></div>
        <div><span class="muted">2nd</span> <?= e($p2['name'] ?? '—') ?> <?php if ($p2): ?><span class="<?= has_room($p2) ? 'muted' : 'full' ?>">(<?= e(capacity_label($p2)) ?>)</span><?php endif; ?></div>
        <?php if ($r['status'] === 'accepted'): ?><div><span class="muted">In</span> <strong><?= e($cur['name'] ?? '—') ?></strong><?= $r['group_name'] ? ' · ' . e($r['group_name']) : '' ?></div><?php endif; ?>
      </div>
    </div>
    <form method="post" class="row-actions">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <label class="check small"><input type="checkbox" name="notify" value="1" checked> email guest</label>
      <?php $inAct = $r['status'] === 'accepted' ? (int)$r['activity_id'] : 0; ?>
      <?php if ($p1 && $inAct !== (int)$p1['id']): ?><button class="btn btn-ok" name="do" value="accept_primary">Accept 1st</button><?php endif; ?>
      <?php if ($p2 && $inAct !== (int)$p2['id']): ?><button class="btn btn-ok2" name="do" value="accept_secondary">Accept 2nd</button><?php endif; ?>
      <?php if ($r['status'] !== 'declined'): ?><button class="btn btn-danger" name="do" value="decline" data-confirm="Decline <?= e(full_name($r)) ?>?">Decline</button><?php endif; ?>
      <details class="more">
        <summary class="btn">More</summary>
        <div class="more-body">
          <div class="inline">
            <select name="activity_id">
              <?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e(capacity_label($a)) ?>)</option><?php endforeach; ?>
            </select>
            <button class="btn" name="do" value="accept_other">Accept into this</button>
          </div>
          <div class="inline">
            <button class="btn" name="do" value="resend">Re-send last email</button>
            <?php if ($r['status'] !== 'pending'): ?><button class="btn" name="do" value="reset" data-confirm="Move back to pending?">Back to pending</button><?php endif; ?>
            <button class="btn btn-danger" name="do" value="delete" data-confirm="Delete this sign-up permanently?">Delete</button>
          </div>
          <a class="small" href="<?= e(status_link($r)) ?>" target="_blank" rel="noopener">Open guest status page ↗</a>
        </div>
      </details>
    </form>
    <details class="edit">
      <summary class="small">Edit details</summary>
      <form method="post" class="grid2 edit-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="do" value="edit">
        <label class="field"><span>First name</span><input name="first_name" value="<?= e($r['first_name']) ?>"></label>
        <label class="field"><span>Last name</span><input name="last_name" value="<?= e($r['last_name']) ?>"></label>
        <label class="field"><span>Email</span><input name="email" value="<?= e($r['email']) ?>"></label>
        <label class="field"><span>Phone</span><input name="phone" value="<?= e($r['phone']) ?>"></label>
        <label class="field"><span>1st choice</span><select name="primary"><option value="0">—</option><?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$r['primary_activity_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>2nd choice</span><select name="secondary"><option value="0">—</option><?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$r['secondary_activity_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
        <div><button class="btn" type="submit">Save</button></div>
      </form>
    </details>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer(true);
