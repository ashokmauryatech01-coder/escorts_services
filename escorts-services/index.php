<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$membershipPrices = app_membership_prices();
$companions = $db->query('SELECT * FROM companions WHERE active = 1 ORDER BY id DESC')->fetchAll();
$patron = vg_patron($db);
$featured = array_slice($companions, 0, 3);

vg_head(['title' => 'Velvet & Gold — Private Companion Collective', 'active' => 'home', 'patron' => $patron]);
?>
<div class="wrap">
  <section class="hero" aria-labelledby="heroTitle">
    <div>
      <span class="kicker">Private · Adults only · By introduction</span>
      <h1 id="heroTitle">Good company, <em>arranged</em> with discretion.</h1>
      <p class="lead">Velvet &amp; Gold is a members' collective for dinners, events and quiet evenings. Browse current profiles, send a private request, and a coordinator handles the rest.</p>
      <div class="cta-row">
        <a class="btn btn-gold" href="directory.php">Browse the directory</a>
        <?php if ($patron): ?><a class="btn" href="dashboard.php">Open my lounge</a><?php else: ?><a class="btn" href="register.php">Become a member</a><?php endif; ?>
      </div>
      <div class="hero-facts">
        <div><strong><?= count($companions) ?></strong>Profiles available</div>
        <div><strong>18+</strong>Adults only</div>
        <div><strong>Manual</strong>Every request reviewed</div>
      </div>
    </div>
    <div>
      <?php if ($patron): ?>
        <div class="mcard" aria-label="Your membership card">
          <div class="mcard-top"><span class="brand-mark" aria-hidden="true">V</span><span class="badge">Member</span></div>
          <div class="mcard-tier"><small>Your membership</small><?= app_escape($patron['tier']) ?></div>
          <div class="mcard-bot"><div>Member<strong><?= app_escape($patron['name']) ?></strong></div><div class="mcard-id"><?= app_escape($patron['id']) ?></div></div>
        </div>
      <?php else: ?>
        <div class="mcard" aria-hidden="true">
          <div class="mcard-top"><span class="brand-mark">V</span><span class="badge">Members</span></div>
          <div class="mcard-tier"><small>Membership</small>Velvet &amp; Gold</div>
          <div class="mcard-bot"><div>Admitted by<strong>Request</strong></div><div class="mcard-id">VG-0000-0000</div></div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="section-tight" id="featured">
  <div class="wrap">
    <div class="section-head">
      <div><h2>Recently added</h2><p>A few of the profiles currently open to requests.</p></div>
      <a class="btn" href="directory.php">See all profiles</a>
    </div>
    <?php if (!$featured): ?>
      <div class="empty"><strong>No active listings right now</strong>The coordinator is updating the directory. Please check back soon.</div>
    <?php else: ?>
      <div class="grid-cards"><?php foreach ($featured as $c) vg_profile_card($c); ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="section" id="how">
  <div class="wrap">
    <div class="section-head"><div><h2>How it works</h2><p>Four steps from first visit to a confirmed evening.</p></div></div>
    <ol class="steps">
      <li><h3>Join</h3><p>Choose a membership and add your contact details. Profile details and photos are optional.</p></li>
      <li><h3>Pay by UPI</h3><p>Scan your personal QR code and send the exact amount, then enter the transaction reference.</p></li>
      <li><h3>Send a request</h3><p>Once the coordinator has verified your payment, request a date, time and setting from any profile.</p></li>
      <li><h3>Get a reply</h3><p>The coordinator confirms or declines. You'll see the outcome in your dashboard.</p></li>
    </ol>
  </div>
</section>

<section class="section-tight" id="memberships">
  <div class="wrap">
    <div class="section-head"><div><h2>Memberships</h2><p>One-time membership fees. Choose the level that suits you.</p></div><a class="btn" href="membership.php">Compare in detail</a></div>
    <div class="tiers">
      <?php
      $tiers = [
          'Velvet Pass' => ['Private member access', false],
          'Gold Reserve' => ['Enhanced member access', true],
          'Elite VIP' => ['Premium member access', false],
      ];
      foreach ($tiers as $name => [$desc, $feat]): ?>
        <div class="tier<?= $feat ? ' featured' : '' ?>">
          <h3><?= app_escape($name) ?></h3>
          <div class="price"><?= vg_money($membershipPrices[$name]) ?></div>
          <p><?= app_escape($desc) ?></p>
          <a class="btn <?= $feat ? 'btn-gold' : '' ?>" href="register.php?tier=<?= rawurlencode($name) ?>">Choose <?= app_escape($name) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="privacy">
  <div class="wrap split">
    <div><h2>Private by design</h2><p class="lead" style="margin-top:1rem">We collect only what the coordinator needs to respond to you, and we say plainly what we don't collect.</p></div>
    <ul class="facts">
      <li><strong>Your profile is never published</strong><span>Member details and photos are visible only to you and the coordinator.</span></li>
      <li><strong>No cards, no ID numbers</strong><span>Payment is by UPI. We never ask for card details, government ID or one-time codes.</span></li>
      <li><strong>Payments are checked by a person</strong><span>A submitted reference is verified against the merchant account before access is granted.</span></li>
    </ul>
  </div>
</section>

<section class="section-tight" id="faq">
  <div class="wrap wrap-narrow" style="margin:0 auto">
    <h2 style="margin-bottom:1.2rem">Questions</h2>
    <div class="faq">
      <details><summary>Who can use Velvet &amp; Gold?</summary><p>Adults aged 18 and over. You'll be asked to confirm your age when you arrive.</p></details>
      <details><summary>Why do I need to pay before sending a request?</summary><p>Membership is paid once by UPI. After the coordinator verifies the payment, request buttons unlock on every profile.</p></details>
      <details><summary>How long does verification take?</summary><p>The coordinator checks each reference by hand against the merchant account. Your dashboard shows the status as soon as it changes.</p></details>
      <details><summary>Can other members see my details?</summary><p>No. Your profile and photos are not listed in the directory. Only you and the coordinator can see them.</p></details>
      <details><summary>Is a request the same as a booking?</summary><p>No. A request is confirmed or declined by the coordinator, depending on availability.</p></details>
    </div>
  </div>
</section>
<?php vg_foot();
