<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();

/** Handles the image fields of the activity form. Returns the new value for the image column, or false to keep the old one. */
function posted_image(?string $current)
{
    if (!empty($_POST['image_remove'])) return null;
    $f = $_FILES['image_file'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        if ($f['size'] > 8 * 1024 * 1024) { flash('Image too big (max 8 MB).', 'err'); return false; }
        $info = @getimagesize($f['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext) { flash('Image must be JPG, PNG or WebP.', 'err'); return false; }
        $dir = APP_ROOT . '/public/uploads/activities';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) { flash('Could not save the image — check that public/uploads is writable.', 'err'); return false; }
        return 'uploads/activities/' . $name;
    }
    $url = trim((string)($_POST['image_url'] ?? ''));
    if ($url !== '' && $url !== (string)$current) {
        if (!preg_match('#^https://[^\s"\'<>]+$#i', $url) || strlen($url) > 500) { flash('Image link must be a full https:// address.', 'err'); return false; }
        return $url;
    }
    return false;
}

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
            q('UPDATE hm_activities SET name=?, description=?, details=?, capacity=?, is_open=?, sort_order=? WHERE id=?', [...$data, $id]);
            $old = activity($id);
            $img = posted_image($old['image'] ?? null);
            if ($img !== false) {
                q('UPDATE hm_activities SET image = ? WHERE id = ?', [$img, $id]);
                if (!empty($old['image']) && str_starts_with($old['image'], 'uploads/') && $old['image'] !== $img) @unlink(APP_ROOT . '/public/' . $old['image']);
            }
            $a = activity($id);
            flash('Saved ' . $name . '.' . ((int)$a['capacity'] > 0 && (int)$a['accepted_count'] > (int)$a['capacity'] ? ' Note: more people are already accepted than the new limit.' : ''));
        } else {
            $img = posted_image(null);
            q('INSERT INTO hm_activities (name, description, details, capacity, is_open, sort_order, image) VALUES (?,?,?,?,?,?,?)', [...$data, $img === false ? null : $img]);
            flash('Added ' . $name . '.');
        }
    } elseif ($do === 'delete' && $id) {
        $n = (int)q('SELECT COUNT(*) FROM hm_signups WHERE activity_id = ? AND status = \'accepted\'', [$id])->fetchColumn();
        if ($n > 0) {
            flash('Cannot delete: ' . $n . ' accepted guests are in this activity. Move or decline them first, or just close sign-ups for it.', 'err');
        } else {
            q('DELETE FROM hm_activities WHERE id = ?', [$id]);
            flash('Activity deleted.');
        }
    } elseif ($do === 'publish' && $id) {
        q('UPDATE hm_activities SET results_published = 1 WHERE id = ?', [$id]);
        $sent = 0; $failed = 0;
        if (!empty($_POST['notify'])) {
            @set_time_limit(300);
            foreach (q('SELECT * FROM hm_signups WHERE activity_id = ? AND status = \'accepted\'', [$id])->fetchAll() as $s) {
                mail_results($s) ? $sent++ : $failed++;
            }
        }
        flash('Results published.' . ($sent + $failed ? " Emails sent: $sent" . ($failed ? ", failed: $failed" : '') . '.' : ''), $failed ? 'err' : 'ok');
    } elseif ($do === 'unpublish' && $id) {
        q('UPDATE hm_activities SET results_published = 0 WHERE id = ?', [$id]);
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
<p class="muted small">Limit 0 = unlimited. Image: upload a JPG/PNG/WebP (landscape, ~1200×675 works best) or paste an https:// link. "Description" is shown on the sign-up form; "Details for confirmed guests" (time, place, what to bring) goes into the confirmation and group emails and the guest's status page.</p>
<?php endif; ?>

<?php foreach ($acts as $a): ?>
<div class="card">
  <?php if ($admin): ?>
  <form method="post" class="grid2" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <label class="field"><span>Name</span><input name="name" value="<?= e($a['name']) ?>" required></label>
    <div class="grid2 tight">
      <label class="field"><span>Limit (accepted: <?= (int)$a['accepted_count'] ?>)</span><input type="number" min="0" name="capacity" value="<?= (int)$a['capacity'] ?>"></label>
      <label class="field"><span>Order</span><input type="number" name="sort_order" value="<?= (int)$a['sort_order'] ?>"></label>
    </div>
    <label class="field"><span>Description (public, on sign-up form)</span><textarea name="description" rows="2"><?= e($a['description']) ?></textarea></label>
    <label class="field"><span>Details for confirmed guests</span><textarea name="details" rows="2"><?= e($a['details']) ?></textarea></label>
    <div class="imgfield span2">
      <?php if ($src = activity_image($a, true)): ?><img class="imgprev" src="<?= e($src) ?>" alt=""><?php else: ?><div class="imgprev imgprev-empty">No image</div><?php endif; ?>
      <div class="imgfield-inputs">
        <label class="field"><span>Image — upload new</span><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></label>
        <label class="field"><span>…or image link (https://)</span><input name="image_url" value="<?= e(str_starts_with((string)$a['image'], 'https://') ? $a['image'] : '') ?>" placeholder="https://"></label>
        <?php if ($a['image']): ?><label class="check small"><input type="checkbox" name="image_remove" value="1"> Remove image</label><?php endif; ?>
      </div>
    </div>
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
    <?php if ($src = activity_image($a, true)): ?><img class="imgprev" src="<?= e($src) ?>" alt="" style="margin-bottom:14px"><?php endif; ?>
    <h2><?= e($a['name']) ?> <small><?= e(capacity_label($a)) ?></small></h2>
    <?php if ($a['description']): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
    <?php if ($a['details']): ?><div class="note"><?= nl2br(e($a['details'])) ?></div><?php endif; ?>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($admin): ?>
<div class="card">
  <h2>Add activity</h2>
  <form method="post" class="grid2" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <label class="field"><span>Name</span><input name="name" required></label>
    <div class="grid2 tight">
      <label class="field"><span>Limit</span><input type="number" min="0" name="capacity" value="0"></label>
      <label class="field"><span>Order</span><input type="number" name="sort_order" value="<?= count($acts) + 1 ?>"></label>
    </div>
    <label class="field"><span>Description</span><textarea name="description" rows="2"></textarea></label>
    <label class="field"><span>Details for confirmed guests</span><textarea name="details" rows="2"></textarea></label>
    <label class="field"><span>Image — upload</span><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></label>
    <label class="field"><span>…or image link (https://)</span><input name="image_url" placeholder="https://"></label>
    <div class="inline"><label class="check"><input type="checkbox" name="is_open" value="1" checked> Visible on sign-up form</label><button class="btn btn-primary">Add</button></div>
  </form>
</div>
<?php endif;
page_footer(true);
