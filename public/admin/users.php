<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_admin();
$acts = activities();

function save_assignments(int $uid, array $ids): void
{
    q('DELETE FROM hm_user_activity WHERE user_id = ?', [$uid]);
    foreach (array_unique(array_map('intval', $ids)) as $aid) {
        if (activity($aid)) q('INSERT INTO hm_user_activity (user_id, activity_id) VALUES (?, ?)', [$uid, $aid]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    $uid = (int)($_POST['id'] ?? 0);
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'coordinator';
    $adminCount = (int)q('SELECT COUNT(*) FROM hm_users WHERE role = \'admin\'')->fetchColumn();

    if ($do === 'create') {
        $name = trim((string)$_POST['name']);
        $email = mb_strtolower(trim((string)$_POST['email']));
        $pass = (string)$_POST['password'];
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('Name, valid email and a password of at least 8 characters are required.', 'err');
        } else {
            try {
                q('INSERT INTO hm_users (name, email, pass_hash, role) VALUES (?, ?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
                save_assignments((int)db()->lastInsertId(), (array)($_POST['acts'] ?? []));
                flash("Account for $name created. Send them the login link and password.");
            } catch (PDOException $ex) {
                flash('That email already has an account.', 'err');
            }
        }
    } elseif ($do === 'update' && $uid) {
        $u = q('SELECT * FROM hm_users WHERE id = ?', [$uid])->fetch();
        if ($u && $u['role'] === 'admin' && $role !== 'admin' && $adminCount <= 1) {
            flash('There must be at least one main admin.', 'err');
        } elseif ($u) {
            q('UPDATE hm_users SET name = ?, role = ? WHERE id = ?', [trim((string)$_POST['name']) ?: $u['name'], $role, $uid]);
            save_assignments($uid, (array)($_POST['acts'] ?? []));
            if (($p = (string)($_POST['password'] ?? '')) !== '') {
                if (strlen($p) < 8) flash('Password not changed: at least 8 characters.', 'err');
                else q('UPDATE hm_users SET pass_hash = ? WHERE id = ?', [password_hash($p, PASSWORD_DEFAULT), $uid]);
            }
            flash('Saved ' . $u['name'] . '.');
        }
    } elseif ($do === 'delete' && $uid) {
        if ($uid === (int)$me['id']) flash('You cannot delete your own account.', 'err');
        else { q('DELETE FROM hm_users WHERE id = ?', [$uid]); flash('Account deleted.'); }
    }
    redirect('users.php');
}

$users = q('SELECT * FROM hm_users ORDER BY role, name')->fetchAll();
$assign = [];
foreach (q('SELECT * FROM hm_user_activity')->fetchAll() as $r) $assign[(int)$r['user_id']][] = (int)$r['activity_id'];

page_header('Team', true);
?>
<div class="pagehead"><h1>Team</h1></div>
<p class="muted small hint"><strong>Main admin</strong> — everything: sign-ups, activities, limits, groups, emails, team. <strong>Coordinator</strong> — only the activities ticked below: see guest details, check people in, read documents, enter results. Cannot change activities or accept/decline. Login page: <code><?= e(url('admin/')) ?></code></p>

<?php
$actNames = [];
foreach ($acts as $a) $actNames[(int)$a['id']] = $a['name'];
foreach ($users as $u):
  $mine = array_map(fn($id) => $actNames[$id] ?? '', $assign[(int)$u['id']] ?? []); ?>
<div class="card acc-card">
<details class="acc">
  <summary class="acc-head">
    <span class="acc-thumb acc-avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
    <span class="acc-title"><strong><?= e($u['name']) ?></strong>
      <span class="acc-meta">
        <span class="badge <?= $u['role'] === 'admin' ? 'badge-accepted' : 'badge-pending' ?>"><?= $u['role'] === 'admin' ? 'Main admin' : 'Coordinator' ?></span>
        <span><?= e($u['email']) ?></span>
        <?php if ($u['role'] !== 'admin'): ?><span class="muted"><?= $mine ? e(implode(', ', $mine)) : 'no activities yet' ?></span><?php endif; ?>
      </span>
    </span>
    <span class="acc-caret" aria-hidden="true"></span>
  </summary>
  <div class="acc-body">
  <form method="post" class="grid2">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
    <label class="field"><span>Name</span><input name="name" value="<?= e($u['name']) ?>"></label>
    <label class="field"><span>Role</span>
      <select name="role"><option value="coordinator">Coordinator</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Main admin</option></select></label>
    <fieldset class="field actchecks span2"><span>Activities (for coordinators)</span>
      <?php foreach ($acts as $a): ?>
        <label class="check small"><input type="checkbox" name="acts[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'], $assign[(int)$u['id']] ?? [], true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="field"><span>New password (leave empty to keep)</span><input type="password" name="password" autocomplete="new-password"></label>
    <div class="form-actions span2">
      <button class="btn btn-primary" name="do" value="update">Save changes</button>
      <?php if ((int)$u['id'] !== (int)$me['id']): ?><button class="btn btn-danger" name="do" value="delete" data-confirm="Delete <?= e($u['name']) ?>?">Delete account</button><?php endif; ?>
    </div>
  </form>
  </div>
</details>
</div>
<?php endforeach; ?>

<div class="card acc-card">
<details class="acc"<?= count($users) <= 1 ? ' open' : '' ?>>
  <summary class="acc-head"><span class="acc-thumb acc-plus">+</span><span class="acc-title"><strong>Add team member</strong><span class="acc-meta"><span>Main admin or activity coordinator</span></span></span><span class="acc-caret" aria-hidden="true"></span></summary>
  <div class="acc-body">
  <form method="post" class="grid2">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <label class="field"><span>Name</span><input name="name" required></label>
    <label class="field"><span>Email (login)</span><input type="email" name="email" required autocomplete="off"></label>
    <label class="field"><span>Password (min. 8)</span><input type="text" name="password" minlength="8" required autocomplete="off"></label>
    <label class="field"><span>Role</span><select name="role"><option value="coordinator">Coordinator</option><option value="admin">Main admin</option></select></label>
    <fieldset class="field actchecks span2"><span>Activities</span>
      <?php foreach ($acts as $a): ?><label class="check small"><input type="checkbox" name="acts[]" value="<?= (int)$a['id'] ?>"> <?= e($a['name']) ?></label><?php endforeach; ?>
    </fieldset>
    <div class="form-actions span2"><button class="btn btn-primary">Create account</button></div>
  </form>
  </div>
</details>
</div>
<?php page_footer(true);
