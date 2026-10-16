<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'save') {
        set_setting('signups_open', !empty($_POST['signups_open']) ? '1' : '0');
        set_setting('intro_text', trim((string)$_POST['intro_text']));
        flash('Settings saved.');
    } elseif ($do === 'test_mail') {
        $ok = send_mail($me['email'], 'Test email from HLTV Awards Mingle', [
            'Hi ' . e($me['name']) . ',', 'If you can read this, SMTP is configured correctly.',
        ], ['Open the site', url('')]);
        flash($ok ? 'Test email sent to ' . $me['email'] . '.' : 'Test email FAILED — see the mail log below.', $ok ? 'ok' : 'err');
    }
    redirect('settings.php');
}

$log = q('SELECT * FROM hm_mail_log ORDER BY id DESC LIMIT 50')->fetchAll();
$failed = (int)q('SELECT COUNT(*) FROM hm_mail_log WHERE ok = 0')->fetchColumn();

page_header('Settings', true);
?>
<div class="pagehead"><h1>Settings</h1></div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <label class="check"><input type="checkbox" name="signups_open" value="1" <?= setting('signups_open', '1') === '1' ? 'checked' : '' ?>> Sign-ups open</label>
  <label class="field"><span>Intro text on the sign-up page</span><textarea name="intro_text" rows="3"><?= e(setting('intro_text', '')) ?></textarea></label>
  <button class="btn btn-primary">Save</button>
  <p class="small muted">Public sign-up link: <code><?= e(url('')) ?></code></p>
</form>

<div class="card">
  <div class="pagehead">
    <h2>Mail log <small>last 50 · <?= $failed ?> failed total</small></h2>
    <form method="post"><?= csrf_field() ?><button class="btn" name="do" value="test_mail">Send test email to me</button></form>
  </div>
  <p class="small muted">Sending from <code><?= e(cfg('smtp.from')) ?></code> via <code><?= e(cfg('smtp.host')) ?>:<?= (int)cfg('smtp.port') ?></code> (<?= e(cfg('smtp.secure')) ?>), login <code><?= e(cfg('smtp.user')) ?></code>.</p>
  <?php if (cfg('mail_dry_run')): ?><p class="err">Dry-run mode is ON in config.php — emails are only written to storage/mail.log.</p><?php endif; ?>
  <table class="tbl small">
    <thead><tr><th>Time</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr><td><?= e(date('d.m. H:i:s', strtotime($l['created_at']))) ?></td><td><?= e($l['to_email']) ?></td><td><?= e($l['subject']) ?></td>
      <td><?php if ($l['ok']): ?><span class="badge badge-accepted">sent</span><?php else: ?><span class="badge badge-declined">failed</span><details class="errlog"><summary><?= e(mb_substr(strtok((string)$l['error'], "\n"), 0, 90)) ?></summary><pre><?= e($l['error']) ?></pre></details><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php page_footer(true);
