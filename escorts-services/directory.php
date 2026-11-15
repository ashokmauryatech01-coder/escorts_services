<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/inc/layout.php';
app_start_session();
$db = app_db();

// $companions = $db->query('SELECT * FROM companions WHERE active = 1 ORDER BY id DESC')->fetchAll();
// $patron = vg_patron($db);
// $payment = $patron ? vg_latest_payment($db, $patron['id']) : null;
// $categories = array_values(array_unique(array_column($companions, 'category')));
// sort($categories);

// vg_head([
//     'title' => 'Directory — Velvet & Gold',
//     'active' => 'directory',
//     'patron' => $patron,
//     'cfg' => ['paymentVerified' => $payment && $payment['status'] === 'Verified'],
// ]);

// Fetch ALL companion records
$companions = $db->query(
    'SELECT * FROM companions ORDER BY id DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$patron = vg_patron($db);
$payment = $patron ? vg_latest_payment($db, $patron['id']) : null;

$categories = array_values(
    array_unique(array_column($companions, 'category'))
);

sort($categories);

vg_head([
    'title' => 'Directory — Velvet & Gold',
    'active' => 'directory',
    'patron' => $patron,
    'cfg' => [
        'paymentVerified' => $payment && $payment['status'] === 'Verified'
    ],
]);
?>

<div class="wrap">
  <div class="page-head">
    <h1>The directory</h1>
    <p class="lead">Every profile below is currently open to requests. Select a profile for full details.</p>
  </div>

  <?php if (!$companions): ?>
    <div class="empty"><strong>No active listings right now</strong>The coordinator is updating the directory. Please check back soon.</div>
  <?php else: ?>
    <?php if ($patron === null): ?>
      <div class="notice" style="margin-bottom:1.4rem"><strong>Browsing as a guest.</strong> <a href="register.php">Become a member</a> to send requests.</div>
    <?php elseif (app_upi_config() && !($payment && $payment['status'] === 'Verified')): ?>
      <div class="notice warn" style="margin-bottom:1.4rem"><strong>Requests are locked.</strong> They unlock once your membership payment is verified. <a href="payment.php">Check payment status</a></div>
    <?php endif; ?>

    <form class="filters" id="filters" role="search" onsubmit="return false">
      <label class="sr-only" for="q">Search profiles</label>
      <input class="input" id="q" type="search" placeholder="Search by name, place or style" autocomplete="off">
      <label class="sr-only" for="loc">Location</label>
      <select class="input" id="loc"><option value="">All locations</option>
        <?php foreach (array_unique(array_column($companions, 'location')) as $loc): ?><option><?= app_escape($loc) ?></option><?php endforeach; ?>
      </select>
      <label class="sr-only" for="sort">Sort</label>
      <select class="input" id="sort"><option value="new">Newest first</option><option value="name">Name A–Z</option></select>
    </form>
    <?php if (count($categories) > 1): ?>
    <div class="chips" id="chips" role="group" aria-label="Filter by category">
      <button class="chip" type="button" aria-pressed="true" data-cat="">All</button>
      <?php foreach ($categories as $cat): ?><button class="chip" type="button" aria-pressed="false" data-cat="<?= app_escape($cat) ?>"><?= app_escape($cat) ?></button><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="result-count" id="count" aria-live="polite"></div>
    <div class="grid-cards" id="grid">
      <?php foreach ($companions as $c): ?>
        <?php vg_profile_card($c, true); ?>
      <?php endforeach; ?>
    </div>
    <div class="empty" id="none" hidden><strong>No profiles match</strong>Try a different search or clear the filters.<br><button class="btn" id="reset" type="button">Clear filters</button></div>
  <?php endif; ?>
</div>

<?php
vg_booking_modal();
vg_foot(['script' => <<<'JS'
(()=>{
  const grid=document.getElementById('grid'); if(!grid) return;
  const cards=[...grid.children], q=document.getElementById('q'), loc=document.getElementById('loc'), sort=document.getElementById('sort');
  const chips=[...document.querySelectorAll('#chips .chip')], count=document.getElementById('count'), none=document.getElementById('none');
  let cat='';
  const locOf=c=>c.querySelector('.pcard-loc').textContent.trim();
  const apply=()=>{
    const term=q.value.trim().toLowerCase(); let shown=0;
    cards.forEach(c=>{
      const ok=(!cat||c.dataset.cat===cat)&&(!loc.value||locOf(c)===loc.value)&&(!term||c.dataset.name.includes(term));
      c.hidden=!ok; if(ok) shown++;
    });
    const ordered=[...cards].sort((a,b)=>sort.value==='name'?a.dataset.label.localeCompare(b.dataset.label):Number(b.dataset.id)-Number(a.dataset.id));
    ordered.forEach(c=>grid.appendChild(c));
    count.textContent=`${shown} of ${cards.length} profile${cards.length===1?'':'s'}`;
    none.hidden=shown!==0;
  };
  chips.forEach(ch=>ch.addEventListener('click',()=>{cat=ch.dataset.cat;chips.forEach(x=>x.setAttribute('aria-pressed',x===ch));apply();}));
  [q,loc,sort].forEach(el=>{el.addEventListener('input',apply);el.addEventListener('change',apply);});
  document.getElementById('reset').addEventListener('click',()=>{q.value='';loc.value='';cat='';chips.forEach((x,i)=>x.setAttribute('aria-pressed',i===0));apply();});
  apply();
})();
JS]);
