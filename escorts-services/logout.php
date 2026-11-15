<?php
declare(strict_types=1);
// Member sign-out. api.php's `logout` action only clears the coordinator session,
// so the member dashboard uses this small endpoint instead.
require __DIR__ . '/app.php';
app_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['error' => 'Method not allowed.'], 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !hash_equals(app_csrf_token(), (string) ($input['csrf'] ?? ''))) {
    app_json(['error' => 'Your session expired. Refresh the page and try again.'], 419);
}
unset($_SESSION['patron_id']);
session_regenerate_id(true);
app_json(['ok' => true]);
