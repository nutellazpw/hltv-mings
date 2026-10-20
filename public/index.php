<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

$open = setting('signups_open', '1') === '1';
$acts = activities(true);
$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'discord' => '', 'company' => '', 'primary' => '', 'secondary' => ''];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));

    // Honeypot: bots fill every field.
    if (!empty($_POST['website'])) { $done = true; }
    else {
        if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 80) $errors['first_name'] = 'Please enter your given name.';
        if ($old['last_name'] === '' || mb_strlen($old['last_name']) > 80) $errors['last_name'] = 'Please enter your family name.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) $errors['email'] = 'Please enter a valid email address.';
        if (!preg_match('/^\+?[0-9 ()\-\/]{6,30}$/', $old['phone'])) $errors['phone'] = 'Please enter a valid phone number, including country code.';
        $discord = clean_discord($old['discord']);
        if ($discord === null) $errors['discord'] = 'Please enter your Discord username (e.g. zywoo or name#1234).';
        if ($old['company'] === '' || mb_strlen($old['company']) > 160) $errors['company'] = 'Please enter your company and position.';

        $openIds = array_map(fn($a) => (string)$a['id'], $acts);
        if (!in_array($old['primary'], $openIds, true)) $errors['primary'] = 'Please choose your first-choice activity.';
        if ($old['secondary'] !== '' && (!in_array($old['secondary'], $openIds, true) || $old['secondary'] === $old['primary'])) {
            $errors['secondary'] = 'Your second choice must be a different activity.';
        }
        if (empty($_POST['consent'])) $errors['consent'] = 'Please confirm you agree to us storing your details for this event.';

        if (!$errors) {
            $email = mb_strtolower($old['email']);
            $existing = q('SELECT * FROM hm_signups WHERE email = ?', [$email])->fetch();
            if ($existing) {
                // No email is sent at sign-up; tell them on screen instead.
                $errors['email'] = 'This email address is already signed up. If you need to change your choices, please contact the organisers.';
            } else {
                $token = bin2hex(random_bytes(20));
                q('INSERT INTO hm_signups (first_name, last_name, email, phone, discord, company, primary_activity_id, secondary_activity_id, token)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $old['first_name'], $old['last_name'], $email, $old['phone'], $discord, $old['company'],
                    (int)$old['primary'], $old['secondary'] === '' ? null : (int)$old['secondary'], $token,
                ]);
                // Post/Redirect/Get: a refresh can't submit twice, and the confirmation survives the redirect.
                $_SESSION['signup_done'] = (int)db()->lastInsertId();
                redirect('./?thanks=1#signup-done');
            }
        }
    }
}

$doneSignup = null;
if (isset($_GET['thanks']) && !empty($_SESSION['signup_done'])) {
    $doneSignup = q('SELECT * FROM hm_signups WHERE id = ?', [(int)$_SESSION['signup_done']])->fetch() ?: null;
}

page_header('Sign up');
?>
<section class="hero">
  <img class="hero-logo" src="assets/logo-hltv-awards-vertical.svg" alt="HLTV Awards by 1xBet">
  <p class="eyebrow">Side activities</p>
  <h1>Mingle Activities</h1>
  <div class="intro">
    <?php foreach (preg_split('/\R\s*\R/', trim((string)setting('intro_text', ''))) as $para): if (trim($para) === '') continue; ?>
      <p><?= nl2br(e(trim($para))) ?></p>
    <?php endforeach; ?>
  </div>
  <hr class="rule">
</section>

<?php if ($done): ?>
  <div class="card card-success" id="signup-done"><h2>Thank you</h2><p>Your sign-up has been received.</p></div>
<?php elseif ($doneSignup):
  $p1 = $doneSignup['primary_activity_id'] ? activity((int)$doneSignup['primary_activity_id']) : null;
  $p2 = $doneSignup['secondary_activity_id'] ? activity((int)$doneSignup['secondary_activity_id']) : null; ?>
  <div class="card card-success done-card" id="signup-done">
    <p class="done-check" aria-hidden="true">&#10003;</p>
    <h2>You're signed up, <?= e($doneSignup['first_name']) ?>!</h2>
    <p>Thank you — your sign-up for HLTV Awards Mingle activities is complete.</p>
    <div class="done-choices">
      <div><span>1st choice</span><strong><?= e($p1['name'] ?? '—') ?></strong></div>
      <div><span>2nd choice</span><strong><?= e($p2['name'] ?? 'None') ?></strong></div>
    </div>
    <p>Spots are limited, so every sign-up is reviewed by the organisers. We will email you at <strong><?= e($doneSignup['email']) ?></strong> to let you know whether you have secured a spot.</p>
    <p class="small muted">You can check your status at any time on your personal page — bookmark it if you like.</p>
    <p><a class="btn" href="<?= e(status_link($doneSignup)) ?>">View my status</a></p>
  </div>
<?php elseif (!$open): ?>
  <div class="card"><h2>Sign-ups are closed</h2><p>Thanks for your interest. If you already signed up, we will email you to let you know whether you have secured a spot.</p></div>
<?php else: ?>
<form method="post" class="card form" novalidate>
  <?= csrf_field() ?>
  <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

  <h2>Your details</h2>
  <div class="grid2">
    <label class="field<?= isset($errors['first_name']) ? ' has-err' : '' ?>">
      <span>Given name</span>
      <input name="first_name" value="<?= e($old['first_name']) ?>" autocomplete="given-name" required>
      <?php if (isset($errors['first_name'])): ?><em><?= e($errors['first_name']) ?></em><?php endif; ?>
    </label>
    <label class="field<?= isset($errors['last_name']) ? ' has-err' : '' ?>">
      <span>Family name</span>
      <input name="last_name" value="<?= e($old['last_name']) ?>" autocomplete="family-name" required>
      <?php if (isset($errors['last_name'])): ?><em><?= e($errors['last_name']) ?></em><?php endif; ?>
    </label>
    <label class="field<?= isset($errors['email']) ? ' has-err' : '' ?>">
      <span>Email</span>
      <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
      <?php if (isset($errors['email'])): ?><em><?= e($errors['email']) ?></em><?php endif; ?>
    </label>
    <label class="field<?= isset($errors['phone']) ? ' has-err' : '' ?>">
      <span>Phone</span>
      <input type="tel" name="phone" value="<?= e($old['phone']) ?>" placeholder="+381 60 123 4567" autocomplete="tel" required>
      <?php if (isset($errors['phone'])): ?><em><?= e($errors['phone']) ?></em><?php endif; ?>
    </label>
    <label class="field<?= isset($errors['discord']) ? ' has-err' : '' ?>">
      <span>Discord username</span>
      <input name="discord" value="<?= e($old['discord']) ?>" placeholder="e.g. zywoo" autocomplete="off" autocapitalize="off" spellcheck="false" required>
      <?php if (isset($errors['discord'])): ?><em><?= e($errors['discord']) ?></em><?php endif; ?>
    </label>
    <label class="field<?= isset($errors['company']) ? ' has-err' : '' ?>">
      <span>Company / Position</span>
      <input name="company" value="<?= e($old['company']) ?>" placeholder="e.g. Team Vitality – Content Manager" autocomplete="organization-title" maxlength="160" required>
      <?php if (isset($errors['company'])): ?><em><?= e($errors['company']) ?></em><?php endif; ?>
    </label>
  </div>
  <p class="small muted req-note">All fields are required.</p>

  <h2>1st choice <small>required</small></h2>
  <?php if (isset($errors['primary'])): ?><p class="err"><?= e($errors['primary']) ?></p><?php endif; ?>
  <div class="acts" data-group="primary">
    <?php foreach ($acts as $a): ?>
    <label class="act<?= activity_image($a) ? ' has-img' : '' ?>">
      <input type="radio" name="primary" value="<?= (int)$a['id'] ?>" <?= $old['primary'] === (string)$a['id'] ? 'checked' : '' ?>>
      <span class="act-body">
        <?php if ($src = activity_image($a)): ?><span class="act-img" style="background-image:url('<?= e($src) ?>')"></span><?php endif; ?>
        <strong><?= e($a['name']) ?></strong>
        <?php if ($a['description']): ?><small><?= e($a['description']) ?></small><?php endif; ?>
      </span>
    </label>
    <?php endforeach; ?>
  </div>

  <h2>2nd choice <small>backup, if your 1st choice is full</small></h2>
  <?php if (isset($errors['secondary'])): ?><p class="err"><?= e($errors['secondary']) ?></p><?php endif; ?>
  <div class="acts" data-group="secondary">
    <label class="act act-none">
      <input type="radio" name="secondary" value="" <?= $old['secondary'] === '' ? 'checked' : '' ?>>
      <span class="act-body"><strong>No second choice</strong></span>
    </label>
    <?php foreach ($acts as $a): ?>
    <label class="act">
      <input type="radio" name="secondary" value="<?= (int)$a['id'] ?>" <?= $old['secondary'] === (string)$a['id'] ? 'checked' : '' ?>>
      <span class="act-body">
        <strong><?= e($a['name']) ?></strong>
        <?php if ($a['description']): ?><small><?= e($a['description']) ?></small><?php endif; ?>
      </span>
    </label>
    <?php endforeach; ?>
  </div>

  <label class="check<?= isset($errors['consent']) ? ' has-err' : '' ?>">
    <input type="checkbox" name="consent" value="1" <?= !empty($_POST['consent']) ? 'checked' : '' ?>>
    <span>I agree that my name, email, phone number, Discord username and company/position are stored and used by the organisers only to manage HLTV Awards Mingle activities.</span>
  </label>
  <?php if (isset($errors['consent'])): ?><p class="err"><?= e($errors['consent']) ?></p><?php endif; ?>

  <button class="btn btn-primary btn-lg" type="submit">Sign up</button>
  <p class="small muted">Sign-ups are reviewed by the organisers. Choices can't be changed after submitting.</p>
</form>
<?php endif;
page_footer();
