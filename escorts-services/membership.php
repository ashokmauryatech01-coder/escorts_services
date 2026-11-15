<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$patron = vg_patron($db);
$prices = app_membership_prices();
$tiers = [
    'Velvet Pass' => ['Private member access', 'The entry level. Browse profiles and send requests once your payment is verified.'],
    'Gold Reserve' => ['Enhanced member access', 'A step up for members who plan to book regularly.'],
    'Elite VIP' => ['Premium member access', 'The highest membership level.'],
];

vg_head(['title' => 'Membership — Velvet & Gold', 'active' => 'membership', 'patron' => $patron]);
?>
<div class="wrap">
  <div class="page-head">
    <h1>Membership</h1>
    <p class="lead">Pay once by UPI. After the coordinator verifies your payment, you can send requests from any profile.</p>
  </div>
  <div class="tiers">
    <?php $i = 0; foreach ($tiers as $name => [$short, $long]): $i++; $current = $patron && $patron['tier'] === $name; ?>
      <div class="tier<?= $i === 2 ? ' featured' : '' ?>">
        <h3><?= app_escape($name) ?></h3>
        <div class="price"><?= vg_money($prices[$name]) ?><small>one-time</small></div>
        <p><?= app_escape($short) ?>. <?= app_escape($long) ?></p>
        <ul>
          <li>Send requests to any active profile</li>
          <li>Track every request in your dashboard</li>
          <li>Private profile and photos, never published</li>
        </ul>
        <?php if ($current): ?>
          <a class="btn btn-gold" href="payment.php">Your membership · view payment</a>
        <?php elseif ($patron): ?>
          <span class="badge dim" style="align-self:flex-start">Membership is chosen at sign-up</span>
        <?php else: ?>
          <a class="btn <?= $i === 2 ? 'btn-gold' : '' ?>" href="register.php?tier=<?= rawurlencode($name) ?>">Choose <?= app_escape($name) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <section class="section split" style="padding-bottom:0">
    <div><h2>How payment works</h2><p class="lead" style="margin-top:1rem">Payment is by UPI only. We never take card details.</p></div>
    <ol class="how" style="margin-top:0">
      <li><span><strong>Scan your QR code.</strong> It carries your exact amount and a payment reference.</span></li>
      <li><span><strong>Pay in any UPI app.</strong> Keep the transaction reference (UTR) it shows you.</span></li>
      <li><span><strong>Submit the reference.</strong> The coordinator checks it against the merchant account and marks it verified.</span></li>
    </ol>
  </section>
</div>
<?php vg_foot();
