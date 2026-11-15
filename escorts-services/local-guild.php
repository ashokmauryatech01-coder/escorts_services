<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
vg_head(['title' => 'Velvet & Gold — Portal', 'patron' => vg_patron(app_db()), 'gate' => false]);
?>
<div class="wrap auth-wrap">
  <h1>Welcome</h1>
  <p class="lead">Choose where you're headed. The coordinator console needs an administrator account.</p>
  <div class="portal">
    <a href="index.php"><strong>Member site</strong><span>Browse profiles, join, and manage requests</span></a>
    <a href="admin.php"><strong>Coordinator console</strong><span>Manage profiles, payments and requests</span></a>
  </div>
</div>
<?php vg_foot();
