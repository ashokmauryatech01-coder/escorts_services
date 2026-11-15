<?php
declare(strict_types=1);
/**
 * Shared page chrome for the public / member pages: <head>, age gate, header, footer, scripts.
 * Presentation only — data access stays in each page and in app.php / api.php.
 */

const VG_FONTS = 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Manrope:wght@400;500;600;700;800&display=swap';

/** Current member (same query the old index.php used). */
function vg_patron(PDO $db): ?array
{
    if (empty($_SESSION['patron_id'])) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM patrons WHERE id = ?');
    $stmt->execute([$_SESSION['patron_id']]);
    $patron = $stmt->fetch() ?: null;
    if (!$patron) {
        unset($_SESSION['patron_id']);
    }
    return $patron;
}

/** Latest membership payment row for a member (same query the old index.php used). */
function vg_latest_payment(PDO $db, string $patronId): ?array
{
    $stmt = $db->prepare('SELECT * FROM membership_payments WHERE patron_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$patronId]);
    return $stmt->fetch() ?: null;
}

function vg_money(int|float $rupees): string
{
    return '₹' . number_format((float) $rupees);
}

function vg_initial(string $name): string
{
    return mb_strtoupper(mb_substr(trim($name) ?: 'V', 0, 1));
}

/**
 * Opening markup. $o: title, active, patron, gate (bool), robots, body, desc, cfg (extra JS config)
 */
function vg_head(array $o): void
{
    $title = $o['title'] ?? 'Velvet & Gold';
    $patron = $o['patron'] ?? null;
    $active = $o['active'] ?? '';
    $gate = $o['gate'] ?? true;
    $desc = $o['desc'] ?? 'Velvet & Gold is a private, adults-only companion collective. Browse curated profiles and send a discreet request.';
    $cfg = array_merge([
        'csrf' => app_csrf_token(),
        'isMember' => (bool) $patron,
        'upiOn' => (bool) app_upi_config(),
        'paymentVerified' => false,
    ], $o['cfg'] ?? []);
    $link = static fn (string $key, string $href, string $label): string =>
        '<a href="' . $href . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#12080e">
<meta name="description" content="<?= app_escape($desc) ?>">
<?php if (!empty($o['robots'])): ?><meta name="robots" content="<?= app_escape($o['robots']) ?>">
<?php endif; ?>
<title><?= app_escape($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= VG_FONTS ?>">
<link rel="stylesheet" href="assets/css/vg.css?v=2">
<script>try{if(sessionStorage.getItem('vg_age_confirmed')==='yes')document.documentElement.classList.add('age-ok')}catch(e){}</script>
</head>
<body class="<?= app_escape($o['body'] ?? '') ?>">
<a class="skip" href="#main">Skip to content</a>
<?php if ($gate): ?>
<div class="age-gate" id="ageGate" role="dialog" aria-modal="true" aria-labelledby="ageTitle"><div class="age-box">
  <div class="brand-mark">18+</div>
  <h2 id="ageTitle">Adults only</h2>
  <p>Velvet &amp; Gold is for visitors aged 18 and over. Please confirm your age to continue.</p>
  <button class="btn btn-gold btn-block" id="ageConfirm" type="button">I am 18 or older</button>
  <a class="leave" href="https://www.google.com">Leave this site</a>
</div></div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap nav">
    <a class="brand" href="index.php" aria-label="Velvet &amp; Gold home">
      <span class="brand-mark" aria-hidden="true">V</span>
      <span class="brand-text"><span class="brand-name">Velvet &amp; Gold</span><span class="brand-sub">Private companion collective</span></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="navLinks" aria-label="Menu"><span></span></button>
    <nav class="nav-links" id="navLinks" aria-label="Main">
      <?= $link('directory', 'directory.php', 'Directory') ?>
      <?= $link('membership', 'membership.php', 'Membership') ?>
      <?= $link('how', 'index.php#how', 'How it works') ?>
      <?php if ($patron): ?>
        <?= $link('dashboard', 'dashboard.php', 'My lounge') ?>
        <a class="btn btn-gold" href="dashboard.php"><?= app_escape(vg_initial($patron['name'])) ?> · Dashboard</a>
      <?php else: ?>
        <?= $link('login', 'login.php', 'Sign in') ?>
        <a class="btn btn-gold" href="register.php">Become a member</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main id="main">
<script>window.VG_CFG=<?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php
}

function vg_foot(array $o = []): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">V</span><span class="brand-text"><span class="brand-name">Velvet &amp; Gold</span></span></a>
        <p class="foot-note" style="margin-top:1rem">An adults-only introduction service. Requests are subject to availability and coordinator confirmation. We never ask for card details, government ID numbers or one-time codes.</p>
      </div>
      <div>
        <h4>Explore</h4>
        <ul><li><a href="directory.php">Directory</a></li><li><a href="membership.php">Membership</a></li><li><a href="index.php#how">How it works</a></li><li><a href="index.php#faq">Questions</a></li></ul>
      </div>
      <div>
        <h4>Account</h4>
        <ul><li><a href="register.php">Become a member</a></li><li><a href="dashboard.php">My lounge</a></li><li><a href="login.php">Sign in</a></li><li><a href="admin.php">Coordinator console</a></li></ul>
      </div>
    </div>
    <div class="foot-base"><span>© <?= date('Y') ?> Velvet &amp; Gold. Adults 18+ only.</span><span>Profiles and payments are reviewed by a person.</span></div>
  </div>
</footer>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<?php if (!empty($o['extra'])) echo $o['extra']; ?>
<script src="assets/js/vg.js?v=1"></script>
<?php if (!empty($o['script'])): ?><script><?= $o['script'] ?></script><?php endif; ?>
</body>
</html>
<?php
}

/** Booking request modal, shared by directory and listing pages. */
function vg_booking_modal(): void
{
    ?>
<div class="modal" id="bookingModal" role="dialog" aria-modal="true" aria-labelledby="bookingTitle"><div class="modal-box">
  <div class="modal-head"><div><span class="kicker">Private request</span><h2 id="bookingTitle">Send a request</h2></div><button class="close" type="button" data-close aria-label="Close">&times;</button></div>
  <form id="bookingForm">
    <input type="hidden" name="companion_id" id="bookingCompanionId">
    <div class="field"><label for="bookingSetting">Preferred setting</label><select id="bookingSetting" name="setting"><option>Luxury hotel or suite</option><option>Restaurant or event</option><option>Private studio</option><option>Other agreed location</option></select></div>
    <div class="form-grid">
      <div class="field"><label for="bookingDate">Preferred date</label><input type="date" id="bookingDate" name="date" min="<?= date('Y-m-d') ?>" required></div>
      <div class="field"><label for="bookingTime">Preferred time</label><input type="time" id="bookingTime" name="time" required></div>
    </div>
    <div class="field"><label for="bookingDuration">Duration</label><select id="bookingDuration" name="duration"><option>2 Hours Sensual Session</option><option>Half-Day (4 Hours)</option><option>Full Overnight (8 Hours)</option></select></div>
    <p class="hint" style="margin-bottom:1rem">This is a request, not a confirmed booking. The coordinator replies in your dashboard.</p>
    <button class="btn btn-wine btn-block" type="submit">Send request</button>
  </form>
</div></div>
<?php
}

/** Profile card used on the home page and directory. */
function vg_profile_card(array $c, bool $withBook = false): void
{
    ?>
<article class="pcard" data-cat="<?= app_escape($c['category']) ?>" data-name="<?= app_escape(mb_strtolower($c['name'] . ' ' . $c['title'] . ' ' . $c['location'] . ' ' . $c['category'] . ' ' . $c['description'])) ?>" data-id="<?= (int) $c['id'] ?>" data-label="<?= app_escape($c['name']) ?>">
  <div class="media">
    <span class="mono" aria-hidden="true"><?= app_escape(vg_initial($c['name'])) ?></span>
    <img class="js-photo" src="<?= app_escape($c['photo']) ?>" alt="" loading="lazy">
    <span class="badge"><?= app_escape($c['badge']) ?></span>
  </div>
  <div class="pcard-body">
    <span class="pcard-cat"><?= app_escape($c['category']) ?></span>
    <h3><a href="listing.php?id=<?= (int) $c['id'] ?>"><?= app_escape($c['name']) ?></a></h3>
    <div class="pcard-title"><?= app_escape($c['title']) ?></div>
    <p class="pcard-loc"><?= app_escape($c['location']) ?></p>
    <div class="pcard-meta"><span>Hourly<strong><?= app_escape($c['rate']) ?></strong></span><span>Overnight<strong><?= app_escape($c['overnight']) ?></strong></span></div>
    <?php if ($withBook): ?><button type="button" class="btn btn-gold btn-block book-button" data-id="<?= (int) $c['id'] ?>" data-name="<?= app_escape($c['name']) ?>">Send a request</button><?php endif; ?>
  </div>
</article>
<?php
}
