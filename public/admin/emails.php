<?php
declare(strict_types=1);
require __DIR__ . '/../../lib/bootstrap.php';
$me = require_admin();

$acts = activities();
$sampleAct = null;
foreach ($acts as $a) if (!empty($a['details'])) { $sampleAct = $a; break; }
$sampleAct = $sampleAct ?? ($acts[0] ?? ['id' => 0, 'name' => 'Karting', 'details' => '']);
$second = null;
foreach ($acts as $a) if ((int)$a['id'] !== (int)$sampleAct['id']) { $second = $a; break; }
$sampleGroup = q('SELECT * FROM hm_groups WHERE activity_id = ? ORDER BY id LIMIT 1', [$sampleAct['id']])->fetch()
    ?: ['name' => 'Group A', 'info' => 'Meet at the hotel lobby at 09:30. The bus leaves at 09:45.'];

$sample = ['id' => 0, 'first_name' => 'Alex', 'last_name' => 'Example', 'email' => $me['email'], 'token' => str_repeat('0', 40)];
$ctx = ['activity' => $sampleAct, 'primary' => $sampleAct, 'secondary' => $second, 'group' => $sampleGroup];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $type = (string)($_POST['type'] ?? '');
    if (isset(MAIL_TYPES[$type])) {
        $ok = mail_type($type, $sample, $ctx, $me['email']);
        flash($ok ? 'Sample "' . MAIL_TYPES[$type]['label'] . '" email sent to ' . $me['email'] . '.' : 'Sending failed — see Settings → Mail log.', $ok ? 'ok' : 'err');
    }
    redirect('emails.php#mail-' . $type);
}

$mode = setting('decision_emails', 'immediate');
page_header('Emails', true);
?>
<div class="pagehead"><h1>Emails</h1></div>
<p class="muted small hint">No email is sent when a guest signs up — they see a confirmation on the sign-up page instead. Below is every email guests can receive, shown with sample data (guest "Alex", activity <b><?= e($sampleAct['name']) ?></b>). Texts in the emails come from what you enter in <b>Activities</b> (details for confirmed guests) and <b>Groups</b> (time / meeting point). All emails are sent from <code><?= e(cfg('smtp.from')) ?></code> with a "do not reply" note. Accept / decline emails are currently set to <b><?= $mode === 'batch' ? 'send later, all at once' : 'send immediately' ?></b> — change this in <a href="settings.php">Settings</a>.</p>

<?php $n = 0; foreach (MAIL_TYPES as $type => $info): $n++;
  [$subject, $paras, $button] = mail_build($type, $sample, $ctx);
  $html = mail_template($subject, $paras, $button); ?>
<div class="card acc-card" id="mail-<?= e($type) ?>">
  <details class="acc"<?= $n === 1 ? ' open' : '' ?>>
    <summary class="acc-head">
      <span class="acc-thumb acc-plus"><?= $n ?></span>
      <span class="acc-title"><strong><?= e($info['label']) ?></strong>
        <span class="acc-meta"><span>Subject: <?= e($subject) ?></span></span>
      </span>
      <span class="acc-caret" aria-hidden="true"></span>
    </summary>
    <div class="acc-body">
      <div class="mail-when"><span>When is it sent?</span><p><?= e($info['when']) ?></p></div>
      <iframe class="mail-preview" title="<?= e($info['label']) ?> preview" sandbox="" srcdoc="<?= e($html) ?>" loading="lazy"></iframe>
      <form method="post" class="form-actions">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <button class="btn" type="submit">Send this sample to <?= e($me['email']) ?></button>
      </form>
    </div>
  </details>
</div>
<?php endforeach; ?>
<?php page_footer(true);
