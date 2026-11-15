<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
app_start_session();

$photoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$photoId || (!isset($_SESSION['admin_id']) && empty($_SESSION['patron_id']))) {
    http_response_code(404);
    exit;
}

$stmt = app_db()->prepare('SELECT patron_id, file_key FROM patron_photos WHERE id = ?');
$stmt->execute([$photoId]);
$photo = $stmt->fetch();
if (!$photo || (empty($_SESSION['admin_id']) && !hash_equals((string) $photo['patron_id'], (string) $_SESSION['patron_id']))) {
    http_response_code(404);
    exit;
}

$directory = getenv('APP_DATA_DIR') ?: __DIR__ . '/storage';
$path = $directory . '/profile-photos/' . $photo['file_key'] . '.jpg';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="profile-photo.jpg"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
