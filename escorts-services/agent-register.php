<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$patron = vg_patron($db);
$defaultPhoto = 'https://drive.google.com/thumbnail?id=1XkU45-woMLNPnKJrUCocBP3ZRNHGUKbU&sz=w1000';
$agentPlans = app_agent_plans();
vg_head(['title' => 'Apply as an agent — Velvet & Gold', 'active' => 'agent', 'patron' => $patron]);
?>
<div class="wrap auth-wrap">
  <h1>Apply to join as an agent</h1>
  <p class="lead" style="margin-bottom:1.5rem">Send us your proposed public profile. The coordinator reviews applications before a profile appears in the directory.</p>
  <div class="notice" style="margin-bottom:1.5rem"><strong>One-time subscription:</strong> Select your plan below. Pay by UPI only after your application is approved. The coordinator verifies payment before publishing your profile.</div>
  <form id="agentApplyForm" class="panel">
    <fieldset class="group"><legend>Contact details</legend>
      <div class="form-grid">
        <div class="field"><label for="agentName">Display name</label><input id="agentName" name="name" required maxlength="100" autocomplete="name"></div>
        <div class="field"><label for="agentEmail">Email</label><input id="agentEmail" name="email" type="email" required maxlength="254" autocomplete="email"></div>
        <div class="field"><label for="agentPhone">Phone</label><input id="agentPhone" name="phone" type="tel" required maxlength="40" autocomplete="tel"></div>
        <div class="field"><label for="agentPassword">Create password</label><input id="agentPassword" name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password"></div>
        <div class="field"><label for="agentPasswordConfirm">Confirm password</label><input id="agentPasswordConfirm" name="password_confirm" type="password" required minlength="10" maxlength="128" autocomplete="new-password"></div>
      </div>
    </fieldset>
    <fieldset class="group"><legend>Choose your agent subscription</legend>
      <div class="tier-pick">
        <?php foreach ($agentPlans as $plan => $price): ?>
        <label class="tier-opt"><input type="radio" name="plan" value="<?= app_escape($plan) ?>" required><span><span class="t-name"><?= app_escape($plan) ?></span><span class="t-desc">Agent listing subscription · one-time</span></span><span class="t-price"><?= vg_money($price) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="group"><legend>Proposed listing</legend>
      <div class="form-grid">
        <div class="field"><label for="agentTitle">Profile title</label><input id="agentTitle" name="title" required maxlength="200" placeholder="e.g. Dining and events companion"></div>
        <div class="field"><label for="agentCategory">Category</label><input id="agentCategory" name="category" required maxlength="100" placeholder="e.g. Dinner & Events"></div>
        <div class="field"><label for="agentRate">Hourly rate</label><input id="agentRate" name="rate" required maxlength="100" placeholder="e.g. ₹4,000/hr"></div>
        <div class="field"><label for="agentOvernight">Overnight rate</label><input id="agentOvernight" name="overnight" required maxlength="100" placeholder="e.g. ₹20,000"></div>
        <div class="field"><label for="agentLocation">Service location</label><input id="agentLocation" name="location" required maxlength="160" autocomplete="address-level2"></div>
        <div class="field"><label for="agentPhoto">Profile photo URL (HTTPS)</label><input id="agentPhoto" name="photo" type="url" required maxlength="500" value="<?= app_escape($defaultPhoto) ?>"><span class="hint">Use an image link that can be viewed publicly.</span></div>
      </div>
      <div class="field"><label for="agentDescription">About your services</label><textarea id="agentDescription" name="description" required maxlength="500" rows="4"></textarea></div>
    </fieldset>
    <label class="check"><input type="checkbox" name="adult_confirm" value="1" required><span>I confirm I am 18 years of age or older and agree that my proposed listing is reviewed before publication.</span></label>
    <button class="btn btn-gold btn-block" type="submit" id="agentApplyBtn">Send application</button>
  </form>
  <p class="hint" style="margin-top:1rem;text-align:center">Already applied? <a href="agent-login.php">Agent sign in</a>. Member? <a href="login.php">Member sign in</a>.</p>
</div>
<?php
vg_foot(['script' => <<<'JS'
(()=>{
 const {api,toast}=window.VG,form=document.querySelector('#agentApplyForm'),button=document.querySelector('#agentApplyBtn');
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!form.reportValidity())return;button.disabled=true;
  const values=Object.fromEntries(new FormData(form));
  try{const result=await api({action:'agent-apply',...values});const box=document.createElement('div');box.className='empty';const heading=document.createElement('strong');heading.textContent='Application received';const message=document.createElement('p');message.textContent=result.message;const link=document.createElement('a');link.className='btn btn-gold';link.href='agent-account.php';link.textContent='View application status';box.append(heading,message,link);form.replaceChildren(box);}
  catch(error){button.disabled=false;toast(error.message,true);}
 });
})();
JS]);
