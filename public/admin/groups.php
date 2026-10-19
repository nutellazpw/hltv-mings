<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();
$admin = is_admin();

$allowed = allowed_activity_ids();
$acts = array_values(array_filter(activities(), fn($a) => in_array((int)$a['id'], $allowed, true)));
$aid = (int)($_GET['a'] ?? ($acts[0]['id'] ?? 0));
if ($aid) require_activity($aid);
$act = $aid ? activity($aid) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act) {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $gid = (int)($_POST['group_id'] ?? 0);
    // Make sure a posted group belongs to this activity.
    if ($gid && !q('SELECT 1 FROM hm_groups WHERE id = ? AND activity_id = ?', [$gid, $aid])->fetchColumn()) {
        http_response_code(400); exit('Bad group.');
    }

    if ($do === 'add_group' && $admin) {
        $name = trim((string)$_POST['name']);
        if ($name !== '') {
            q('INSERT INTO hm_groups (activity_id, name, info) VALUES (?, ?, ?)', [$aid, $name, trim((string)$_POST['info']) ?: null]);
            flash('Group ' . $name . ' added.');
        }
    } elseif ($do === 'auto_groups' && $admin) {
        $n = max(1, min(50, (int)$_POST['count']));
        $existing = (int)q('SELECT COUNT(*) FROM hm_groups WHERE activity_id = ?', [$aid])->fetchColumn();
        for ($i = 1; $i <= $n; $i++) {
            q('INSERT INTO hm_groups (activity_id, name) VALUES (?, ?)', [$aid, 'Group ' . ($existing + $i)]);
        }
        flash("$n groups added.");
    } elseif ($do === 'save_group' && $admin && $gid) {
        q('UPDATE hm_groups SET name = ?, info = ? WHERE id = ?', [trim((string)$_POST['name']), trim((string)$_POST['info']) ?: null, $gid]);
        flash('Group saved.');
    } elseif ($do === 'delete_group' && $admin && $gid) {
        q('UPDATE hm_signups SET group_id = NULL WHERE group_id = ?', [$gid]);
        q('DELETE FROM hm_groups WHERE id = ?', [$gid]);
        flash('Group deleted; its members are now unassigned.');
    } elseif ($do === 'save_people') {
        $validGroups = array_map('intval', q('SELECT id FROM hm_groups WHERE activity_id = ?', [$aid])->fetchAll(PDO::FETCH_COLUMN));
        foreach ((array)($_POST['people'] ?? []) as $sid => $row) {
            $sid = (int)$sid;
            if (!q('SELECT 1 FROM hm_signups WHERE id = ? AND activity_id = ? AND status = \'accepted\'', [$sid, $aid])->fetchColumn()) continue;
            $result = trim((string)($row['result'] ?? '')) ?: null;
            if ($admin) {
                $g = (int)($row['group'] ?? 0);
                $g = in_array($g, $validGroups, true) ? $g : null;
                q('UPDATE hm_signups SET group_id = ?, result = ? WHERE id = ?', [$g, $result, $sid]);
            } else {
                q('UPDATE hm_signups SET result = ? WHERE id = ?', [$result, $sid]);
            }
        }
        foreach ((array)($_POST['group_result'] ?? []) as $g => $res) {
            if (in_array((int)$g, $validGroups, true)) q('UPDATE hm_groups SET result = ? WHERE id = ?', [trim((string)$res) ?: null, (int)$g]);
        }
        flash('Saved.');
    } elseif ($do === 'notify' && $admin) {
        @set_time_limit(300);
        $sql = 'SELECT * FROM hm_signups WHERE activity_id = ? AND status = \'accepted\' AND group_id IS NOT NULL';
        $params = [$aid];
        if ($gid) { $sql .= ' AND group_id = ?'; $params[] = $gid; }
        $sent = 0; $failed = 0;
        foreach (q($sql, $params)->fetchAll() as $s) mail_group($s) ? $sent++ : $failed++;
        flash("Group emails sent: $sent" . ($failed ? ", failed: $failed (see Settings → Mail log)" : '') . '.', $failed ? 'err' : 'ok');
    }
    redirect('groups.php?a=' . $aid);
}

$groups = $act ? q('SELECT g.*, (SELECT COUNT(*) FROM hm_signups s WHERE s.group_id = g.id AND s.status=\'accepted\') AS n FROM hm_groups g WHERE g.activity_id = ? ORDER BY g.id', [$aid])->fetchAll() : [];
$people = $act ? q('SELECT * FROM hm_signups WHERE activity_id = ? AND status = \'accepted\' ORDER BY group_id IS NULL DESC, group_id, first_name', [$aid])->fetchAll() : [];
$unassigned = count(array_filter($people, fn($p) => !$p['group_id']));

page_header('Groups', true);
?>
<div class="pagehead"><h1>Groups & results</h1></div>
<?php if (!$acts): ?><div class="card"><p class="muted">You are not assigned to any activity yet.</p></div><?php page_footer(true); exit; endif; ?>

<nav class="tabs">
  <?php foreach ($acts as $a): ?><a class="<?= (int)$a['id'] === $aid ? 'on' : '' ?>" href="?a=<?= (int)$a['id'] ?>"><?= e($a['name']) ?> <span><?= (int)$a['accepted_count'] ?></span></a><?php endforeach; ?>
</nav>

<?php if ($admin): ?>
<div class="card">
  <div class="pagehead">
    <h2>Groups in <?= e($act['name']) ?> <small><?= count($people) ?> accepted · <?= $unassigned ?> without group</small></h2>
    <?php if ($groups): ?>
    <form method="post"><?= csrf_field() ?><button class="btn btn-ok" name="do" value="notify" data-confirm="Email every assigned guest in <?= e($act['name']) ?> their group?">Email all groups</button></form>
    <?php endif; ?>
  </div>
  <div class="groupgrid">
    <?php foreach ($groups as $g): ?>
    <form method="post" class="groupcard">
      <?= csrf_field() ?>
      <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
      <input name="name" value="<?= e($g['name']) ?>" class="gname">
      <textarea name="info" rows="2" placeholder="Time slot / meeting point (sent in email)"><?= e($g['info']) ?></textarea>
      <div class="inline small">
        <span><?= (int)$g['n'] ?> people</span>
        <button class="btn" name="do" value="save_group">Save</button>
        <button class="btn" name="do" value="notify" data-confirm="Email members of <?= e($g['name']) ?>?">Email group</button>
        <button class="btn btn-danger" name="do" value="delete_group" data-confirm="Delete <?= e($g['name']) ?>?" title="Delete group">Delete</button>
      </div>
    </form>
    <?php endforeach; ?>
  </div>
  <div class="inline">
    <form method="post" class="inline"><?= csrf_field() ?>
      <input name="name" placeholder="New group name" required>
      <input name="info" placeholder="Time / meeting point (optional)">
      <button class="btn" name="do" value="add_group">Add group</button>
    </form>
    <form method="post" class="inline"><?= csrf_field() ?>
      <input type="number" name="count" min="1" max="50" value="2" style="width:5em">
      <button class="btn" name="do" value="auto_groups">Add numbered groups</button>
    </form>
  </div>
</div>
<?php endif; ?>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="save_people">
  <div class="pagehead">
    <h2><?= $admin ? 'Assign people & enter results' : 'Enter results' ?></h2>
    <button class="btn btn-primary hide-mobile">Save</button>
  </div>
  <?php if (!$people): ?><p class="muted">No accepted guests in this activity yet.</p><?php endif; ?>

  <?php if ($groups): ?>
  <div class="grid2">
    <?php foreach ($groups as $g): ?>
      <label class="field"><span><?= e($g['name']) ?> — group result</span>
        <input name="group_result[<?= (int)$g['id'] ?>]" value="<?= e($g['result']) ?>" placeholder="e.g. 1st place / 12 points"></label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <table class="tbl tbl-cards">
    <thead><tr><th>Name</th><th>Phone / Discord</th><th>Group</th><th>Individual result</th></tr></thead>
    <tbody>
    <?php foreach ($people as $p): ?>
      <tr class="<?= $p['group_id'] ? '' : 'nogroup' ?>">
        <td class="td-name" data-label="Name"><?= e(full_name($p)) ?><?= $p['checked_in'] ? ' <span class="badge badge-in">In</span>' : '' ?></td>
        <td class="small td-phone" data-label="Phone"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>"><?= e($p['phone']) ?></a><?= !empty($p['discord']) ? '<br><span class="discord">' . e($p['discord']) . '</span>' : '' ?></td>
        <td data-label="Group">
          <?php if ($admin): ?>
          <select name="people[<?= (int)$p['id'] ?>][group]">
            <option value="0">— none —</option>
            <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$p['group_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
          </select>
          <?php else:
            $gn = '—'; foreach ($groups as $g) if ((int)$g['id'] === (int)$p['group_id']) $gn = $g['name'];
            echo e($gn);
          endif; ?>
        </td>
        <td data-label="Result"><input name="people[<?= (int)$p['id'] ?>][result]" value="<?= e($p['result']) ?>" placeholder="placement, score, time…"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($people): ?><div class="savebar"><button class="btn btn-primary">Save groups &amp; results</button></div><?php endif; ?>
  <p class="small muted">Results are visible to guests only after a main admin publishes them in Activities.</p>
</form>
<?php page_footer(true);
