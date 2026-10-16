<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';

if ((int)q('SELECT COUNT(*) FROM users')->fetchColumn() === 0) redirect('setup.php');
if (current_user()) redirect('index.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
    if ($_SESSION['login_tries'] > 5) sleep(min(10, $_SESSION['login_tries'] - 5));
    $u = q('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim((string)($_POST['email'] ?? '')))])->fetch();
    if ($u && password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['login_tries'] = 0;
        redirect($u['role'] === 'admin' ? 'index.php' : 'checkin.php');
    }
    $err = 'Wrong email or password.';
}
page_header('Log in', true);
?>
<form method="post" class="card form narrow">
  <?= csrf_field() ?>
  <h2>Organiser login</h2>
  <?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
  <label class="field"><span>Email</span><input type="email" name="email" required autofocus></label>
  <label class="field"><span>Password</span><input type="password" name="password" required></label>
  <button class="btn btn-primary" type="submit">Log in</button>
</form>
<?php page_footer(true);
