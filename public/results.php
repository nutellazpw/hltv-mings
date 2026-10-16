<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

$published = array_values(array_filter(activities(), fn($a) => (int)$a['results_published'] === 1));
$aid = (int)($_GET['a'] ?? ($published[0]['id'] ?? 0));
$act = null;
foreach ($published as $p) if ((int)$p['id'] === $aid) $act = $p;

page_header('Results');
?>
<section class="hero hero-sm">
  <p class="eyebrow">HLTV Awards Mingle</p>
  <h1>Results</h1>
</section>
<?php if (!$published): ?>
  <div class="card"><p>No results have been published yet. Check back after the activities.</p></div>
<?php else: ?>
  <nav class="tabs">
    <?php foreach ($published as $p): ?>
      <a class="<?= (int)$p['id'] === $aid ? 'on' : '' ?>" href="?a=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($act):
    $groups = q('SELECT * FROM groups_ WHERE activity_id = ? ORDER BY name', [$act['id']])->fetchAll();
    foreach ($groups as $g):
      $people = q('SELECT first_name, last_name, result FROM signups WHERE group_id = ? AND status = \'accepted\' ORDER BY first_name', [$g['id']])->fetchAll(); ?>
    <div class="card">
      <h2><?= e($g['name']) ?></h2>
      <?php if ($g['result']): ?><div class="result-box"><span>Result</span><strong><?= nl2br(e($g['result'])) ?></strong></div><?php endif; ?>
      <ul class="mates">
        <?php foreach ($people as $m): ?>
          <li><span><?= e($m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.') ?></span>
          <?php if ($m['result']): ?><em><?= e($m['result']) ?></em><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; endif; ?>
<?php endif;
page_footer();
