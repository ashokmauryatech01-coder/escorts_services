<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$patron = vg_patron(app_db());
vg_head(['title' => 'Agent sign in — Velvet & Gold', 'active' => 'agent', 'patron' => $patron]);
?>
<div class="wrap auth-wrap">
  <section class="panel auth-card" style="max-width:480px;margin:0 auto">
    <h1>Agent sign in</h1>
    <p class="lead" style="margin-bottom:1.5rem">Check your application and subscription status.</p>
    <form id="agentLoginForm">
      <div class="field"><label for="agentLoginEmail">Email</label><input id="agentLoginEmail" name="email" type="email" required autocomplete="email"></div>
      <div class="field"><label for="agentLoginPassword">Password</label><input id="agentLoginPassword" name="password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-gold btn-block" type="submit" id="agentLoginBtn">Sign in</button>
    </form>
    <p class="hint" style="margin-top:1rem">New agent? <a href="agent-register.php">Apply here</a>.</p>
  </section>
</div>
<?php
vg_foot(['script' => <<<'JS'
(()=>{
 const {api,toast}=window.VG,form=document.querySelector('#agentLoginForm'),button=document.querySelector('#agentLoginBtn');
 form.addEventListener('submit',async event=>{event.preventDefault();button.disabled=true;const values=Object.fromEntries(new FormData(form));
  try{await api({action:'agent-login',...values});location.href='agent-account.php';}
  catch(error){button.disabled=false;toast(error.message,true);}
 });
})();
JS]);
