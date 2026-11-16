<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/vendor/autoload.php';
app_start_session();

$patronId = (string) ($_SESSION['patron_id'] ?? '');
$agentId = filter_var($_SESSION['agent_application_id'] ?? null, FILTER_VALIDATE_INT);
$reference = trim((string) ($_GET['ref'] ?? ''));
$upi = app_upi_config();
if (!$upi) {
    http_response_code(503);
    exit;
}

if ($patronId !== '' && preg_match('/^VG[A-F0-9]{16}$/', $reference)) {
    $stmt = app_db()->prepare(
        'SELECT amount_paise FROM membership_payments
         WHERE patron_id = ? AND payment_ref = ? AND status = \'Awaiting Payment\''
    );
    $stmt->execute([$patronId, $reference]);
    $payment = $stmt->fetch();
} elseif ($agentId && preg_match('/^AG[A-F0-9]{16}$/', $reference)) {
    $stmt = app_db()->prepare(
        'SELECT amount_paise FROM agent_applications
         WHERE id = ? AND payment_ref = ? AND status = \'Approved\' AND payment_status IN (\'Awaiting Payment\', \'Rejected\')'
    );
    $stmt->execute([$agentId, $reference]);
    $payment = $stmt->fetch();
} else {
    http_response_code(404);
    exit;
}
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
