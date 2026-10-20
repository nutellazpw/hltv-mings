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

    if ($do === 'send_decisions') {
        @set_time_limit(300);
        $sent = 0; $failed = 0;
        foreach (unsent_decisions() as $u) {
            $ok = $u['status'] === 'accepted' ? mail_accepted($u) : mail_declined($u);
            $ok ? $sent++ : $failed++;
        }
        flash("Decision emails sent: $sent" . ($failed ? ", failed: $failed (see Settings → Mail log)" : '') . '.', $failed ? 'err' : 'ok');
        redirect('index.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'a', 'q']))));
    }

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
                if (!$notify && !$alreadyThere) q('UPDATE hm_signups SET notified = NULL WHERE id = ?', [$id]);
                $sent = $notify ? mail_accepted($s) : null;
                flash(full_name($s) . ' accepted for ' . $a['name'] . ($notify ? ($sent ? ' — email sent.' : ' — EMAIL FAILED, check Settings → Mail log.') : ' — email not sent yet.'), $sent === false ? 'err' : 'ok');
            }
        } elseif ($do === 'decline') {
            q('UPDATE hm_signups SET status = \'declined\', activity_id = NULL, group_id = NULL, checked_in = 0 WHERE id = ?', [$id]);
            if (!$notify) q('UPDATE hm_signups SET notified = NULL WHERE id = ?', [$id]);
            $sent = $notify ? mail_declined($s) : null;
            flash(full_name($s) . ' declined' . ($notify ? ($sent ? ' — email sent.' : ' — EMAIL FAILED.') : ' — email not sent yet.'), $sent === false ? 'err' : 'ok');
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
            $dcRaw = trim((string)($_POST['discord'] ?? '')); $dc = $dcRaw === '' ? null : clean_discord($dcRaw);
            $co = mb_substr(trim((string)($_POST['company'] ?? '')), 0, 160) ?: null;
            $p1 = (int)$_POST['primary'] ?: null; $p2 = (int)$_POST['secondary'] ?: null;
            if ($fn === '' || $ln === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                flash('Name and a valid email are required.', 'err');
            } else {
                try {
                    q('UPDATE hm_signups SET first_name=?, last_name=?, email=?, phone=?, discord=?, company=?, primary_activity_id=?, secondary_activity_id=? WHERE id=?',
                      [$fn, $ln, $em, $ph, $dc ?? ($dcRaw === '' ? null : $dcRaw), $co, $p1, $p2, $id]);
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
    fputcsv($out, ['Given name', 'Family name', 'Email', 'Phone', 'Discord', 'Company / Position', '1st choice', '2nd choice', 'Status', 'Assigned activity', 'Group', 'Checked in', 'Result', 'Signed up']);
    $rows = q('SELECT s.*, g.name AS group_name FROM hm_signups s LEFT JOIN hm_groups g ON g.id = s.group_id ORDER BY s.created_at')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['discord'] ?? '', $r['company'] ?? '',
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
if ($fQ !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.discord LIKE ? OR s.company LIKE ?)'; $like = '%' . $fQ . '%'; array_push($params, $like, $like, $like, $like, $like, $like); }
$rows = q('SELECT s.*, g.name AS group_name FROM hm_signups s LEFT JOIN hm_groups g ON g.id = s.group_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.created_at ASC', $params)->fetchAll();

$counts = ['all' => 0, 'pending' => 0, 'accepted' => 0, 'declined' => 0];
foreach (q('SELECT status, COUNT(*) c FROM hm_signups GROUP BY status')->fetchAll() as $c) { $counts[$c['status']] = (int)$c['c']; $counts['all'] += (int)$c['c']; }
$checkedIn = (int)q("SELECT COUNT(*) FROM hm_signups WHERE status = 'accepted' AND checked_in = 1")->fetchColumn();
$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['status' => $fStatus, 'a' => $fAct ?: null, 'q' => $fQ ?: null], $over), fn($v) => $v !== null && $v !== ''));

page_header('Sign-ups', true);
?>
<div class="pagehead">
  <h1>Sign-ups</h1>
  <a class="btn" href="?export=1">Export CSV</a>
</div>

<div class="stats">
  <a class="stat<?= $counts['pending'] ? ' stat-hot' : '' ?>" href="<?= e($qs(['status' => 'pending'])) ?>"><strong><?= $counts['pending'] ?></strong><span>To review</span></a>
  <a class="stat" href="<?= e($qs(['status' => 'accepted'])) ?>"><strong><?= $counts['accepted'] ?></strong><span>Accepted</span></a>
  <a class="stat" href="<?= e($qs(['status' => 'declined'])) ?>"><strong><?= $counts['declined'] ?></strong><span>Declined</span></a>
  <a class="stat" href="checkin.php"><strong><?= $checkedIn ?></strong><span>Checked in</span></a>
</div>

<?php $unsent = unsent_decisions(); if ($unsent):
  $nAcc = count(array_filter($unsent, fn($u) => $u['status'] === 'accepted')); $nDec = count($unsent) - $nAcc; ?>
<form method="post" class="card notice-bar">
  <?= csrf_field() ?>
  <div><strong><?= count($unsent) ?> decision<?= count($unsent) === 1 ? '' : 's' ?> not emailed yet</strong>
    <span class="small muted"><?= $nAcc ?> accepted · <?= $nDec ?> declined — guests don't know yet.</span></div>
  <button class="btn btn-primary" name="do" value="send_decisions" data-confirm="Email <?= count($unsent) ?> guests their result now? (<?= $nAcc ?> confirmations, <?= $nDec ?> declines)">Send decision emails</button>
</form>
<?php endif; ?>

<h2 class="section-label">Capacity <small>tap to filter</small></h2>
<div class="capgrid">
  <?php foreach ($acts as $a): $full = !has_room($a);
    $pct = (int)$a['capacity'] > 0 ? min(100, (int)round($a['accepted_count'] / $a['capacity'] * 100)) : 0; ?>
  <a class="cap<?= $full ? ' cap-full' : '' ?><?= $fAct === (int)$a['id'] ? ' on' : '' ?>" href="<?= e($qs(['a' => $fAct === (int)$a['id'] ? null : (int)$a['id']])) ?>">
    <span><?= e($a['name']) ?></span>
    <strong><?= e(capacity_label($a)) ?></strong>
    <i class="capbar"><b style="width:<?= (int)$a['capacity'] > 0 ? $pct : 0 ?>%"></b></i>
    <?php if (!$a['is_open']): ?><small>hidden on form</small><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<nav class="tabs tabs-scroll">
  <?php foreach (['pending' => 'Pending', 'accepted' => 'Accepted', 'declined' => 'Declined', 'all' => 'All'] as $k => $lbl): ?>
    <a class="<?= $fStatus === $k ? 'on' : '' ?>" href="<?= e($qs(['status' => $k])) ?>"><?= $lbl ?> <span><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
</nav>
<form class="filters" method="get">
  <input type="hidden" name="status" value="<?= e($fStatus) ?>">
  <select name="a" onchange="this.form.submit()" aria-label="Activity">
    <option value="0">All activities</option>
    <?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $fAct === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?>
  </select>
  <input type="search" name="q" value="<?= e($fQ) ?>" placeholder="Search name, email, phone, Discord, company" aria-label="Search">
  <button class="btn" type="submit">Search</button>
  <?php if ($fAct || $fQ !== ''): ?><a class="btn btn-ghost" href="<?= e('?status=' . $fStatus) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="card empty"><p class="muted">No sign-ups here<?= $fStatus === 'pending' ? ' — nothing waiting for review.' : '.' ?></p></div>
<?php endif; ?>

<div class="rows">
<?php foreach ($rows as $r):
  $p1 = $byId[(int)$r['primary_activity_id']] ?? null;
  $p2 = $byId[(int)$r['secondary_activity_id']] ?? null;
  $cur = $byId[(int)$r['activity_id']] ?? null;
  $inAct = $r['status'] === 'accepted' ? (int)$r['activity_id'] : 0; ?>
  <article class="row card row-<?= e($r['status']) ?>">
    <header class="row-head">
      <div class="who">
        <strong><?= e(full_name($r)) ?></strong>
        <?= status_badge($r['status']) ?>
        <?php if ($r['checked_in']): ?><span class="badge badge-in">Checked in</span><?php endif; ?>
        <?php if (in_array($r['status'], ['accepted', 'declined'], true) && $r['notified'] !== $r['status']): ?><span class="badge badge-pending" title="The guest has not been emailed this decision yet">Not emailed</span><?php endif; ?>
      </div>
      <time class="small muted"><?= e(date('d.m. H:i', strtotime($r['created_at']))) ?></time>
    </header>
    <div class="contact">
      <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a>
      <?php if (!empty($r['discord'])): ?><span class="discord" title="Discord"><?= e($r['discord']) ?></span><?php endif; ?>
    </div>
    <?php if (!empty($r['company'])): ?><div class="company small"><?= e($r['company']) ?></div><?php endif; ?>
    <div class="chips">
      <span class="chip<?= $inAct && $p1 && $inAct === (int)$p1['id'] ? ' chip-in' : '' ?>"><em>1st</em><?= e($p1['name'] ?? '—') ?><?php if ($p1): ?> <b class="<?= has_room($p1) ? '' : 'full' ?>"><?= e(capacity_label($p1)) ?></b><?php endif; ?></span>
      <?php if ($p2): ?><span class="chip<?= $inAct === (int)$p2['id'] ? ' chip-in' : '' ?>"><em>2nd</em><?= e($p2['name']) ?> <b class="<?= has_room($p2) ? '' : 'full' ?>"><?= e(capacity_label($p2)) ?></b></span><?php endif; ?>
      <?php if ($inAct && (!$p1 || $inAct !== (int)$p1['id']) && (!$p2 || $inAct !== (int)$p2['id'])): ?><span class="chip chip-in"><em>In</em><?= e($cur['name'] ?? '—') ?></span><?php endif; ?>
      <?php if ($r['group_name']): ?><span class="chip"><em>Group</em><?= e($r['group_name']) ?></span><?php endif; ?>
    </div>
    <form method="post" class="row-actions">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div class="row-buttons">
        <?php if ($p1 && $inAct !== (int)$p1['id']): ?><button class="btn btn-ok" name="do" value="accept_primary"<?= has_room($p1) ? '' : ' disabled title="Full"' ?>>Accept 1st</button><?php endif; ?>
        <?php if ($p2 && $inAct !== (int)$p2['id']): ?><button class="btn btn-ok2" name="do" value="accept_secondary"<?= has_room($p2) ? '' : ' disabled title="Full"' ?>>Accept 2nd</button><?php endif; ?>
        <?php if ($r['status'] !== 'declined'): ?><button class="btn btn-danger" name="do" value="decline" data-confirm="Decline <?= e(full_name($r)) ?>?">Decline</button><?php endif; ?>
      </div>
      <label class="check small notify"><input type="checkbox" name="notify" value="1"<?= setting('decision_emails', 'immediate') === 'immediate' ? ' checked' : '' ?>> Email guest now</label>
    </form>
    <details class="more">
      <summary>More options</summary>
      <div class="more-body">
        <form method="post" class="more-section">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="notify" value="1">
          <label class="field"><span>Accept into another activity (emails guest)</span>
            <span class="inline">
              <select name="activity_id">
                <?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?> (<?= e(capacity_label($a)) ?>)</option><?php endforeach; ?>
              </select>
              <button class="btn" name="do" value="accept_other">Accept</button>
            </span>
          </label>
          <div class="inline">
            <button class="btn" name="do" value="resend">Re-send last email</button>
            <?php if ($r['status'] !== 'pending'): ?><button class="btn" name="do" value="reset" data-confirm="Move back to pending? (no email is sent)">Back to pending</button><?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(status_link($r)) ?>" target="_blank" rel="noopener">Guest page ↗</a>
          </div>
        </form>
        <form method="post" class="grid2 more-section">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="do" value="edit">
          <label class="field"><span>Given name</span><input name="first_name" value="<?= e($r['first_name']) ?>"></label>
          <label class="field"><span>Family name</span><input name="last_name" value="<?= e($r['last_name']) ?>"></label>
          <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($r['email']) ?>"></label>
          <label class="field"><span>Phone</span><input type="tel" name="phone" value="<?= e($r['phone']) ?>"></label>
          <label class="field"><span>Discord</span><input name="discord" value="<?= e($r['discord'] ?? '') ?>" autocapitalize="off" spellcheck="false"></label>
          <label class="field"><span>Company / Position</span><input name="company" value="<?= e($r['company'] ?? '') ?>" maxlength="160"></label>
          <label class="field"><span>1st choice</span><select name="primary"><option value="0">—</option><?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$r['primary_activity_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
          <label class="field"><span>2nd choice</span><select name="secondary"><option value="0">—</option><?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$r['secondary_activity_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
          <div class="inline span2">
            <button class="btn btn-primary" type="submit">Save details</button>
            <button class="btn btn-danger" name="do" value="delete" formnovalidate data-confirm="Delete this sign-up permanently?">Delete sign-up</button>
          </div>
        </form>
      </div>
    </details>
  </article>
<?php endforeach; ?>
</div>
<?php page_footer(true);
