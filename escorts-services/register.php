<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$patron = vg_patron($db);
if ($patron) {
    header('Location: dashboard.php');
    exit;
}
$prices = app_membership_prices();
$upiOn = (bool) app_upi_config();
$preselect = (string) ($_GET['tier'] ?? 'Velvet Pass');
if (!isset($prices[$preselect])) {
    $preselect = 'Velvet Pass';
}
$tierCopy = ['Velvet Pass' => 'Private member access', 'Gold Reserve' => 'Enhanced member access', 'Elite VIP' => 'Premium member access'];

vg_head(['title' => 'Become a member — Velvet & Gold', 'active' => 'register', 'patron' => null]);
?>
<div class="wrap auth-wrap">
  <div class="wizard">
    <h1>Become a member</h1>
    <p class="lead" style="margin-bottom:2rem">Four short steps. Only your name, email and phone are required.</p>

    <form id="registerForm" novalidate>
      <ol class="progress" aria-label="Sign-up progress">
        <li class="active" data-marker="1"><span class="n">1</span><span>Membership</span></li>
        <li data-marker="2"><span class="n">2</span><span>Your details</span></li>
        <li data-marker="3"><span class="n">3</span><span>Profile</span></li>
        <li data-marker="4"><span class="n">4</span><span>Review</span></li>
      </ol>

      <section class="wstep active" data-step="1">
        <h2>Choose your membership</h2>
        <p class="lead">A one-time fee, paid by UPI after sign-up.</p>
        <div class="tier-pick">
          <?php foreach ($prices as $name => $price): ?>
          <label class="tier-opt"><input type="radio" name="tier" value="<?= app_escape($name) ?>"<?= $name === $preselect ? ' checked' : '' ?>><span><span class="t-name"><?= app_escape($name) ?></span><span class="t-desc"><?= app_escape($tierCopy[$name] ?? '') ?></span></span><span class="t-price"><?= vg_money($price) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="actions"><button class="btn btn-gold" type="button" data-next="2">Continue</button></div>
      </section>

      <section class="wstep" data-step="2">
        <h2>Your contact details</h2>
        <p class="lead">The coordinator uses these to respond to your requests.</p>
        <div class="field"><label for="regName">Name or preferred name</label><input id="regName" name="name" maxlength="100" required autocomplete="name"></div>
        <div class="field"><label for="regEmail">Email</label><input id="regEmail" name="email" type="email" maxlength="254" required autocomplete="email" inputmode="email"></div>
        <div class="field"><label for="regPhone">Phone</label><input id="regPhone" name="phone" type="tel" maxlength="40" required autocomplete="tel" inputmode="tel"></div>
        <div class="field"><label for="regPassword">Create password</label><input id="regPassword" name="password" type="password" minlength="10" maxlength="128" required autocomplete="new-password"><span class="hint">Use at least 10 characters. You’ll use this with your email to sign in later.</span></div>
        <div class="field"><label for="regPasswordConfirm">Confirm password</label><input id="regPasswordConfirm" name="password_confirm" type="password" minlength="10" maxlength="128" required autocomplete="new-password"></div>
        <div class="actions"><button class="btn" type="button" data-prev="1">Back</button><button class="btn btn-gold" type="button" data-next="3">Continue</button></div>
      </section>

      <section class="wstep" data-step="3">
        <h2>Your profile</h2>
        <p class="lead">Everything here is optional. Skip whatever you'd rather not share.</p>
        <fieldset class="group"><legend>About you</legend>
          <div class="form-grid">
            <div class="field"><label for="profileHeight">Height (cm)</label><input id="profileHeight" name="height_cm" type="number" min="100" max="250" inputmode="numeric"></div>
            <div class="field"><label for="profileFigure">Figure / physique</label><select id="profileFigure" name="figure"><option value="">Prefer not to say</option><option>Slim</option><option>Average</option><option>Athletic</option><option>Curvy</option><option>Muscular</option><option>Other</option></select></div>
            <div class="field"><label for="profileHair">Hair color</label><input id="profileHair" name="hair_color" maxlength="60"></div>
            <div class="field"><label for="profileInterests">Interests</label><input id="profileInterests" name="interests" maxlength="300"></div>
          </div>
        </fieldset>
        <fieldset class="group"><legend>Location</legend>
          <div class="form-grid">
            <div class="field"><label for="profileCity">City</label><input id="profileCity" name="city" maxlength="100" autocomplete="address-level2"></div>
            <div class="field"><label for="profileState">State / region</label><input id="profileState" name="state" maxlength="100" autocomplete="address-level1"></div>
            <div class="field"><label for="profileCountry">Country</label><input id="profileCountry" name="country" maxlength="100" autocomplete="country-name"></div>
            <div class="field"><label for="profilePostal">Postal / PIN code</label><input id="profilePostal" name="postal_code" maxlength="24" autocomplete="postal-code"></div>
          </div>
        </fieldset>
        <fieldset class="group sensitive"><legend>Sensitive details</legend>
          <p class="hint" style="margin:-.2rem 0 1rem">Private to you and the coordinator. Stored only if you tick the consent box below.</p>
          <div class="form-grid">
            <div class="field"><label for="profileSkinTone">Skin tone</label><input id="profileSkinTone" name="skin_tone" maxlength="60"></div>
            <div class="field"><label for="profileOrigin">Origin / background</label><input id="profileOrigin" name="origin" maxlength="100"></div>
            <div class="field"><label for="profileChest">Chest / bust size</label><input id="profileChest" name="chest_size" maxlength="50"></div>
            <div class="field"><label for="profilePenis">Penis size</label><input id="profilePenis" name="penis_size" maxlength="50"></div>
          </div>
          <label class="check" style="margin-bottom:1rem"><input type="checkbox" name="sensitive_data_consent" value="1"><span>I consent to storing the sensitive details above for coordinator review. They stay private and are never published in the directory.</span></label>
        </fieldset>
        <div class="field">
          <label for="profilePhotos">Photos (up to 3)</label>
          <input id="profilePhotos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
          <span class="hint">JPEG, PNG or WebP, up to 5 MB each. Photos are resized and stored privately.</span>
          <div class="previews" id="photoPreviews"></div>
        </div>
        <div class="actions"><button class="btn" type="button" data-prev="2">Back</button><button class="btn btn-gold" type="button" data-next="4">Review</button></div>
      </section>

      <section class="wstep" data-step="4">
        <h2>Review your request</h2>
        <p class="lead">Check everything before creating your member profile.</p>
        <div class="review" id="review"></div>
        <div class="notice" style="margin-top:1.2rem"><?= $upiOn ? 'After you create your profile you\'ll go straight to the UPI payment page.' : 'Payment is not set up yet, so you won\'t be asked to pay now.' ?> Never enter card details, government ID numbers or one-time codes on this site.</div>
        <div class="actions"><button class="btn" type="button" data-prev="3">Back</button><button class="btn btn-gold" type="submit" id="createBtn">Create member profile</button></div>
      </section>
    </form>
    <p class="hint" style="margin-top:1.6rem;text-align:center">Coordinator? <a href="login.php">Sign in here</a>.</p>
  </div>
</div>
<?php
vg_foot(['script' => 'window.VG_PRICES=' . json_encode($prices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';' . <<<'JS'
(()=>{
  const {$,$$,api,toast,cfg}=window.VG;
  const form=$('#registerForm'); const steps=$$('[data-step]',form), marks=$$('[data-marker]',form);
  const go=(n)=>{
    steps.forEach(s=>s.classList.toggle('active',+s.dataset.step===n));
    marks.forEach(m=>{const k=+m.dataset.marker;m.classList.toggle('active',k===n);m.classList.toggle('done',k<n);m.querySelector('.n').textContent=k<n?'✓':k;});
    if(n===4) buildReview();
    window.scrollTo({top:form.getBoundingClientRect().top+scrollY-110,behavior:'smooth'});
  };
  const buildReview=()=>{
    const v=new FormData(form), consent=!!v.get('sensitive_data_consent'), np='Not provided';
    const rows=[['Membership',v.get('tier')],['Amount','₹'+Number(window.VG_PRICES[v.get('tier')]).toLocaleString('en-IN')],['Name',v.get('name')],['Email',v.get('email')],['Phone',v.get('phone')],
      ['Height',v.get('height_cm')?v.get('height_cm')+' cm':np],['Figure / physique',v.get('figure')||np],['Hair color',v.get('hair_color')||np],
      ['Location',[v.get('city'),v.get('state'),v.get('country'),v.get('postal_code')].filter(Boolean).join(', ')||np],['Interests',v.get('interests')||np],
      ['Skin tone',consent?(v.get('skin_tone')||np):'Not shared'],['Origin / background',consent?(v.get('origin')||np):'Not shared'],
      ['Chest / bust size',consent?(v.get('chest_size')||np):'Not shared'],['Penis size',consent?(v.get('penis_size')||np):'Not shared'],
      ['Photos',$('#profilePhotos').files.length+' selected']];
    const box=$('#review'); box.replaceChildren();
    rows.forEach(([k,val])=>{const r=document.createElement('div');r.className='row';const a=document.createElement('span');a.textContent=k;const b=document.createElement('strong');b.textContent=val;r.append(a,b);box.append(r);});
  };
  $$('[data-next]',form).forEach(b=>b.addEventListener('click',()=>{
    const cur=b.closest('[data-step]');
    const bad=$$('input[required]',cur).find(f=>!f.checkValidity());
    if(bad){bad.reportValidity();return;}
    if(+b.dataset.next===4){
      if($('#profilePhotos').files.length>3){toast('Choose no more than three photos.',true);return;}
      const sens=['skin_tone','origin','chest_size','penis_size'].some(n=>String(new FormData(form).get(n)||'').trim()!=='');
      if(sens&&!form.elements.sensitive_data_consent.checked){toast('Tick the consent box to share sensitive details, or leave those fields blank.',true);return;}
    }
    go(+b.dataset.next);
  }));
  $$('[data-prev]',form).forEach(b=>b.addEventListener('click',()=>go(+b.dataset.prev)));
  $('#profilePhotos').addEventListener('change',e=>{
    const files=[...e.target.files], box=$('#photoPreviews'); box.replaceChildren();
    if(files.length>3){e.target.value='';toast('Choose no more than three photos.',true);return;}
    for(const f of files){
      if(!['image/jpeg','image/png','image/webp'].includes(f.type)||f.size>5*1024*1024){e.target.value='';box.replaceChildren();toast('Choose JPEG, PNG or WebP images under 5 MB each.',true);return;}
      const img=document.createElement('img');img.alt='Selected photo';img.src=URL.createObjectURL(f);box.append(img);
    }
  });
  form.addEventListener('submit',async e=>{
    e.preventDefault();
    const btn=$('#createBtn'); btn.disabled=true;
    const data=new FormData(form); data.set('action','register'); data.set('csrf',cfg.csrf);
    try{
      const res=await fetch('api.php',{method:'POST',body:data}); const out=await res.json().catch(()=>({}));
      if(!res.ok) throw new Error(out.error||'The request could not be completed.');
      if(cfg.upiOn){ try{ await api({action:'payment-start'}); }catch(err){ toast('Profile created, but payment could not be started: '+err.message,true); } }
      location.href='payment.php';
    }catch(err){ btn.disabled=false; toast(err.message,true); }
  });
})();
JS]);
