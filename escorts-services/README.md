# Velvet & Gold

The site now runs on PHP 8.1+ with SQLite. The database is created in `storage/site.sqlite` on first request; the PHP process must be able to write to `storage/`. Set `APP_DATA_DIR` to use a protected writable directory outside the web root.

## Run locally

```sh
composer install
export APP_UPI_ID='9074638861@YBL'
export APP_UPI_PAYEE='TEJPAL JAT'
php -d upload_max_filesize=5M -d post_max_size=16M -d max_file_uploads=4 -S 127.0.0.1:8000
```

Set a real merchant UPI ID and payee name before starting the server; otherwise payment is intentionally disabled. Open `http://127.0.0.1:8000/`. The public portal is `index.php`; the coordinator console is `admin.php`.

## Create the first coordinator account

Run this once from the application directory. The script creates a random temporary password and prints it only once:

```sh
php setup-admin.php coordinator
```

Keep the generated password securely. The admin account is stored as a password hash. The initial account can only be created from the command line.

Memberships, companion profiles, booking requests, and payment references are persisted in SQLite. The site does not process cards or perform government-ID verification; do not enter card details, government ID numbers, or one-time codes.

Signup can optionally collect private profile details and up to three JPEG, PNG, or WebP photos (maximum 5 MB each). Sensitive details require a separate consent checkbox. Photos are normalized and stored outside public access in the private data directory; only the member's current browser session and a signed-in coordinator can view them. Profile information is not published in the public directory. Configure PHP to allow uploads up to 5 MB per file and a 16 MB request body; `.user.ini` provides these limits on PHP CGI/FPM hosts. The PHP built-in development server does not read `.user.ini`, so use the options in the run command above when testing uploads locally.

## UPI membership payments

Set `APP_UPI_ID` to the real merchant VPA and `APP_UPI_PAYEE` to the displayed payee name in the PHP server environment. UPI is disabled unless both values are configured and valid. Existing membership prices are ₹1,499 / ₹4,999 / ₹12,500; optional server overrides are `APP_PRICE_VELVET_PASS`, `APP_PRICE_GOLD_RESERVE`, and `APP_PRICE_ELITE_VIP` (whole rupees).

Coordinators can also add or change the active payee and UPI ID in the admin console under **UPI payment details**. Saved admin settings override the server environment values; removing saved settings returns to the environment configuration. Disabling UPI turns off payment pages until it is enabled again.

After signup review, members are taken to `payment.php`, which displays their local UPI QR and exact membership amount. The site records the member-submitted UPI transaction reference (UTR) and leaves the payment pending. The coordinator must verify the amount and transaction in the merchant bank/UPI account before marking it verified in the admin console. A QR scan, UPI app return, or user-submitted UTR is not proof of receipt; this implementation does not have a bank/payment-provider webhook and never auto-verifies payments.

Agent applications are submitted through `agent-register.php`. The coordinator reviews the application first; if approved, the agent pays the selected one-time plan by UPI and submits the UTR from `agent-account.php`. The profile is published only after manual payment verification. Default one-time agent prices are ₹2,500 (Young Unmarried), ₹4,500 (Married), and ₹6,500 (Mature). Optional overrides are `APP_AGENT_PRICE_YOUNG_UNMARRIED`, `APP_AGENT_PRICE_MARRIED`, and `APP_AGENT_PRICE_MATURE`. Recurring billing is not implemented.

## Demo UPI QR (no real payee)

To preview the payment flow without a merchant account, run `./run-demo.sh` (sets `APP_UPI_DEMO=1` and a throwaway data folder). The QR then points at a fake payee, `demo.merchant@example`, which no UPI app can pay. Demo mode only applies when no valid `APP_UPI_ID` / `APP_UPI_PAYEE` is set, so production behaviour is unchanged.

## Front-end structure

- `assets/css/vg.css`, `assets/js/vg.js`: shared design system and behaviour.
- `inc/layout.php`: shared header, footer, age gate and card/modal partials.
- Pages: `index.php`, `directory.php`, `listing.php?id=`, `membership.php`, `register.php`, `agent-register.php`, `agent-login.php`, `agent-account.php`, `login.php`, `dashboard.php`, `payment.php`, `admin.php`.
- `logout.php`: member sign-out. `api.php`'s `logout` action only clears the coordinator session.
