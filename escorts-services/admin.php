<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
app_start_session();
$db = app_db();
$csrf = app_csrf_token();
$isAdmin = !empty($_SESSION['admin_id']);
$setupRequired = !app_admin_exists();
$companions = [];
$patrons = [];
$patronPhotos = [];
$bookings = [];
$payments = [];
$stats = ['companions' => 0, 'patrons' => 0, 'bookings' => 0, 'pending' => 0, 'payments' => 0];
if ($isAdmin) {
    $companions = $db->query('SELECT * FROM companions ORDER BY id DESC')->fetchAll();
    $patrons = $db->query('SELECT * FROM patrons ORDER BY created_at DESC')->fetchAll();
    $patronPhotos = $db->query(
        'SELECT id, patron_id FROM patron_photos ORDER BY patron_id, sort_order'
    )->fetchAll();
    $bookings = $db->query(
        'SELECT b.*, p.name AS patron_name, c.name AS companion_name,
                c.title AS companion_title, c.category AS companion_category,
                c.rate AS companion_rate, c.overnight AS companion_overnight,
                c.location AS companion_location, c.description AS companion_description,
                c.badge AS companion_badge, c.photo AS companion_photo
         FROM bookings b JOIN patrons p ON p.id = b.patron_id
         JOIN companions c ON c.id = b.companion_id ORDER BY b.id DESC'
    )->fetchAll();
    $payments = $db->query(
        'SELECT mp.*, p.name AS patron_name, p.email AS patron_email, p.tier AS tier
         FROM membership_payments mp JOIN patrons p ON p.id = mp.patron_id
         ORDER BY mp.id DESC'
    )->fetchAll();
    $stats = [
        'companions' => (int) $db->query('SELECT COUNT(*) FROM companions WHERE active = 1')->fetchColumn(),
        'patrons' => (int) $db->query('SELECT COUNT(*) FROM patrons')->fetchColumn(),
        'bookings' => (int) $db->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
        'pending' => (int) $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn(),
        'payments' => (int) $db->query("SELECT COUNT(*) FROM membership_payments WHERE status = 'Submitted'")->fetchColumn(),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#12080e"><meta name="robots" content="noindex">
<title>Coordinator Console — Velvet &amp; Gold</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700&family=Manrope:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/vg.css?v=1">
</head>
<body class="admin">
<?php
$pill = static fn (string $s): string => ['Pending' => 'warn', 'Confirmed' => 'ok', 'Declined' => 'bad', 'Verified' => 'ok', 'Rejected' => 'bad', 'Submitted' => 'warn', 'Awaiting Payment' => 'dim'][$s] ?? '';
if (!$isAdmin): ?>
<div class="ad-login">
  <a class="brand" href="index.php" style="margin-bottom:1.6rem"><span class="brand-mark" aria-hidden="true">V</span><span class="brand-text"><span class="brand-name">Velvet &amp; Gold</span><span class="brand-sub">Coordinator console</span></span></a>
  <main class="panel auth-card">
  <?php if ($setupRequired): ?>
    <h1 style="font-size:2.2rem;margin-bottom:.8rem">Coordinator account not set up</h1>
    <p class="notice warn">For security, the first administrator must be created from the server command line. Run <code>php setup-admin.php &lt;username&gt;</code> in the application directory, then sign in here.</p>
  <?php else: ?>
    <h1 style="font-size:2.2rem;margin-bottom:.4rem">Coordinator sign in</h1>
    <p class="muted" style="margin-bottom:1.4rem">Restricted access.</p>
    <form id="authForm">
      <div class="field"><label for="username">Username</label><input id="username" name="username" required minlength="3" maxlength="50" autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-gold btn-block" type="submit">Sign in</button>
    </form>
  <?php endif; ?>
  </main>
  <p class="hint" style="margin-top:1.2rem;text-align:center"><a href="index.php">← Back to the member site</a></p>
</div>
<?php else: ?>
<div class="ad-shell">
  <aside class="ad-side">
    <a class="brand" href="admin.php"><span class="brand-mark" aria-hidden="true">V</span><span class="brand-text"><span class="brand-name">Velvet &amp; Gold</span><span class="brand-sub">Coordinator console</span></span></a>
    <nav class="ad-nav" data-spy aria-label="Console sections">
      <a href="#overview" class="on">Overview</a>
      <a href="#payments">Payments <span class="pip <?= $stats['payments'] ? '' : 'zero' ?>"><?= $stats['payments'] ?></span></a>
      <a href="#bookings">Requests <span class="pip <?= $stats['pending'] ? '' : 'zero' ?>" id="requestBadge"><?= $stats['pending'] ?></span></a>
      <a href="#members">Members <span class="pip zero"><?= $stats['patrons'] ?></span></a>
      <a href="#roster">Roster <span class="pip zero"><?= count($companions) ?></span></a>
    </nav>
    <div class="ad-user">
      <span>Signed in as <strong><?= app_escape($_SESSION['admin_username'] ?? 'Coordinator') ?></strong></span>
      <a class="btn btn-sm" href="index.php">Member site</a>
      <button class="btn btn-sm" id="logout" type="button">Sign out</button>
    </div>
  </aside>

  <main class="ad-main">
    <div class="ad-top" id="overview"><div><span class="kicker">Agency management</span><h1>Coordinator console</h1></div></div>
    <div class="ad-stats">
      <div class="stat"><strong><?= $stats['companions'] ?></strong><span>Active profiles</span></div>
      <div class="stat"><strong id="bookingTotal"><?= $stats['bookings'] ?></strong><span>Requests received</span></div>
      <div class="stat<?= $stats['pending'] ? ' alert' : '' ?>" id="pendingStat"><strong id="pendingTotal"><?= $stats['pending'] ?></strong><span>Awaiting response</span></div>
      <div class="stat"><strong><?= $stats['patrons'] ?></strong><span>Members</span></div>
      <div class="stat<?= $stats['payments'] ? ' alert' : '' ?>"><strong><?= $stats['payments'] ?></strong><span>Payments to verify</span></div>
    </div>

    <section class="ad-panel" id="payments" aria-labelledby="payTitle">
      <div class="hd"><h2 id="payTitle">UPI membership payments</h2></div>
      <p class="notice" style="margin-bottom:1rem">A submitted UTR is only a claim. Check the amount, status and reference in the merchant UPI or bank account before marking a payment verified.</p>
      <?php if (!$payments): ?><div class="empty"><strong>No payments yet</strong>Requests appear here after members start a UPI payment.</div><?php else: ?>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Member</th><th>Tier / amount</th><th>Payment ref</th><th>UTR</th><th>Submitted</th><th>Status / review</th></tr></thead><tbody>
        <?php foreach ($payments as $payment): ?>
        <tr>
          <td data-label="Member"><div><?= app_escape($payment['patron_name']) ?><span class="sub"><?= app_escape($payment['patron_email']) ?></span></div></td>
          <td data-label="Tier / amount"><div><?= app_escape($payment['tier']) ?><span class="sub">₹<?= number_format((int) $payment['amount_paise'] / 100, 2) ?></span></div></td>
          <td data-label="Payment ref"><span class="mono-ref"><?= app_escape($payment['payment_ref']) ?></span></td>
          <td data-label="UTR"><span class="mono-ref"><?= app_escape($payment['utr'] ?: 'Not submitted') ?></span></td>
          <td data-label="Submitted"><?= app_escape($payment['submitted_at'] ?: $payment['created_at']) ?></td>
          <td data-label="Status" class="<?= $payment['status'] === 'Submitted' ? 'cell-actions' : '' ?>">
            <span class="badge <?= $pill($payment['status']) ?>"><?= app_escape($payment['status']) ?></span>
            <?php if ($payment['status'] === 'Submitted'): ?>
            <form class="payment-review-form tbl-tools" style="margin-top:.6rem"><input type="hidden" name="id" value="<?= (int) $payment['id'] ?>"><select name="status" aria-label="Payment review decision"><option value="Verified">Verified in bank</option><option value="Rejected">Reject</option></select><button class="btn btn-gold btn-sm" type="submit">Save review</button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </section>

    <section class="ad-panel" id="bookings" aria-labelledby="bkTitle">
      <div class="hd"><h2 id="bkTitle">Booking requests</h2><span class="hint" id="bookingLiveStatus" aria-live="polite">Live updates on</span></div>
      <div id="bookingContent" aria-live="polite">
      <?php if (!$bookings): ?><div class="empty"><strong>No requests yet</strong>Member requests will show up here.</div><?php else: ?>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Member</th><th>Profile</th><th>Setting</th><th>Requested time</th><th>Status</th><th>Update</th></tr></thead><tbody>
        <?php foreach ($bookings as $b): ?>
        <tr>
          <td data-label="Member"><div><?= app_escape($b['patron_name']) ?><span class="sub"><?= app_escape($b['patron_id']) ?></span></div></td>
          <td data-label="Profile"><div><?= app_escape($b['companion_name']) ?><button class="btn btn-sm view-agent-profile" type="button" data-profile="<?= app_escape(json_encode([
              'name' => $b['companion_name'], 'title' => $b['companion_title'],
              'category' => $b['companion_category'], 'rate' => $b['companion_rate'],
              'overnight' => $b['companion_overnight'], 'location' => $b['companion_location'],
              'description' => $b['companion_description'], 'badge' => $b['companion_badge'],
              'photo' => $b['companion_photo'], 'id' => (int) $b['companion_id'],
          ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">View profile</button></div></td>
          <td data-label="Setting"><?= app_escape($b['setting']) ?></td>
          <td data-label="Requested"><div><?= app_escape($b['booking_date']) ?>, <?= app_escape($b['booking_time']) ?><span class="sub"><?= app_escape($b['duration']) ?></span></div></td>
          <td data-label="Status"><span class="badge <?= $pill($b['status']) ?>"><?= app_escape($b['status']) ?></span></td>
          <td data-label="Update"><form class="status-form tbl-tools"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><select name="status" aria-label="Request status"><option <?= $b['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option><option <?= $b['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option><option <?= $b['status'] === 'Declined' ? 'selected' : '' ?>>Declined</option></select><button class="btn btn-gold btn-sm" type="submit">Save</button></form></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
      </div>
    </section>

    <div class="modal" id="agentProfileModal" role="dialog" aria-modal="true" aria-labelledby="agentProfileName">
      <div class="modal-box">
        <div class="modal-head"><div><span class="kicker" id="agentProfileCategory"></span><h2 id="agentProfileName">Profile</h2></div><button class="close" type="button" data-agent-close aria-label="Close">&times;</button></div>
        <img id="agentProfilePhoto" alt="" style="display:none;width:100%;max-height:320px;object-fit:cover;border-radius:12px;margin-bottom:1rem">
        <p id="agentProfileTitle" class="lead"></p>
        <p id="agentProfileLocation" class="muted"></p>
        <p id="agentProfileBadge" class="badge" style="display:inline-flex"></p>
        <div class="panel" style="margin-top:1rem"><p id="agentProfileDescription"></p><dl class="dl"><div><dt>Hourly rate</dt><dd id="agentProfileRate"></dd></div><div><dt>Overnight</dt><dd id="agentProfileOvernight"></dd></div></dl></div>
        <a id="agentProfileLink" class="btn btn-gold btn-block" style="margin-top:1rem" href="#">Open full listing</a>
      </div>
    </div>

    <section class="ad-panel" id="members" aria-labelledby="mbTitle">
      <div class="hd"><h2 id="mbTitle">Member profiles</h2><div class="tbl-tools"><input id="memberSearch" type="search" placeholder="Search members" aria-label="Search members"></div></div>
      <?php if (!$patrons): ?><div class="empty"><strong>No members yet</strong>Members appear here after they register.</div><?php else: ?>
      <div class="tbl-wrap"><table class="tbl" id="memberTable"><thead><tr><th>Member</th><th>Contact</th><th>Membership</th><th>Private profile</th><th>Photos</th><th>Joined</th></tr></thead><tbody>
        <?php foreach ($patrons as $p): ?>
          <?php
          $details = [
              'Height' => $p['height_cm'] !== null ? $p['height_cm'] . ' cm' : '',
              'Figure' => $p['figure'], 'Hair' => $p['hair_color'], 'Skin tone' => $p['skin_tone'],
              'City' => $p['city'], 'State' => $p['state'], 'Country' => $p['country'],
              'Postal code' => $p['postal_code'], 'Interests' => $p['interests'],
              'Origin' => $p['origin'], 'Chest/bust' => $p['chest_size'], 'Penis size' => $p['penis_size'],
          ];
          ?>
        <tr>
          <td data-label="Member"><div><?= app_escape($p['name']) ?><span class="sub mono-ref"><?= app_escape($p['id']) ?></span></div></td>
          <td data-label="Contact"><div><?= app_escape($p['email']) ?><span class="sub"><?= app_escape($p['phone']) ?></span></div></td>
          <td data-label="Membership"><span class="badge"><?= app_escape($p['tier']) ?></span></td>
          <td data-label="Profile"><div class="kv"><?php $any = false; foreach ($details as $label => $value): if ($value !== null && $value !== ''): $any = true; ?><div><b><?= app_escape($label) ?>:</b> <?= app_escape($value) ?></div><?php endif; endforeach; if (!$any): ?><span class="faint">None provided</span><?php endif; ?></div></td>
          <td data-label="Photos"><div class="thumbs"><?php $n = 0; foreach ($patronPhotos as $photo): if ($photo['patron_id'] === $p['id']): $n++; ?><a href="photo.php?id=<?= (int) $photo['id'] ?>" target="_blank" rel="noopener"><img src="photo.php?id=<?= (int) $photo['id'] ?>" alt="Private member photo" loading="lazy"></a><?php endif; endforeach; if (!$n): ?><span class="faint">None</span><?php endif; ?></div></td>
          <td data-label="Joined"><?= app_escape($p['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </section>

    <section class="ad-panel" id="roster" aria-labelledby="rsTitle">
      <div class="hd"><h2 id="rsTitle">Companion profiles</h2><button class="btn btn-gold btn-sm" id="newCompanion" type="button">+ Add profile</button></div>
      <?php if (!$companions): ?><div class="empty"><strong>No profiles yet</strong>Add the first profile to publish it in the directory.</div><?php else: ?>
      <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Profile</th><th>Category</th><th>Rates</th><th>Location</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($companions as $c): ?>
        <tr>
          <td data-label="Profile"><div><strong><?= app_escape($c['name']) ?></strong><span class="sub"><?= app_escape($c['title']) ?></span></div></td>
          <td data-label="Category"><?= app_escape($c['category']) ?></td>
          <td data-label="Rates"><div><?= app_escape($c['rate']) ?><span class="sub"><?= app_escape($c['overnight']) ?> overnight</span></div></td>
          <td data-label="Location"><?= app_escape($c['location']) ?></td>
          <td data-label="Status"><span class="badge <?= $c['active'] ? 'ok' : 'dim' ?>"><?= $c['active'] ? 'Active' : 'Hidden' ?></span></td>
          <td data-label="Action"><div class="tbl-tools"><button class="btn btn-sm edit-profile" type="button" data-profile="<?= app_escape(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">Edit</button><button class="btn btn-sm toggle-profile" type="button" data-id="<?= (int) $c['id'] ?>"><?= $c['active'] ? 'Hide' : 'Activate' ?></button></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </section>
  </main>
</div>

<div class="drawer" id="profileEditor" role="dialog" aria-modal="true" aria-labelledby="editorTitle">
  <div class="scrim" data-drawer-close></div>
  <div class="sheet">
    <div class="hd"><h2 id="editorTitle">Add companion profile</h2><button class="close" type="button" data-drawer-close aria-label="Close">&times;</button></div>
    <form class="listing-form" id="profileForm">
      <input type="hidden" name="id" value="">
      <div class="field"><label for="f-name">Name</label><input id="f-name" name="name" required maxlength="160"></div>
      <div class="field"><label for="f-title">Title</label><input id="f-title" name="title" required maxlength="200"></div>
      <div class="field"><label for="f-cat">Category</label><input id="f-cat" name="category" required maxlength="100"></div>
      <div class="field"><label for="f-badge">Availability badge</label><input id="f-badge" name="badge" required maxlength="100"></div>
      <div class="field"><label for="f-rate">Hourly rate</label><input id="f-rate" name="rate" required maxlength="100"></div>
      <div class="field"><label for="f-over">Overnight rate</label><input id="f-over" name="overnight" required maxlength="100"></div>
      <div class="field"><label for="f-loc">Location</label><input id="f-loc" name="location" required maxlength="160"></div>
      <div class="field"><label for="f-photo">Photo URL (HTTPS)</label><input id="f-photo" name="photo" type="url" required maxlength="500"></div>
      <div class="field span2"><label for="f-desc">Description</label><textarea id="f-desc" name="description" required maxlength="500"></textarea></div>
      <div class="field span2" style="display:flex;gap:.7rem;flex-wrap:wrap"><button class="btn btn-gold" type="submit" style="flex:1">Save profile</button><button class="btn" id="clearEditor" type="button">Clear</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.VG_CFG={csrf:<?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>};</script>
<script src="assets/js/vg.js?v=1"></script>
<script>
const {api,toast}=window.VG;
const authForm=document.querySelector('#authForm');
if(authForm)authForm.addEventListener('submit',async event=>{event.preventDefault();const form=new FormData(authForm);try{await api({action:'login',username:form.get('username'),password:form.get('password')});location.reload();}catch(error){toast(error.message,true);}});
const agentModal=document.querySelector('#agentProfileModal');
if(agentModal){
 const closeAgent=()=>agentModal.classList.remove('open');
 document.addEventListener('click',event=>{
  const button=event.target.closest('.view-agent-profile');
  if(button){
   try{
    const p=JSON.parse(button.dataset.profile);
    document.querySelector('#agentProfileName').textContent=p.name||'Profile';
    document.querySelector('#agentProfileCategory').textContent=p.category||'';
    document.querySelector('#agentProfileTitle').textContent=p.title||'';
    document.querySelector('#agentProfileLocation').textContent=p.location||'';
    document.querySelector('#agentProfileBadge').textContent=p.badge||'';
    document.querySelector('#agentProfileDescription').textContent=p.description||'';
    document.querySelector('#agentProfileRate').textContent=p.rate||'—';
    document.querySelector('#agentProfileOvernight').textContent=p.overnight||'—';
    const photo=document.querySelector('#agentProfilePhoto');
    if(p.photo&&/^https:\/\//i.test(p.photo)){photo.src=p.photo;photo.style.display='block';}else{photo.removeAttribute('src');photo.style.display='none';}
    document.querySelector('#agentProfileLink').href=`listing.php?id=${encodeURIComponent(p.id)}`;
    agentModal.classList.add('open');
   }catch(error){toast('Could not open this profile.',true);}
  }else if(event.target===agentModal||event.target.closest('[data-agent-close]'))closeAgent();
 });
 document.addEventListener('keydown',event=>{if(event.key==='Escape')closeAgent();});
}
const bookingTotalEl=document.querySelector('#bookingTotal'),pendingTotalEl=document.querySelector('#pendingTotal');
if(bookingTotalEl&&pendingTotalEl){
 let knownTotal=Number(bookingTotalEl.textContent),knownPending=Number(pendingTotalEl.textContent),refreshing=false;
 const pollRequests=async()=>{
  if(refreshing)return;
  try{
   const data=await api({action:'admin-bookings-list'});
   if(data.total!==knownTotal){refreshing=true;document.querySelector('#bookingLiveStatus').textContent='New request received. Updating…';setTimeout(()=>location.reload(),700);return;}
   if(data.pending!==knownPending){knownPending=data.pending;pendingTotalEl.textContent=data.pending;const stat=document.querySelector('#pendingStat'),badge=document.querySelector('#requestBadge');stat.classList.toggle('alert',data.pending>0);badge.textContent=data.pending;badge.classList.toggle('zero',data.pending===0);}
  }catch(error){/* Keep the console usable if a poll briefly fails. */}
 };
 setInterval(pollRequests,5000);
}
const logout=document.querySelector('#logout');
if(logout)logout.addEventListener('click',async()=>{try{await api({action:'logout'});location.reload();}catch(error){toast(error.message,true);}});
const profileForm=document.querySelector('#profileForm');
if(profileForm){
 const drawer=document.querySelector('#profileEditor');
 const open=()=>{drawer.classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>profileForm.elements.name.focus(),50);};
 const close=()=>{drawer.classList.remove('open');document.body.style.overflow='';};
 const clear=()=>{profileForm.reset();profileForm.elements.id.value='';document.querySelector('#editorTitle').textContent='Add companion profile';};
 drawer.querySelectorAll('[data-drawer-close]').forEach(el=>el.addEventListener('click',close));
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&drawer.classList.contains('open'))close();});
 document.querySelector('#clearEditor').addEventListener('click',clear);
 document.querySelector('#newCompanion').addEventListener('click',()=>{clear();open();});
 document.querySelectorAll('.edit-profile').forEach(button=>button.addEventListener('click',()=>{clear();const profile=JSON.parse(button.dataset.profile);for(const [key,value] of Object.entries(profile)){if(profileForm.elements[key])profileForm.elements[key].value=value;}document.querySelector('#editorTitle').textContent='Edit companion profile';open();}));
 profileForm.addEventListener('submit',async event=>{event.preventDefault();const values=Object.fromEntries(new FormData(profileForm));try{await api({action:'admin-companion-save',...values});location.reload();}catch(error){toast(error.message,true);}});
 document.querySelectorAll('.toggle-profile').forEach(button=>button.addEventListener('click',async()=>{try{await api({action:'admin-companion-toggle',id:button.dataset.id});location.reload();}catch(error){toast(error.message,true);}}));
 document.querySelectorAll('.status-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const values=Object.fromEntries(new FormData(form));try{await api({action:'admin-booking-status',...values});location.reload();}catch(error){toast(error.message,true);}}));
 document.querySelectorAll('.payment-review-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const values=Object.fromEntries(new FormData(form));try{await api({action:'admin-payment-review',...values});location.reload();}catch(error){toast(error.message,true);}}));
 const ms=document.querySelector('#memberSearch'),mt=document.querySelector('#memberTable');
 if(ms&&mt)ms.addEventListener('input',()=>{const t=ms.value.trim().toLowerCase();mt.querySelectorAll('tbody tr').forEach(r=>{r.hidden=t&&!r.textContent.toLowerCase().includes(t);});});
}
</script>
</body>
</html>
