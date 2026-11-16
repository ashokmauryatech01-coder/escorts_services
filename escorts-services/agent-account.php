<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$agentId = filter_var($_SESSION['agent_application_id'] ?? null, FILTER_VALIDATE_INT);
if (!$agentId) {
    header('Location: agent-login.php');
    exit;
}
$db = app_db();
$query = $db->prepare('SELECT * FROM agent_applications WHERE id = ?');
$query->execute([$agentId]);
$agent = $query->fetch();
if (!$agent) {
    unset($_SESSION['agent_application_id']);
    header('Location: agent-login.php');
    exit;
}
$upi = app_upi_config();
$amount = (int) ($agent['amount_paise'] ?? 0);
vg_head(['title' => 'Agent application — Velvet & Gold', 'active' => 'agent', 'patron' => null, 'robots' => 'noindex']);
?>
<div class="wrap auth-wrap">
  <h1>Your agent application</h1>
  <p class="lead" style="margin-bottom:1.5rem">Hello, <?= app_escape($agent['name']) ?>. Here is the latest status of your application and subscription.</p>
  <div class="panel">
    <dl class="dl">
      <div><dt>Application</dt><dd>#<?= (int) $agent['id'] ?> · <?= app_escape($agent['status']) ?></dd></div>
      <div><dt>Selected plan</dt><dd><?= app_escape($agent['plan'] ?? '—') ?></dd></div>
      <div><dt>One-time subscription fee</dt><dd><?= $amount ? vg_money($amount / 100) : '—' ?></dd></div>
      <div><dt>Payment status</dt><dd><?= app_escape($agent['payment_status']) ?></dd></div>
    </dl>
  </div>

  <?php if ($agent['status'] === 'Pending'): ?>
    <div class="notice" style="margin-top:1.2rem">Your application is waiting for coordinator review. Payment becomes available after approval.</div>
  <?php elseif ($agent['status'] === 'Declined'): ?>
    <div class="notice warn" style="margin-top:1.2rem">Your application was not approved. Contact the coordinator if you need more information.</div>
  <?php elseif ($agent['payment_status'] === 'Verified' && $agent['companion_id']): ?>
    <div class="notice" style="margin-top:1.2rem">Your payment is verified and your profile is published.</div>
    <a class="btn btn-gold" style="margin-top:1rem" href="listing.php?id=<?= (int) $agent['companion_id'] ?>">View your public profile</a>
  <?php elseif (in_array($agent['payment_status'], ['Awaiting Payment', 'Rejected'], true)): ?>
    <?php if ($agent['payment_status'] === 'Rejected'): ?><p class="notice warn" style="margin:1.2rem 0">The coordinator could not verify the previous reference. Check the amount and submit the correct UPI reference.</p><?php endif; ?>
    <?php if (!$upi): ?>
      <p class="notice warn" style="margin-top:1.2rem">UPI is not configured. Contact the coordinator for payment instructions.</p>
    <?php else: ?>
      <?php if (!empty($upi['demo'])): ?><p class="notice warn" style="margin:1.2rem 0">Demo payment mode is active. This QR uses a fake payee and cannot move money.</p><?php endif; ?>
      <div class="panel" style="margin-top:1.2rem">
        <h2>Pay after approval</h2>
        <p>Pay exactly <?= vg_money($amount / 100) ?> to <?= app_escape($upi['payee']) ?>, then submit the UPI transaction reference below. A coordinator checks the payment before publishing your profile.</p>
        <div class="qr-box" style="margin-top:1rem">
          <img src="upi-qr.php?ref=<?= rawurlencode((string) $agent['payment_ref']) ?>" alt="Agent subscription UPI QR code" width="200" height="200">
          <dl>
            <div><dt>UPI ID</dt><dd><?= app_escape($upi['upi_id']) ?></dd><button class="copy" type="button" data-copy="<?= app_escape($upi['upi_id']) ?>">Copy UPI ID</button></div>
            <div><dt>Payment reference</dt><dd class="mono-ref"><?= app_escape($agent['payment_ref']) ?></dd></div>
          </dl>
        </div>
        <a class="btn btn-block" style="margin-top:1rem" href="<?= app_escape(app_upi_intent($upi, (string) $agent['payment_ref'], $amount, 'agent subscription')) ?>">Open UPI app</a>
        <form id="agentUtrForm" style="margin-top:1.2rem">
          <input type="hidden" name="payment_ref" value="<?= app_escape($agent['payment_ref']) ?>">
          <div class="field"><label for="agentUtr">UPI transaction reference (UTR)</label><input id="agentUtr" name="utr" required minlength="6" maxlength="30" pattern="[A-Za-z0-9-]{6,30}" autocapitalize="characters" autocomplete="off"></div>
          <button class="btn btn-gold btn-block" id="agentUtrBtn" type="submit">Submit for verification</button>
        </form>
      </div>
    <?php endif; ?>
  <?php elseif ($agent['payment_status'] === 'Submitted'): ?>
    <p class="notice" style="margin-top:1.2rem">Your UPI reference is with the coordinator for manual verification. Your profile will be published after payment is confirmed.</p>
  <?php endif; ?>
  <button class="btn" id="agentLogout" type="button" style="margin-top:1.2rem">Sign out</button>
</div>
<?php
vg_foot(['script' => <<<'JS'
(()=>{
 const {api,toast}=window.VG,form=document.querySelector('#agentUtrForm'),button=document.querySelector('#agentUtrBtn');
 if(form)form.addEventListener('submit',async event=>{event.preventDefault();if(!form.reportValidity())return;button.disabled=true;const values=Object.fromEntries(new FormData(form));
  try{await api({action:'agent-payment-submit',...values});location.reload();}
  catch(error){button.disabled=false;toast(error.message,true);}
 });
 const logout=document.querySelector('#agentLogout');if(logout)logout.addEventListener('click',async()=>{try{await api({action:'agent-logout'});location.href='agent-login.php';}catch(error){toast(error.message,true);}});
})();
JS]);
