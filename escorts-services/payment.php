<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();

$patronId = (string) ($_SESSION['patron_id'] ?? '');
if ($patronId === '') {
    header('Location: register.php');
    exit;
}

$db = app_db();
$patronQuery = $db->prepare('SELECT id, name, email, phone, tier FROM patrons WHERE id = ?');
$patronQuery->execute([$patronId]);
$patron = $patronQuery->fetch();
if (!$patron) {
    unset($_SESSION['patron_id']);
    header('Location: register.php');
    exit;
}

$paymentQuery = $db->prepare('SELECT * FROM membership_payments WHERE patron_id = ? ORDER BY id DESC LIMIT 1');
$paymentQuery->execute([$patronId]);
$payment = $paymentQuery->fetch() ?: null;
$upiConfig = app_upi_config();
$membershipPrice = app_membership_prices()[$patron['tier']] ?? 0;
$amountPaise = $payment && $payment['status'] !== 'Rejected'
    ? (int) $payment['amount_paise']
    : $membershipPrice * 100;
$isDemo = !empty($upiConfig['demo']);
$status = $payment['status'] ?? null;

vg_head(['title' => 'Membership payment — Velvet & Gold', 'active' => 'dashboard', 'patron' => $patron, 'gate' => false, 'robots' => 'noindex']);
?>
<div class="wrap">
  <div class="page-head" style="padding-bottom:1.2rem">
    <h1>Membership payment</h1>
    <p class="lead">Pay by UPI, then give us the transaction reference so the coordinator can verify it.</p>
  </div>

  <div class="pay">
    <aside>
      <div class="mcard" aria-label="Membership summary" style="margin-bottom:1.6rem">
        <div class="mcard-top"><span class="brand-mark" aria-hidden="true">V</span><span class="badge <?= $status === 'Verified' ? 'ok' : 'warn' ?>"><?= $status === 'Verified' ? 'Verified' : 'Pending' ?></span></div>
        <div class="mcard-tier"><small>Membership</small><?= app_escape($patron['tier']) ?></div>
        <div class="mcard-bot"><div>Member<strong><?= app_escape($patron['name']) ?></strong></div><div class="mcard-id"><?= app_escape($patron['id']) ?></div></div>
      </div>
      <div class="panel">
        <dl class="dl">
          <div><dt>Membership</dt><dd><?= app_escape($patron['tier']) ?></dd></div>
          <div><dt>Contact</dt><dd><?= app_escape($patron['email']) ?></dd></div>
          <div><dt>Amount due</dt><dd class="amount">₹<?= number_format($amountPaise / 100) ?></dd></div>
        </dl>
      </div>
      <p style="margin-top:1rem"><a href="dashboard.php">← Back to my lounge</a></p>
    </aside>

    <section aria-labelledby="payTitle">
      <div class="panel">
        <?php if ($isDemo): ?>
          <div class="demo-bar" role="note"><span><strong>Demo mode.</strong> This QR code is a placeholder. It points to a fake payee, so no money can move.</span></div>
        <?php endif; ?>
        <h2 id="payTitle" style="font-size:2rem;margin-bottom:1rem">
          <?php if (!$upiConfig): ?>Payment unavailable
          <?php elseif ($payment && $status === 'Awaiting Payment'): ?>Scan to pay
          <?php elseif ($payment && in_array($status, ['Submitted', 'Verified'], true)): ?>Payment <?= $status === 'Verified' ? 'verified' : 'submitted' ?>
          <?php else: ?>Start your payment<?php endif; ?>
        </h2>

        <?php if (!$upiConfig): ?>
          <p class="notice warn">UPI payments aren't set up yet. Please contact the coordinator. A payment QR can't be shown until a valid merchant UPI ID and payee name are configured.</p>

        <?php elseif ($payment && $status === 'Awaiting Payment'): ?>
          <div class="qr-box">
            <?php if ($isDemo): ?><span class="demo-tag">DEMO</span><?php endif; ?>
            <img src="upi-qr.php?ref=<?= rawurlencode($payment['payment_ref']) ?>" alt="UPI payment QR code for ₹<?= number_format($amountPaise / 100) ?>" width="200" height="200">
            <dl>
              <div><dt>Payee</dt><dd><?= app_escape($upiConfig['payee']) ?></dd></div>
              <div><dt>UPI ID</dt><dd><?= app_escape($upiConfig['upi_id']) ?></dd><button class="copy" type="button" data-copy="<?= app_escape($upiConfig['upi_id']) ?>">Copy UPI ID</button></div>
              <div><dt>Payment reference</dt><dd class="mono-ref"><?= app_escape($payment['payment_ref']) ?></dd></div>
            </dl>
          </div>
          <ol class="how">
            <li><span><strong>Scan the QR code</strong> with any UPI app<?= $isDemo ? ' (demo codes won\'t complete)' : '' ?>, or tap “Open UPI app” on your phone.</span></li>
            <li><span><strong>Pay exactly ₹<?= number_format($amountPaise / 100) ?>.</strong> Keep the transaction reference your app shows.</span></li>
            <li><span><strong>Enter that reference below</strong> so the coordinator can verify it.</span></li>
          </ol>
          <a class="btn btn-block" href="<?= app_escape(app_upi_intent($upiConfig, $payment['payment_ref'], (int) $payment['amount_paise'])) ?>">Open UPI app</a>
          <form id="utrForm" style="margin-top:1.4rem">
            <input type="hidden" name="payment_ref" value="<?= app_escape($payment['payment_ref']) ?>">
            <div class="field"><label for="paymentUtr">UPI transaction reference (UTR)</label>
              <input id="paymentUtr" name="utr" required minlength="6" maxlength="30" pattern="[A-Za-z0-9\-]{6,30}" autocomplete="off" autocapitalize="characters" placeholder="Shown by your payment app">
              <span class="hint">6–30 letters, numbers or hyphens.</span></div>
            <button class="btn btn-wine btn-block" type="submit" id="utrBtn">Submit for verification</button>
          </form>
          <p class="notice" style="margin-top:1.2rem">A QR scan or a submitted reference doesn't confirm payment. The coordinator verifies it against the merchant account.</p>

        <?php elseif ($payment && in_array($status, ['Submitted', 'Verified'], true)): ?>
          <div class="status-card">
            <span class="badge <?= $status === 'Verified' ? 'ok' : 'warn' ?>"><?= app_escape($status) ?></span>
            <dl class="dl" style="margin-top:.8rem">
              <div><dt>Payment reference</dt><dd class="mono-ref"><?= app_escape($payment['payment_ref']) ?></dd></div>
              <div><dt>Transaction reference</dt><dd class="mono-ref"><?= app_escape($payment['utr']) ?></dd></div>
            </dl>
            <p class="muted" style="margin:1rem 0 0"><?= $status === 'Verified' ? 'Your membership payment is verified. You can now send requests.' : 'The coordinator will compare your reference with the merchant account. This page shows the result once it changes.' ?></p>
          </div>
          <div class="cta-row" style="margin-top:1.2rem"><a class="btn btn-gold" href="<?= $status === 'Verified' ? 'directory.php' : 'dashboard.php' ?>"><?= $status === 'Verified' ? 'Browse the directory' : 'Back to my lounge' ?></a></div>

        <?php else: ?>
          <?php if ($status === 'Rejected'): ?><p class="notice bad" style="margin-bottom:1rem">The previous reference couldn't be verified. You can start a new payment request.</p><?php endif; ?>
          <p class="muted">Create a payment request to generate your personal UPI QR code for ₹<?= number_format($amountPaise / 100) ?>.</p>
          <button class="btn btn-gold btn-block" id="startPayment" type="button">Generate UPI QR code</button>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php
vg_foot(['script' => <<<'JS'
(()=>{
  const {$,api,toast}=window.VG;
  const start=$('#startPayment');
  if(start) start.addEventListener('click',async()=>{start.disabled=true;try{await api({action:'payment-start'});location.reload();}catch(e){start.disabled=false;toast(e.message,true);}});
  const f=$('#utrForm');
  if(f) f.addEventListener('submit',async e=>{e.preventDefault();const b=$('#utrBtn');b.disabled=true;const v=new FormData(f);
    try{await api({action:'payment-submit-utr',payment_ref:v.get('payment_ref'),utr:v.get('utr')});location.reload();}
    catch(err){b.disabled=false;toast(err.message,true);}});
})();
JS]);
