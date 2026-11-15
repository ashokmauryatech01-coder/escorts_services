<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$stmt = $db->prepare('SELECT * FROM companions WHERE id = ? AND active = 1');
$stmt->execute([$id ?: 0]);
$c = $stmt->fetch();
$patron = vg_patron($db);
$payment = $patron ? vg_latest_payment($db, $patron['id']) : null;

if (!$c) {
    http_response_code(404);
    vg_head(['title' => 'Profile not found — Velvet & Gold', 'active' => 'directory', 'patron' => $patron]);
    ?>
    <div class="wrap page-head"><div class="empty"><strong>This profile isn't available</strong>It may have been hidden by the coordinator or the link may be wrong.<br><a class="btn btn-gold" href="directory.php">Back to the directory</a></div></div>
    <?php
    vg_foot();
    exit;
}
$others = $db->prepare('SELECT * FROM companions WHERE active = 1 AND id <> ? ORDER BY id DESC LIMIT 3');
$others->execute([$c['id']]);
$others = $others->fetchAll();

vg_head([
    'title' => $c['name'] . ' — Velvet & Gold',
    'active' => 'directory',
    'patron' => $patron,
    'desc' => $c['title'] . ' · ' . $c['location'],
    'cfg' => ['paymentVerified' => $payment && $payment['status'] === 'Verified'],
]);
?>
<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span><a href="directory.php">Directory</a><span>/</span><span><?= app_escape($c['name']) ?></span></nav>
  <div class="listing">
    <div class="media">
      <span class="mono" aria-hidden="true"><?= app_escape(vg_initial($c['name'])) ?></span>
      <img class="js-photo" src="<?= app_escape($c['photo']) ?>" alt="Portrait of <?= app_escape($c['name']) ?>">
    </div>
    <div>
      <span class="badge"><?= app_escape($c['badge']) ?></span>
      <h1><?= app_escape($c['name']) ?></h1>
      <p class="serif" style="font-size:1.5rem;color:var(--brass-hi);margin-bottom:1rem"><?= app_escape($c['title']) ?></p>
      <p class="lead"><?= app_escape($c['description']) ?></p>
      <dl class="dl" style="margin-top:1.4rem">
        <div><dt>Category</dt><dd><?= app_escape($c['category']) ?></dd></div>
        <div><dt>Location</dt><dd><?= app_escape($c['location']) ?></dd></div>
        <div><dt>Availability</dt><dd><?= app_escape($c['badge']) ?></dd></div>
      </dl>
      <div class="rates">
        <div class="rate"><span>Hourly</span><strong><?= app_escape($c['rate']) ?></strong></div>
        <div class="rate"><span>Overnight</span><strong><?= app_escape($c['overnight']) ?></strong></div>
      </div>
      <button type="button" class="btn btn-gold btn-block book-button" data-id="<?= (int) $c['id'] ?>" data-name="<?= app_escape($c['name']) ?>">Send a request</button>
      <p class="hint" style="margin-top:.8rem">
        <?php if (!$patron): ?>You'll need a <a href="register.php">member profile</a> first.
        <?php elseif (app_upi_config() && !($payment && $payment['status'] === 'Verified')): ?>Requests unlock after your <a href="payment.php">membership payment</a> is verified.
        <?php else: ?>The coordinator confirms or declines every request.<?php endif; ?>
      </p>
    </div>
  </div>
</div>

<?php if ($others): ?>
<section class="section"><div class="wrap">
  <div class="section-head"><h2>More profiles</h2><a class="btn" href="directory.php">All profiles</a></div>
  <div class="grid-cards"><?php foreach ($others as $o) vg_profile_card($o); ?></div>
</div></section>
<?php endif;
vg_booking_modal();
vg_foot();
