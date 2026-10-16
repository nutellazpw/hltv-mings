<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';

// One-time setup: only works while there are no users at all.
if ((int)q('SELECT COUNT(*) FROM hm_users')->fetchColumn() > 0) redirect('login.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
        $err = 'Name, valid email and a password of at least 10 characters are required.';
    } else {
        q('INSERT INTO hm_users (name, email, pass_hash, role) VALUES (?, ?, ?, \'admin\')', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        flash('Main admin created. Welcome!');
        redirect('index.php');
    }
}
page_header('Setup', true);
?>
<form method="post" class="card form narrow">
  <?= csrf_field() ?>
  <h2>Create the first main admin</h2>
  <p class="muted small">This page disappears once an account exists.</p>
  <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
  <label class="field"><span>Name</span><input name="name" required></label>
  <label class="field"><span>Email</span><input type="email" name="email" required></label>
  <label class="field"><span>Password (min. 10 characters)</span><input type="password" name="password" minlength="10" required></label>
  <button class="btn btn-primary" type="submit">Create admin</button>
</form>
<?php page_footer(true);
