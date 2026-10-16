<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($do === 'save') {
        $name = trim((string)$_POST['name']);
        $data = [$name, trim((string)$_POST['description']) ?: null, trim((string)$_POST['details']) ?: null,
                 max(0, (int)$_POST['capacity']), !empty($_POST['is_open']) ? 1 : 0, (int)$_POST['sort_order']];
        if ($name === '') {
            flash('Name is required.', 'err');
        } elseif ($id) {
            q('UPDATE activities SET name=?, description=?, details=?, capacity=?, is_open=?, sort_order=? WHERE id=?', [...$data, $id]);
            $a = activity($id);
            flash('Saved ' . $name . '.' . ((int)$a['capacity'] > 0 && (int)$a['accepted_count'] > (int)$a['capacity'] ? ' Note: more people are already accepted than the new limit.' : ''));
        } else {
            q('INSERT INTO activities (name, description, details, capacity, is_open, sort_order) VALUES (?,?,?,?,?,?)', $data);
            flash('Added ' . $name . '.');
        }
    } elseif ($do === 'delete' && $id) {
        $n = (int)q('SELECT COUNT(*) FROM signups WHERE activity_id = ? AND status = \'accepted\'', [$id])->fetchColumn();
        if ($n > 0) {
            flash('Cannot delete: ' . $n . ' accepted guests are in this activity. Move or decline them first, or just close sign-ups for it.', 'err');
        } else {
            q('DELETE FROM activities WHERE id = ?', [$id]);
            flash('Activity deleted.');
        }
    } elseif ($do === 'publish' && $id) {
        q('UPDATE activities SET results_published = 1 WHERE id = ?', [$id]);
        $sent = 0; $failed = 0;
        if (!empty($_POST['notify'])) {
            @set_time_limit(300);
            foreach (q('SELECT * FROM signups WHERE activity_id = ? AND status = \'accepted\'', [$id])->fetchAll() as $s) {
                mail_results($s) ? $sent++ : $failed++;
            }
        }
        flash('Results published.' . ($sent + $failed ? " Emails sent: $sent" . ($failed ? ", failed: $failed" : '') . '.' : ''), $failed ? 'err' : 'ok');
    } elseif ($do === 'unpublish' && $id) {
        q('UPDATE activities SET results_published = 0 WHERE id = ?', [$id]);
        flash('Results hidden.');
    }
    redirect('activities.php');
}

$allowed = allowed_activity_ids();
$acts = array_values(array_filter(activities(), fn($a) => in_array((int)$a['id'], $allowed, true)));
$admin = is_admin();

page_header('Activities', true);
?>
<div class="pagehead"><h1>Activities</h1></div>
<?php if ($admin): ?>
<p class="muted small">Limit 0 = unlimited. "Description" is shown on the sign-up form; "Details for confirmed guests" (time, place, what to bring) goes into the confirmation and group emails and the guest's status page.</p>
<?php endif; ?>

<?php foreach ($acts as $a): ?>
<div class="card">
  <?php if ($admin): ?>
  <form method="post" class="grid2">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <label class="field"><span>Name</span><input name="name" value="<?= e($a['name']) ?>" required></label>
    <div class="grid2 tight">
      <label class="field"><span>Limit (accepted: <?= (int)$a['accepted_count'] ?>)</span><input type="number" min="0" name="capacity" value="<?= (int)$a['capacity'] ?>"></label>
      <label class="field"><span>Order</span><input type="number" name="sort_order" value="<?= (int)$a['sort_order'] ?>"></label>
    </div>
    <label class="field"><span>Description (public, on sign-up form)</span><textarea name="description" rows="2"><?= e($a['description']) ?></textarea></label>
    <label class="field"><span>Details for confirmed guests</span><textarea name="details" rows="2"><?= e($a['details']) ?></textarea></label>
    <div class="inline">
      <label class="check"><input type="checkbox" name="is_open" value="1" <?= $a['is_open'] ? 'checked' : '' ?>> Visible on sign-up form</label>
      <button class="btn btn-primary" name="do" value="save">Save</button>
      <button class="btn btn-danger" name="do" value="delete" data-confirm="Delete <?= e($a['name']) ?>?">Delete</button>
    </div>
  </form>
  <form method="post" class="inline results-line">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <span class="small">Results: <strong><?= $a['results_published'] ? 'published' : 'hidden' ?></strong></span>
    <?php if ($a['results_published']): ?>
      <button class="btn" name="do" value="unpublish">Hide results</button>
    <?php else: ?>
      <label class="check small"><input type="checkbox" name="notify" value="1" checked> email participants</label>
      <button class="btn btn-ok" name="do" value="publish" data-confirm="Publish results for <?= e($a['name']) ?>?">Publish results</button>
    <?php endif; ?>
    <a class="small" href="groups.php?a=<?= (int)$a['id'] ?>">Groups & results →</a>
  </form>
  <?php else: ?>
    <h2><?= e($a['name']) ?> <small><?= e(capacity_label($a)) ?></small></h2>
    <?php if ($a['description']): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
    <?php if ($a['details']): ?><div class="note"><?= nl2br(e($a['details'])) ?></div><?php endif; ?>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($admin): ?>
<div class="card">
  <h2>Add activity</h2>
  <form method="post" class="grid2">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <label class="field"><span>Name</span><input name="name" required></label>
    <div class="grid2 tight">
      <label class="field"><span>Limit</span><input type="number" min="0" name="capacity" value="0"></label>
      <label class="field"><span>Order</span><input type="number" name="sort_order" value="<?= count($acts) + 1 ?>"></label>
    </div>
    <label class="field"><span>Description</span><textarea name="description" rows="2"></textarea></label>
    <label class="field"><span>Details for confirmed guests</span><textarea name="details" rows="2"></textarea></label>
    <div class="inline"><label class="check"><input type="checkbox" name="is_open" value="1" checked> Visible on sign-up form</label><button class="btn btn-primary">Add</button></div>
  </form>
</div>
<?php endif;
page_footer(true);
