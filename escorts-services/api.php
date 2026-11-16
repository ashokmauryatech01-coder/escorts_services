<?php
declare(strict_types=1);

require __DIR__ . '/app.php';
app_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_json(['error' => 'Method not allowed.'], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = str_starts_with(strtolower($contentType), 'multipart/form-data')
    ? $_POST
    : json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    app_json(['error' => 'Invalid request.'], 400);
}
if (!hash_equals(app_csrf_token(), (string) ($input['csrf'] ?? ''))) {
    app_json(['error' => 'Your session expired. Refresh the page and try again.'], 419);
}

$action = (string) ($input['action'] ?? '');
$db = app_db();

try {
    if ($action === 'agent-apply') {
        $name = trim((string) ($input['name'] ?? ''));
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = trim((string) ($input['phone'] ?? ''));
        $plan = trim((string) ($input['plan'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');
        $fields = [
            'title' => trim((string) ($input['title'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'rate' => trim((string) ($input['rate'] ?? '')),
            'overnight' => trim((string) ($input['overnight'] ?? '')),
            'location' => trim((string) ($input['location'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'photo' => trim((string) ($input['photo'] ?? '')),
        ];
        $limits = ['title' => 200, 'category' => 100, 'rate' => 100, 'overnight' => 100, 'location' => 160, 'description' => 500, 'photo' => 500];
        if ($name === '' || mb_strlen($name) > 100 || !$email || $phone === '' || mb_strlen($phone) > 40) {
            app_json(['error' => 'Enter a display name, valid email address, and phone number.'], 422);
        }
        $plans = app_agent_plans();
        if (!array_key_exists($plan, $plans)) {
            app_json(['error' => 'Choose one of the available agent plans.'], 422);
        }
        if (strlen($password) < 10 || strlen($password) > 128 || $password !== $passwordConfirm) {
            app_json(['error' => 'Use a matching password of 10 to 128 characters.'], 422);
        }
        foreach ($fields as $field => $value) {
            $max = $limits[$field];
            if ($value === '' || mb_strlen($value) > $max) {
                app_json(['error' => 'Complete the ' . str_replace('_', ' ', $field) . ' field (maximum ' . $max . ' characters).'], 422);
            }
        }
        if (!filter_var($fields['photo'], FILTER_VALIDATE_URL) || parse_url($fields['photo'], PHP_URL_SCHEME) !== 'https') {
            app_json(['error' => 'Enter a valid HTTPS image URL for your profile photo.'], 422);
        }
        if (($input['adult_confirm'] ?? '') !== '1') {
            app_json(['error' => 'Confirm that you are at least 18 years old.'], 422);
        }
        $duplicate = $db->prepare('SELECT id, password_hash, plan FROM agent_applications WHERE lower(email) = lower(?) AND status = \'Pending\' ORDER BY id DESC LIMIT 1');
        $duplicate->execute([$email]);
        $existingApplication = $duplicate->fetch();
        if ($existingApplication && !empty($existingApplication['password_hash']) && !empty($existingApplication['plan'])) {
            app_json(['error' => 'An application for this email is already awaiting review.'], 409);
        }
        $values = [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $plan, $plans[$plan] * 100, $fields['title'], $fields['category'], $fields['rate'], $fields['overnight'], $fields['location'], $fields['description'], $fields['photo']];
        if ($existingApplication) {
            $update = $db->prepare(
                'UPDATE agent_applications SET name=?, email=?, phone=?, password_hash=?, plan=?, amount_paise=?, title=?, category=?, rate=?, overnight=?, location=?, description=?, photo=? WHERE id=?'
            );
            $update->execute([...$values, (int) $existingApplication['id']]);
            $applicationId = (int) $existingApplication['id'];
        } else {
            $insert = $db->prepare(
                'INSERT INTO agent_applications (name, email, phone, password_hash, plan, amount_paise, title, category, rate, overnight, location, description, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute($values);
            $applicationId = (int) $db->lastInsertId();
        }
        session_regenerate_id(true);
        $_SESSION['agent_application_id'] = $applicationId;
        app_json(['ok' => true, 'message' => 'Application received. The coordinator will review it. If approved, you can pay the one-time subscription by UPI and submit the transaction reference for verification.']);
    }

    if ($action === 'register') {
        $name = trim((string) ($input['name'] ?? ''));
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');
        $tier = (string) ($input['tier'] ?? '');
        $tiers = ['Velvet Pass', 'Gold Reserve', 'Elite VIP'];
        $profile = [
            'figure' => trim((string) ($input['figure'] ?? '')),
            'hair_color' => trim((string) ($input['hair_color'] ?? '')),
            'skin_tone' => trim((string) ($input['skin_tone'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'country' => trim((string) ($input['country'] ?? '')),
            'postal_code' => trim((string) ($input['postal_code'] ?? '')),
            'interests' => trim((string) ($input['interests'] ?? '')),
            'origin' => trim((string) ($input['origin'] ?? '')),
            'chest_size' => trim((string) ($input['chest_size'] ?? '')),
            'penis_size' => trim((string) ($input['penis_size'] ?? '')),
        ];
        $limits = [
            'figure' => 80, 'hair_color' => 60, 'skin_tone' => 60,
            'city' => 100, 'state' => 100, 'country' => 100,
            'postal_code' => 24, 'interests' => 300, 'origin' => 100,
            'chest_size' => 50, 'penis_size' => 50,
        ];
        foreach ($profile as $field => $value) {
            if (mb_strlen($value) > $limits[$field]) {
                app_json(['error' => 'Please shorten the ' . str_replace('_', ' ', $field) . ' field.'], 422);
            }
        }
        $heightInput = trim((string) ($input['height_cm'] ?? ''));
        $height = $heightInput === '' ? null : filter_var($heightInput, FILTER_VALIDATE_INT);
        $sensitiveConsent = ($input['sensitive_data_consent'] ?? '') === '1';
        $sensitiveFields = ['skin_tone', 'origin', 'chest_size', 'penis_size'];
        $hasSensitiveValues = false;
        foreach ($sensitiveFields as $field) {
            if ($profile[$field] !== '') {
                $hasSensitiveValues = true;
                break;
            }
        }

        if ($name === '' || mb_strlen($name) > 100 || !$email || $phone === '' || strlen($phone) > 40 || !in_array($tier, $tiers, true)) {
            app_json(['error' => 'Please check your name, email, phone, and membership selection.'], 422);
        }
        if (strlen($password) < 10 || strlen($password) > 128 || $password !== $passwordConfirm) {
            app_json(['error' => 'Use a matching password of 10 to 128 characters.'], 422);
        }
        $emailCheck = $db->prepare('SELECT 1 FROM patrons WHERE lower(email) = lower(?) LIMIT 1');
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn()) {
            app_json(['error' => 'An account already uses this email. Sign in or contact the coordinator for help.'], 409);
        }
        // if (($heightInput !== '' && ($height === false || $height < 100 || $height > 250))
        //     || ($hasSensitiveValues && !$sensitiveConsent)) {
        //     app_json(['error' => $hasSensitiveValues
        //         ? 'Confirm the private-data consent before saving sensitive profile details.'
        //         : 'Enter a valid height between 100 and 250 cm.'], 422);
        // }

        $photos = [];
        if (isset($_FILES['photos'])) {
            $upload = $_FILES['photos'];
            if (!is_array($upload['error'] ?? null)) {
                app_json(['error' => 'Invalid photo upload.'], 422);
            }
            foreach ($upload['error'] as $index => $errorCode) {
                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($errorCode !== UPLOAD_ERR_OK) {
                    app_json(['error' => 'A photo could not be uploaded. Please check its size and try again.'], 422);
                }
                if (count($photos) >= 3) {
                    app_json(['error' => 'Upload no more than three photos.'], 422);
                }
                $tmpName = $upload['tmp_name'][$index] ?? '';
                if (!is_uploaded_file($tmpName) || filesize($tmpName) > 5 * 1024 * 1024) {
                    app_json(['error' => 'Each photo must be a valid image under 5 MB.'], 422);
                }
                $imageInfo = @getimagesize($tmpName);
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                if (!$imageInfo || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                    || $imageInfo[0] > 6000 || $imageInfo[1] > 6000
                    || $imageInfo[0] * $imageInfo[1] > 12000000) {
                    app_json(['error' => 'Photos must be JPEG, PNG, or WebP images within the supported dimensions.'], 422);
                }
                $photos[] = $tmpName;
            }
        }

        $dataDirectory = getenv('APP_DATA_DIR') ?: __DIR__ . '/storage';
        $photoDirectory = $dataDirectory . '/profile-photos';
        if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0700, true) && !is_dir($photoDirectory)) {
            throw new RuntimeException('Unable to create the private profile-photo directory.');
        }
        if (!chmod($photoDirectory, 0700)) {
            throw new RuntimeException('Unable to restrict access to profile photos.');
        }
        $savedPhotos = [];
        try {
            foreach ($photos as $index => $tmpName) {
                $image = imagecreatefromstring((string) file_get_contents($tmpName));
                if (!$image) {
                    throw new InvalidArgumentException('One of the selected files is not a supported image.');
                }
                $width = imagesx($image);
                $heightPixels = imagesy($image);
                $scale = min(1, 1600 / max($width, $heightPixels));
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($heightPixels * $scale));
                $normalized = imagecreatetruecolor($targetWidth, $targetHeight);
                $white = imagecolorallocate($normalized, 255, 255, 255);
                imagefill($normalized, 0, 0, $white);
                imagecopyresampled($normalized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $heightPixels);
                $key = bin2hex(random_bytes(16));
                $path = $photoDirectory . '/' . $key . '.jpg';
                if (!imagejpeg($normalized, $path, 88) || !chmod($path, 0600)) {
                    imagedestroy($image);
                    imagedestroy($normalized);
                    throw new RuntimeException('Unable to securely store an uploaded profile photo.');
                }
                imagedestroy($image);
                imagedestroy($normalized);
                $savedPhotos[] = ['key' => $key, 'path' => $path, 'order' => $index];
            }
        } catch (Throwable $error) {
            foreach ($savedPhotos as $photo) {
                if (is_file($photo['path'])) {
                    unlink($photo['path']);
                }
            }
            throw $error;
        }

        $id = 'VG-' . strtoupper(bin2hex(random_bytes(4)));
        $sensitiveData = $sensitiveConsent ? $profile : array_merge($profile, [
            'skin_tone' => '', 'origin' => '', 'chest_size' => '', 'penis_size' => '',
        ]);
        try {
            $db->beginTransaction();
            $insert = $db->prepare(
                'INSERT INTO patrons
                 (id, name, email, phone, password_hash, tier, height_cm, figure, hair_color, skin_tone, city, state, country,
                  postal_code, interests, origin, chest_size, penis_size, sensitive_data_consent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $id, $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $tier, $height, $profile['figure'], $profile['hair_color'],
                $sensitiveData['skin_tone'], $profile['city'], $profile['state'], $profile['country'],
                $profile['postal_code'], $profile['interests'], $sensitiveData['origin'],
                $sensitiveData['chest_size'], $sensitiveData['penis_size'], (int) $sensitiveConsent,
            ]);
            $photoInsert = $db->prepare('INSERT INTO patron_photos (patron_id, file_key, sort_order) VALUES (?, ?, ?)');
            foreach ($savedPhotos as $photo) {
                $photoInsert->execute([$id, $photo['key'], $photo['order']]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            foreach ($savedPhotos as $photo) {
                if (is_file($photo['path'])) {
                    unlink($photo['path']);
                }
            }
            throw $error;
        }
        $_SESSION['patron_id'] = $id;
        app_json(['ok' => true, 'patron' => ['id' => $id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'tier' => $tier]]);
    }

    if ($action === 'payment-start') {
        $patronId = (string) ($_SESSION['patron_id'] ?? '');
        if ($patronId === '') {
            app_json(['error' => 'Sign in to your member session before starting payment.'], 401);
        }
        $upi = app_upi_config();
        if (!$upi) {
            app_json(['error' => 'UPI payments are not configured yet. Please contact the coordinator.'], 503);
        }
        $patronQuery = $db->prepare('SELECT tier FROM patrons WHERE id = ?');
        $patronQuery->execute([$patronId]);
        $patron = $patronQuery->fetch();
        if (!$patron) {
            app_json(['error' => 'Member profile not found.'], 404);
        }

        $existing = $db->prepare(
            'SELECT payment_ref, amount_paise, status FROM membership_payments
             WHERE patron_id = ? AND status IN (\'Awaiting Payment\', \'Submitted\', \'Verified\')
             ORDER BY id DESC LIMIT 1'
        );
        $existing->execute([$patronId]);
        $payment = $existing->fetch();
        if (!$payment) {
            $prices = app_membership_prices();
            $amountPaise = $prices[$patron['tier']] * 100;
            $reference = 'VG' . strtoupper(bin2hex(random_bytes(8)));
            $insert = $db->prepare(
                'INSERT INTO membership_payments (patron_id, payment_ref, amount_paise) VALUES (?, ?, ?)'
            );
            $insert->execute([$patronId, $reference, $amountPaise]);
            $payment = ['payment_ref' => $reference, 'amount_paise' => $amountPaise, 'status' => 'Awaiting Payment'];
        }
        app_json([
            'ok' => true,
            'payment_ref' => $payment['payment_ref'],
            'amount_paise' => (int) $payment['amount_paise'],
            'amount' => number_format((int) $payment['amount_paise'] / 100, 2, '.', ''),
            'status' => $payment['status'],
            'upi_uri' => app_upi_intent($upi, $payment['payment_ref'], (int) $payment['amount_paise']),
            'qr_url' => 'upi-qr.php?ref=' . rawurlencode($payment['payment_ref']),
            'payee' => $upi['payee'],
            'upi_id' => $upi['upi_id'],
        ]);
    }

    if ($action === 'payment-submit-utr') {
        $patronId = (string) ($_SESSION['patron_id'] ?? '');
        $reference = trim((string) ($input['payment_ref'] ?? ''));
        $utr = strtoupper(trim((string) ($input['utr'] ?? '')));
        if ($patronId === '') {
            app_json(['error' => 'Sign in to your member session before submitting a payment reference.'], 401);
        }
        if (!preg_match('/^[A-Z0-9-]{6,30}$/', $utr)) {
            app_json(['error' => 'Enter the UPI transaction reference shown by your payment app.'], 422);
        }
        $paymentQuery = $db->prepare(
            'SELECT id FROM membership_payments
             WHERE patron_id = ? AND payment_ref = ? AND status = \'Awaiting Payment\''
        );
        $paymentQuery->execute([$patronId, $reference]);
        $payment = $paymentQuery->fetch();
        if (!$payment) {
            app_json(['error' => 'This payment request is not awaiting a transaction reference.'], 409);
        }
        $duplicate = $db->prepare('SELECT 1 FROM membership_payments WHERE utr = ?');
        $duplicate->execute([$utr]);
        if ($duplicate->fetchColumn()) {
            app_json(['error' => 'That transaction reference has already been submitted.'], 409);
        }
        $update = $db->prepare(
            'UPDATE membership_payments SET utr = ?, status = \'Submitted\', submitted_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = \'Awaiting Payment\''
        );
        $update->execute([$utr, $payment['id']]);
        if ($update->rowCount() !== 1) {
            app_json(['error' => 'Payment request has already been updated. Refresh and check its status.'], 409);
        }
        app_json(['ok' => true, 'message' => 'Transaction reference submitted for coordinator verification.']);
    }

    if ($action === 'book') {
        $patronId = (string) ($_SESSION['patron_id'] ?? '');
        $companionId = filter_var($input['companion_id'] ?? null, FILTER_VALIDATE_INT);
        $setting = trim((string) ($input['setting'] ?? ''));
        $date = (string) ($input['date'] ?? '');
        $time = (string) ($input['time'] ?? '');
        $duration = trim((string) ($input['duration'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $validDate = $dateValue && $dateValue->format('Y-m-d') === $date && $date >= date('Y-m-d');
        $validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
        $validDurations = ['2 Hours Sensual Session', 'Half-Day (4 Hours)', 'Full Overnight (8 Hours)'];
        if ($patronId === '' || !$companionId || $setting === '' || mb_strlen($setting) > 120 || !$validDate || !$validTime || !in_array($duration, $validDurations, true)) {
            app_json(['error' => 'Register first and provide a valid date, time, setting, and duration.'], 422);
        }
        if (app_upi_config()) {
            $membershipQuery = $db->prepare(
                'SELECT 1 FROM membership_payments WHERE patron_id = ? AND status = \'Verified\''
            );
            $membershipQuery->execute([$patronId]);
            if (!$membershipQuery->fetchColumn()) {
                app_json(['error' => 'Verify your membership payment before sending a request.'], 403);
            }
        }

        $check = $db->prepare('SELECT id FROM companions WHERE id = ? AND active = 1');
        $check->execute([$companionId]);
        if (!$check->fetch()) {
            app_json(['error' => 'That listing is no longer available.'], 404);
        }
        $insert = $db->prepare(
            'INSERT INTO bookings (patron_id, companion_id, setting, booking_date, booking_time, duration)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$patronId, $companionId, $setting, $date, $time, $duration]);
        app_json(['ok' => true, 'message' => 'Your request has been sent for confirmation.']);
    }

    if ($action === 'member-login') {
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($input['password'] ?? '');
        $stmt = $db->prepare('SELECT id, name, password_hash FROM patrons WHERE lower(email) = lower(?)');
        $stmt->execute([$email ?: '']);
        $member = $stmt->fetch();
        if (!$member || empty($member['password_hash']) || !password_verify($password, $member['password_hash'])) {
            app_json(['error' => 'Incorrect email or password.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['patron_id'] = $member['id'];
        app_json(['ok' => true, 'member' => ['name' => $member['name']]]);
    }

    if ($action === 'agent-login') {
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($input['password'] ?? '');
        $stmt = $db->prepare('SELECT id, name, password_hash FROM agent_applications WHERE lower(email) = lower(?) AND password_hash IS NOT NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email ?: '']);
        $agent = $stmt->fetch();
        if (!$agent || !password_verify($password, $agent['password_hash'])) {
            app_json(['error' => 'Incorrect email or password.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['agent_application_id'] = (int) $agent['id'];
        app_json(['ok' => true]);
    }

    if ($action === 'agent-payment-submit') {
        $agentId = filter_var($_SESSION['agent_application_id'] ?? null, FILTER_VALIDATE_INT);
        $reference = trim((string) ($input['payment_ref'] ?? ''));
        $utr = strtoupper(trim((string) ($input['utr'] ?? '')));
        if (!$agentId || !preg_match('/^AG[A-F0-9]{16}$/', $reference) || !preg_match('/^[A-Z0-9-]{6,30}$/', $utr)) {
            app_json(['error' => 'Enter the UPI reference shown on this page.'], 422);
        }
        $query = $db->prepare('SELECT payment_status, payment_ref FROM agent_applications WHERE id = ? AND status = \'Approved\'');
        $query->execute([$agentId]);
        $agent = $query->fetch();
        if (!$agent || !hash_equals((string) $agent['payment_ref'], $reference) || !in_array($agent['payment_status'], ['Awaiting Payment', 'Rejected'], true)) {
            app_json(['error' => 'This agent payment cannot be updated. Refresh and check your application status.'], 409);
        }
        $duplicate = $db->prepare('SELECT 1 FROM membership_payments WHERE utr = ? UNION SELECT 1 FROM agent_applications WHERE utr = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$utr, $utr, $agentId]);
        if ($duplicate->fetchColumn()) {
            app_json(['error' => 'This UPI reference has already been submitted.'], 409);
        }
        $update = $db->prepare('UPDATE agent_applications SET utr = ?, payment_status = \'Submitted\', payment_submitted_at = CURRENT_TIMESTAMP, payment_reviewed_at = NULL WHERE id = ?');
        $update->execute([$utr, $agentId]);
        app_json(['ok' => true, 'message' => 'Payment reference submitted for coordinator verification.']);
    }

    if (in_array($action, ['login', 'admin-login'], true)) {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $stmt = $db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            app_json(['error' => 'Incorrect username or password.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        app_json(['ok' => true]);
    }

    if ($action === 'logout') {
        app_require_admin();
        unset($_SESSION['admin_id'], $_SESSION['admin_username']);
        session_regenerate_id(true);
        app_json(['ok' => true]);
    }

    if ($action === 'agent-logout') {
        if (empty($_SESSION['agent_application_id'])) {
            app_json(['error' => 'No agent session is active.'], 401);
        }
        unset($_SESSION['agent_application_id']);
        session_regenerate_id(true);
        app_json(['ok' => true]);
    }

    if (str_starts_with($action, 'admin-')) {
        app_require_admin();
    }

    if ($action === 'admin-upi-save') {
        $upiId = trim((string) ($input['upi_id'] ?? ''));
        $payee = trim((string) ($input['payee'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,63}@[A-Za-z0-9][A-Za-z0-9.-]{1,63}$/', $upiId)) {
            app_json(['error' => 'Enter a valid UPI ID, for example name@bank.'], 422);
        }
        if ($payee === '' || mb_strlen($payee) > 50) {
            app_json(['error' => 'Enter a payee name of 1 to 50 characters.'], 422);
        }
        $stmt = $db->prepare(
            'INSERT INTO upi_settings (id, upi_id, payee, enabled, updated_at) VALUES (1, ?, ?, 1, CURRENT_TIMESTAMP)
             ON CONFLICT(id) DO UPDATE SET upi_id = excluded.upi_id, payee = excluded.payee, enabled = 1, updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$upiId, $payee]);
        app_json(['ok' => true, 'message' => 'UPI details saved. New payment pages will use these details.']);
    }

    if ($action === 'admin-upi-disable') {
        $db->exec(
            "INSERT INTO upi_settings (id, upi_id, payee, enabled, updated_at) VALUES (1, '', '', 0, CURRENT_TIMESTAMP)
             ON CONFLICT(id) DO UPDATE SET enabled = 0, updated_at = CURRENT_TIMESTAMP"
        );
        app_json(['ok' => true, 'message' => 'UPI payments disabled.']);
    }

    if ($action === 'admin-upi-reset') {
        $db->exec('DELETE FROM upi_settings WHERE id = 1');
        app_json(['ok' => true, 'message' => 'Saved UPI settings removed. The app will use server environment settings.']);
    }

    if ($action === 'admin-bookings-list') {
        $rows = $db->query(
            'SELECT b.id, b.patron_id, p.name AS patron_name, c.name AS companion_name,
                    b.setting, b.booking_date, b.booking_time, b.duration, b.status
             FROM bookings b JOIN patrons p ON p.id = b.patron_id
             JOIN companions c ON c.id = b.companion_id
             ORDER BY b.id DESC'
        )->fetchAll();
        $total = (int) $db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
        $pending = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'Pending'")->fetchColumn();
        $agentApplications = (int) $db->query("SELECT COUNT(*) FROM agent_applications WHERE status = 'Pending'")->fetchColumn();
        app_json(['ok' => true, 'bookings' => $rows, 'total' => $total, 'pending' => $pending, 'agentApplications' => $agentApplications]);
    }

    if ($action === 'admin-agent-application-review') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($input['status'] ?? '');
        if (!$id || !in_array($status, ['Approved', 'Declined'], true)) {
            app_json(['error' => 'Choose approve or decline for this application.'], 422);
        }
        $db->beginTransaction();
        $query = $db->prepare('SELECT * FROM agent_applications WHERE id = ? AND status = \'Pending\'');
        $query->execute([$id]);
        $application = $query->fetch();
        if (!$application) {
            $db->rollBack();
            app_json(['error' => 'This application has already been reviewed or no longer exists. Refresh the page.'], 409);
        }
        if ($status === 'Approved') {
            if (empty($application['plan']) || (int) $application['amount_paise'] < 1) {
                $db->rollBack();
                app_json(['error' => 'This application has no valid subscription plan. Ask the applicant to submit a new application.'], 409);
            }
            $reference = 'AG' . strtoupper(bin2hex(random_bytes(8)));
            $update = $db->prepare('UPDATE agent_applications SET status = ?, payment_ref = ?, payment_status = \'Awaiting Payment\', reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'Pending\'');
            $update->execute([$status, $reference, $id]);
        } else {
            $update = $db->prepare('UPDATE agent_applications SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'Pending\'');
            $update->execute([$status, $id]);
        }
        $db->commit();
        app_json(['ok' => true, 'message' => $status === 'Approved' ? 'Application approved. The agent can now pay the selected subscription.' : 'Application declined.']);
    }

    if ($action === 'admin-agent-payment-review') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($input['status'] ?? '');
        if (!$id || !in_array($status, ['Verified', 'Rejected'], true)) {
            app_json(['error' => 'Choose whether the agent payment is verified or rejected.'], 422);
        }
        $db->beginTransaction();
        $query = $db->prepare('SELECT * FROM agent_applications WHERE id = ? AND status = \'Approved\' AND payment_status = \'Submitted\'');
        $query->execute([$id]);
        $agent = $query->fetch();
        if (!$agent) {
            $db->rollBack();
            app_json(['error' => 'This agent payment has already been reviewed or is not ready. Refresh the page.'], 409);
        }
        $companionId = $agent['companion_id'];
        if ($status === 'Verified' && !$companionId) {
            $listing = $db->prepare(
                'INSERT INTO companions (name, title, category, rate, overnight, location, description, badge, photo, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $listing->execute([$agent['name'], $agent['title'], $agent['category'], $agent['rate'], $agent['overnight'], $agent['location'], $agent['description'], 'Subscribed agent', $agent['photo']]);
            $companionId = (int) $db->lastInsertId();
        }
        $update = $db->prepare('UPDATE agent_applications SET payment_status = ?, companion_id = ?, active_at = CASE WHEN ? = \'Verified\' THEN CURRENT_TIMESTAMP ELSE active_at END, payment_reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND payment_status = \'Submitted\'');
        $update->execute([$status, $companionId, $status, $id]);
        $db->commit();
        app_json(['ok' => true, 'message' => $status === 'Verified' ? 'Payment verified and agent profile published.' : 'Payment rejected. The agent can submit a corrected reference.']);
    }

    if ($action === 'admin-companion-save') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $fields = [
            'name' => trim((string) ($input['name'] ?? '')),
            'title' => trim((string) ($input['title'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'rate' => trim((string) ($input['rate'] ?? '')),
            'overnight' => trim((string) ($input['overnight'] ?? '')),
            'location' => trim((string) ($input['location'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'badge' => trim((string) ($input['badge'] ?? '')),
            'photo' => trim((string) ($input['photo'] ?? '')),
        ];
        foreach ($fields as $value) {
            if ($value === '' || mb_strlen($value) > 500) {
                app_json(['error' => 'All listing fields are required and must be under 500 characters.'], 422);
            }
        }
        if (!filter_var($fields['photo'], FILTER_VALIDATE_URL) || parse_url($fields['photo'], PHP_URL_SCHEME) !== 'https') {
            app_json(['error' => 'Use a valid HTTPS image URL.'], 422);
        }
        if ($id) {
            $stmt = $db->prepare(
                'UPDATE companions SET name=:name, title=:title, category=:category, rate=:rate,
                 overnight=:overnight, location=:location, description=:description, badge=:badge, photo=:photo
                 WHERE id=:id'
            );
            $stmt->execute($fields + ['id' => $id]);
            if ($stmt->rowCount() === 0) {
                $exists = $db->prepare('SELECT 1 FROM companions WHERE id = ?');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    app_json(['error' => 'Listing not found.'], 404);
                }
            }
        } else {
            $stmt = $db->prepare(
                'INSERT INTO companions (name, title, category, rate, overnight, location, description, badge, photo)
                 VALUES (:name, :title, :category, :rate, :overnight, :location, :description, :badge, :photo)'
            );
            $stmt->execute($fields);
        }
        app_json(['ok' => true]);
    }

    if ($action === 'admin-companion-toggle') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            app_json(['error' => 'Invalid listing.'], 422);
        }
        $stmt = $db->prepare('UPDATE companions SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            app_json(['error' => 'Listing not found.'], 404);
        }
        app_json(['ok' => true]);
    }

    if ($action === 'admin-booking-status') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($input['status'] ?? '');
        if (!$id || !in_array($status, ['Pending', 'Confirmed', 'Declined'], true)) {
            app_json(['error' => 'Invalid booking update.'], 422);
        }
        $stmt = $db->prepare('UPDATE bookings SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM bookings WHERE id = ? AND status = ?');
            $exists->execute([$id, $status]);
            if (!$exists->fetchColumn()) {
                app_json(['error' => 'Booking not found.'], 404);
            }
        }
        app_json(['ok' => true]);
    }

    if ($action === 'admin-payment-review') {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($input['status'] ?? '');
        if (!$id || !in_array($status, ['Verified', 'Rejected'], true)) {
            app_json(['error' => 'Choose whether the submitted payment is verified or rejected.'], 422);
        }
        $paymentQuery = $db->prepare('SELECT status FROM membership_payments WHERE id = ?');
        $paymentQuery->execute([$id]);
        $payment = $paymentQuery->fetch();
        if (!$payment) {
            app_json(['error' => 'Payment record not found.'], 404);
        }
        if ($payment['status'] !== 'Submitted') {
            app_json(['error' => 'Only submitted payments can be reviewed.'], 409);
        }
        $update = $db->prepare(
            'UPDATE membership_payments SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = \'Submitted\''
        );
        $update->execute([$status, $id]);
        if ($update->rowCount() !== 1) {
            app_json(['error' => 'Payment status changed; refresh the page and review again.'], 409);
        }
        app_json(['ok' => true]);
    }

    app_json(['error' => 'Unknown action.'], 404);
} catch (PDOException $error) {
    error_log('Application database error: ' . $error->getMessage());
    if ($action === 'payment-submit-utr' && (string) $error->getCode() === '23000') {
        app_json(['error' => 'That transaction reference has already been submitted.'], 409);
    }
    app_json(['error' => 'The request could not be saved. Please try again.'], 500);
} catch (InvalidArgumentException $error) {
    app_json(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Application error: ' . $error->getMessage());
    app_json(['error' => 'An unexpected error occurred. Please try again.'], 500);
}
