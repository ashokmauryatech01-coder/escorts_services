<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/vendor/autoload.php';
app_start_session();

$patronId = (string) ($_SESSION['patron_id'] ?? '');
$reference = trim((string) ($_GET['ref'] ?? ''));
if ($patronId === '' || !preg_match('/^VG[A-F0-9]{16}$/', $reference)) {
    http_response_code(404);
    exit;
}

$upi = app_upi_config();
if (!$upi) {
    http_response_code(503);
    exit;
}

$stmt = app_db()->prepare(
    'SELECT amount_paise FROM membership_payments
     WHERE patron_id = ? AND payment_ref = ? AND status = \'Awaiting Payment\''
);
$stmt->execute([$patronId, $reference]);
$payment = $stmt->fetch();
if (!$payment) {
    http_response_code(404);
    exit;
}

$intent = app_upi_intent($upi, $reference, (int) $payment['amount_paise']);
$qr = \Endroid\QrCode\QrCode::create($intent)
    ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
    ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::Medium)
    ->setSize(360)
    ->setMargin(16);
$result = (new \Endroid\QrCode\Writer\PngWriter())->write($qr);
header('Content-Type: image/png');
header('Content-Length: ' . strlen($result->getString()));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $result->getString();
