<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$patron = vg_patron($db);

if (!$patron) {
    vg_head(['title' => 'My lounge — Velvet & Gold', 'active' => 'dashboard', 'patron' => null, 'robots' => 'noindex']);
    ?>
    <div class="wrap page-head"><div class="empty"><strong>No member session found</strong>Create a member profile to use the lounge. Your session stays active in this browser.<br><a class="btn btn-gold" href="register.php">Become a member</a></div></div>
    <?php
    vg_foot();
    exit;
}

$upiConfig = app_upi_config();
$stmt = $db->prepare(
    'SELECT b.*, c.name AS companion_name
     FROM bookings b JOIN companions c ON c.id = b.companion_id
     WHERE b.patron_id = ? ORDER BY b.id DESC'
);
$stmt->execute([$patron['id']]);
$bookings = $stmt->fetchAll();
$stmt = $db->prepare('SELECT id, sort_order FROM patron_photos WHERE patron_id = ? ORDER BY sort_order');
$stmt->execute([$patron['id']]);
$photos = $stmt->fetchAll();
$payment = vg_latest_payment($db, $patron['id']);
$prices = app_membership_prices();
$amount = $payment && $payment['status'] !== 'Rejected' ? (int) $payment['amount_paise'] / 100 : ($prices[$patron['tier']] ?? 0);
$status = $payment['status'] ?? null;
$verified = $status === 'Verified';
$pendingCount = count(array_filter($bookings, fn ($b) => $b['status'] === 'Pending'));

$labels = [
    'height_cm' => 'Height', 'figure' => 'Figure / physique', 'hair_color' => 'Hair color',
    'city' => 'City', 'state' => 'State / region', 'country' => 'Country',
    'postal_code' => 'Postal code', 'interests' => 'Interests',
    'skin_tone' => 'Skin tone', 'origin' => 'Origin / background',
    'chest_size' => 'Chest / bust size', 'penis_size' => 'Penis size',
];
$profileRows = [];
foreach ($labels as $key => $label) {
    if (!empty($patron[$key])) {
        $profileRows[$label] = $key === 'height_cm' ? $patron[$key] . ' cm' : $patron[$key];
    }
}

// Journey: registered → payment → verified → first request
$journey = [
    ['Profile created', true],
    ['Payment ' . ($status === 'Submitted' ? 'submitted' : 'sent'), in_array($status, ['Submitted', 'Verified'], true)],
    ['Payment verified', $verified],
    ['First request sent', (bool) $bookings],
];
if (!$upiConfig) {
    $journey = [['Profile created', true], ['First request sent', (bool) $bookings]];
}
$nowIdx = null;
foreach ($journey as $i => [, $done]) {
    if (!$done) { $nowIdx = $i; break; }
}
$badgeClass = ['Pending' => 'warn', 'Confirmed' => 'ok', 'Declined' => 'bad'];
$payClass = ['Verified' => 'ok', 'Submitted' => 'warn', 'Rejected' => 'bad', 'Awaiting Payment' => 'warn'];

vg_head(['title' => 'My lounge — Velvet & Gold', 'active' => 'dashboard', 'patron' => $patron, 'robots' => 'noindex']);
?>
<div class="wrap">
  <div class="page-head" style="padding-bottom:0">
    <div class="welcome">
      <div>
        <h1>Welcome, <?= app_escape(explode(' ', trim($patron['name']))[0]) ?>.</h1>
        <p class="lead">Your requests, membership and private profile, all in one place.</p>
        <ul class="journey" aria-label="Your progress">
          <?php foreach ($journey as $i => [$label, $done]): ?>
            <li class="<?= $done ? 'done' : ($i === $nowIdx ? 'now' : '') ?>"><span class="dot" aria-hidden="true"><?= $done ? '✓' : '' ?></span><?= app_escape($label) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="cta-row" style="margin-top:1.6rem">
          <?php if ($upiConfig && !$verified): ?><a class="btn btn-gold" href="payment.php"><?= $status === 'Submitted' ? 'View payment status' : 'Pay for membership' ?></a><?php else: ?><a class="btn btn-gold" href="directory.php">Browse the directory</a><?php endif; ?>
        </div>
      </div>
      <div class="mcard" aria-label="Your membership card">
        <div class="mcard-top"><span class="brand-mark" aria-hidden="true">V</span><?php if ($upiConfig): ?><span class="badge <?= $verified ? 'ok' : 'warn' ?>"><?= $verified ? 'Active' : 'Awaiting payment' ?></span><?php else: ?><span class="badge">Member</span><?php endif; ?></div>
        <div class="mcard-tier"><small>Membership</small><?= app_escape($patron['tier']) ?></div>
        <div class="mcard-bot"><div>Member<strong><?= app_escape($patron['name']) ?></strong></div><div class="mcard-id"><?= app_escape($patron['id']) ?></div></div>
      </div>
    </div>
  </div>

  <div class="dash">
    <nav class="side" data-spy aria-label="Dashboard sections">
      <a href="#requests" class="on">Requests <?php if ($pendingCount): ?><span class="count"><?= $pendingCount ?> pending</span><?php endif; ?></a>
      <a href="#membership">Membership</a>
      <a href="#profile">Profile &amp; photos</a>
      <a href="#account">Account</a>
    </nav>

    <div>
      <section class="dash-section" id="requests">
        <h2>Your requests</h2>
        <?php if (!$bookings): ?>
          <div class="empty"><strong>No requests yet</strong>Pick a profile and send your first request.<br><a class="btn btn-gold" href="directory.php">Browse the directory</a></div>
        <?php else: ?>
          <div class="panel" style="padding-block:.4rem">
            <?php foreach ($bookings as $b): ?>
              <div class="req">
                <div><h3><?= app_escape($b['companion_name']) ?></h3><p><?= app_escape($b['setting']) ?></p><p><?= app_escape($b['booking_date']) ?> at <?= app_escape($b['booking_time']) ?> · <?= app_escape($b['duration']) ?></p></div>
                <span class="badge <?= $badgeClass[$b['status']] ?? '' ?>" style="align-self:start"><?= app_escape($b['status']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="dash-section" id="membership">
        <h2>Membership payment</h2>
        <div class="panel">
          <dl class="dl">
            <div><dt>Membership</dt><dd><?= app_escape($patron['tier']) ?></dd></div>
            <div><dt>Amount</dt><dd><?= vg_money($amount) ?></dd></div>
            <?php if ($upiConfig): ?>
            <div><dt>Status</dt><dd><span class="badge <?= $payClass[$status ?? ''] ?? 'dim' ?>"><?= app_escape($status ?? 'Not started') ?></span></dd></div>
            <?php if ($payment && $payment['utr']): ?><div><dt>Transaction reference</dt><dd class="mono-ref"><?= app_escape($payment['utr']) ?></dd></div><?php endif; ?>
            <?php endif; ?>
          </dl>
          <?php if (!$upiConfig): ?>
            <p class="notice" style="margin-top:1rem">UPI payments aren't set up yet. Please contact the coordinator.</p>
          <?php else: ?>
            <p class="muted" style="margin:1rem 0 1.2rem">
              <?php if ($verified): ?>Your payment is verified. You can send requests.
              <?php elseif ($status === 'Submitted'): ?>The coordinator will compare your reference with the merchant account.
              <?php elseif ($status === 'Rejected'): ?>The previous reference couldn't be verified. You can start a new payment.
              <?php else: ?>Scan the UPI QR code and send the exact amount.<?php endif; ?>
            </p>
            <?php if (!$verified): ?><a class="btn btn-gold" href="payment.php"><?= $status === 'Submitted' ? 'View payment' : 'Open payment page' ?></a><?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="dash-section" id="profile">
        <h2>Profile &amp; photos</h2>
        <p class="notice" style="margin-bottom:1rem">Only you and the coordinator can see these details and photos. They are never shown in the directory.</p>
        <?php if (!$profileRows): ?>
          <div class="empty"><strong>No profile details saved</strong>You skipped the optional profile step when you joined.</div>
        <?php else: ?>
          <div class="panel"><dl class="dl"><?php foreach ($profileRows as $label => $value): ?><div><dt><?= app_escape($label) ?></dt><dd><?= app_escape($value) ?></dd></div><?php endforeach; ?></dl></div>
        <?php endif; ?>
        <?php if ($photos): ?>
          <h3 style="margin:1.8rem 0 .8rem">Your private photos</h3>
          <div class="photo-row"><?php foreach ($photos as $p): ?><img src="photo.php?id=<?= (int) $p['id'] ?>" alt="Your private profile photo" loading="lazy"><?php endforeach; ?></div>
        <?php endif; ?>
      </section>

      <section class="dash-section" id="account">
        <h2>Account</h2>
        <div class="panel">
          <dl class="dl">
            <div><dt>Name</dt><dd><?= app_escape($patron['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= app_escape($patron['email']) ?></dd></div>
            <div><dt>Phone</dt><dd><?= app_escape($patron['phone']) ?></dd></div>
            <div><dt>Member ID</dt><dd class="mono-ref"><?= app_escape($patron['id']) ?></dd></div>
          </dl>
          <button class="btn" type="button" data-member-logout style="margin-top:1.2rem">Sign out</button>
        </div>
      </section>
    </div>
  </div>
</div>
<?php vg_foot();
