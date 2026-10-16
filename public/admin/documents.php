<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_login();
$admin = is_admin();
$dir = APP_ROOT . '/storage/docs';
$allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];

/* ---------- Download ---------- */
if (isset($_GET['dl'])) {
    $d = q('SELECT * FROM documents WHERE id = ?', [(int)$_GET['dl']])->fetch();
    if (!$d || !can_access_activity((int)$d['activity_id'])) { http_response_code(404); exit('Not found.'); }
    $path = $dir . '/' . $d['stored_name'];
    if (!is_file($path)) { http_response_code(404); exit('File missing.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($d['original_name']));
    readfile($path);
    exit;
}

/* ---------- Upload / delete (main admins) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'upload') {
        $aid = (int)($_POST['activity_id'] ?? 0);
        $f = $_FILES['file'] ?? null;
        $ext = $f ? strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) : '';
        if (!activity($aid)) {
            flash('Pick an activity.', 'err');
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('Upload failed (file too big or missing).', 'err');
        } elseif (!in_array($ext, $allowedExt, true)) {
            flash('File type not allowed. Allowed: ' . implode(', ', $allowedExt), 'err');
        } elseif ($f['size'] > 20 * 1024 * 1024) {
            flash('Max 20 MB per file.', 'err');
        } else {
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
                q('INSERT INTO documents (activity_id, original_name, stored_name, uploaded_by) VALUES (?, ?, ?, ?)',
                  [$aid, mb_substr(basename((string)$f['name']), 0, 255), $stored, $me['id']]);
                flash('Uploaded.');
            } else {
                flash('Could not save the file — check storage/docs permissions.', 'err');
            }
        }
    } elseif ($do === 'delete') {
        $d = q('SELECT * FROM documents WHERE id = ?', [(int)$_POST['id']])->fetch();
        if ($d) {
            @unlink($dir . '/' . $d['stored_name']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
            flash('Deleted.');
        }
    }
    redirect('documents.php');
}

$allowed = allowed_activity_ids();
$acts = array_values(array_filter(activities(), fn($a) => in_array((int)$a['id'], $allowed, true)));

page_header('Documents', true);
?>
<div class="pagehead"><h1>Documents</h1></div>

<?php if ($admin): ?>
<form method="post" enctype="multipart/form-data" class="card inline">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="upload">
  <select name="activity_id" required>
    <option value="">Activity…</option>
    <?php foreach ($acts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?>
  </select>
  <input type="file" name="file" required>
  <button class="btn btn-primary">Upload</button>
  <span class="small muted">Coordinators only see documents of their own activity.</span>
</form>
<?php endif; ?>

<?php if (!$acts): ?><div class="card"><p class="muted">You are not assigned to any activity yet.</p></div><?php endif; ?>
<?php foreach ($acts as $a):
  $docs = q('SELECT * FROM documents WHERE activity_id = ? ORDER BY created_at DESC', [$a['id']])->fetchAll(); ?>
<div class="card">
  <h2><?= e($a['name']) ?> <small><?= count($docs) ?> files</small></h2>
  <?php if (!$docs): ?><p class="muted small">No documents yet.</p><?php endif; ?>
  <ul class="doclist">
    <?php foreach ($docs as $d): ?>
    <li>
      <a href="?dl=<?= (int)$d['id'] ?>"><?= e($d['original_name']) ?></a>
      <span class="small muted"><?= e(date('d.m. H:i', strtotime($d['created_at']))) ?></span>
      <?php if ($admin): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <button class="btn btn-danger btn-sm" name="do" value="delete" data-confirm="Delete <?= e($d['original_name']) ?>?">Delete</button></form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach;
page_footer(true);
