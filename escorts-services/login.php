<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();
$patron = vg_patron($db);
$isAdmin = !empty($_SESSION['admin_id']);
$setupRequired = !app_admin_exists();

vg_head(['title' => 'Sign in — Velvet & Gold', 'active' => 'login', 'patron' => $patron]);
?>
<div class="wrap auth-wrap">
  <h1>Sign in</h1>
  <p class="lead" style="margin-bottom:2rem">Members and coordinators use different doors.</p>
  <div class="auth-grid">
    <section class="panel auth-card" aria-labelledby="mTitle">
      <h2 id="mTitle">Members</h2>
      <?php if ($patron): ?>
        <p>You're signed in as <strong><?= app_escape($patron['name']) ?></strong>.</p>
        <a class="btn btn-gold btn-block" href="dashboard.php">Open my lounge</a>
        <button class="btn btn-block" type="button" data-member-logout style="margin-top:.7rem">Sign out</button>
      <?php else: ?>
        <p>Sign in with the email and password you chose when you joined.</p>
        <form id="memberAuthForm">
          <div class="field"><label for="memberEmail">Email</label><input id="memberEmail" name="email" type="email" required autocomplete="email"></div>
          <div class="field"><label for="memberPassword">Password</label><input id="memberPassword" name="password" type="password" required autocomplete="current-password"></div>
          <button class="btn btn-gold btn-block" type="submit" id="memberSignBtn">Sign in</button>
        </form>
        <p class="hint" style="margin-top:1rem">New here? <a href="register.php">Create a member account</a>.</p>
      <?php endif; ?>
    </section>

    <section class="panel auth-card" aria-labelledby="cTitle">
      <h2 id="cTitle">Coordinator</h2>
      <?php if ($isAdmin): ?>
        <p>You're signed in as <strong><?= app_escape($_SESSION['admin_username'] ?? 'Coordinator') ?></strong>.</p>
        <a class="btn btn-gold btn-block" href="admin.php">Open the console</a>
      <?php elseif ($setupRequired): ?>
        <div class="notice warn">No coordinator account exists yet. Create the first one from the server command line:<br><code>php setup-admin.php &lt;username&gt;</code></div>
      <?php else: ?>
        <p>Restricted access for managing profiles, payments and requests.</p>
        <form id="authForm">
          <div class="field"><label for="username">Username</label><input id="username" name="username" required minlength="3" maxlength="50" autocomplete="username"></div>
          <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
          <button class="btn btn-gold btn-block" type="submit" id="signBtn">Sign in</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php
vg_foot(['script' => <<<'JS'
(()=>{
  const f=document.getElementById('authForm'); if(!f) return;
  f.addEventListener('submit',async e=>{
    e.preventDefault(); const b=document.getElementById('signBtn'); b.disabled=true;
    const v=new FormData(f);
    try{ await VG.api({action:'admin-login',username:v.get('username'),password:v.get('password')}); location.href='admin.php'; }
    catch(err){ b.disabled=false; VG.toast(err.message,true); }
  });
})();
(()=>{
  const f=document.getElementById('memberAuthForm'); if(!f) return;
  f.addEventListener('submit',async e=>{
    e.preventDefault(); const b=document.getElementById('memberSignBtn'); b.disabled=true;
    const v=new FormData(f);
    try{ await VG.api({action:'member-login',email:v.get('email'),password:v.get('password')}); location.href='dashboard.php'; }
    catch(err){ b.disabled=false; VG.toast(err.message,true); }
  });
})();
JS]);
