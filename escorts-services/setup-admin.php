<?php
declare(strict_types=1);
require __DIR__ . '/app.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$username = $argv[1] ?? '';
$password = bin2hex(random_bytes(16));
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Usage: php setup-admin.php <username>\nUsername must be 3–50 letters, numbers, dots, underscores, or hyphens.\n");
    exit(2);
}

$db = app_db();
if (app_admin_exists()) {
    fwrite(STDERR, "An admin account already exists; refusing to replace it.\n");
    exit(1);
}
$stmt = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Coordinator account created.\nUsername: {$username}\nTemporary password: {$password}\nStore this password securely; it is shown only once.\n");
