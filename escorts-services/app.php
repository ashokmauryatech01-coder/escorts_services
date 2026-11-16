<?php
declare(strict_types=1);

function app_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $directory = getenv('APP_DATA_DIR') ?: __DIR__ . '/storage';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the application data directory.');
    }
    if (!chmod($directory, 0700)) {
        throw new RuntimeException('Unable to restrict access to the application data directory.');
    }

    $databasePath = $directory . '/site.sqlite';
    $db = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if (!chmod($databasePath, 0600)) {
        throw new RuntimeException('Unable to restrict access to the application database.');
    }
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec(
        'CREATE TABLE IF NOT EXISTS companions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            rate TEXT NOT NULL,
            overnight TEXT NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            badge TEXT NOT NULL,
            photo TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS patrons (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            password_hash TEXT,
            tier TEXT NOT NULL,
            height_cm INTEGER,
            figure TEXT,
            hair_color TEXT,
            skin_tone TEXT,
            city TEXT,
            state TEXT,
            country TEXT,
            postal_code TEXT,
            interests TEXT,
            origin TEXT,
            chest_size TEXT,
            penis_size TEXT,
            sensitive_data_consent INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS patron_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patron_id TEXT NOT NULL REFERENCES patrons(id) ON DELETE CASCADE,
            file_key TEXT NOT NULL UNIQUE,
            sort_order INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS agent_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            rate TEXT NOT NULL,
            overnight TEXT NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            photo TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'Pending\' CHECK (status IN (\'Pending\', \'Approved\', \'Declined\')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TEXT
        );
        CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patron_id TEXT NOT NULL REFERENCES patrons(id) ON DELETE CASCADE,
            companion_id INTEGER NOT NULL REFERENCES companions(id),
            setting TEXT NOT NULL,
            booking_date TEXT NOT NULL,
            booking_time TEXT NOT NULL,
            duration TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'Pending\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS membership_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patron_id TEXT NOT NULL REFERENCES patrons(id) ON DELETE CASCADE,
            payment_ref TEXT NOT NULL UNIQUE,
            amount_paise INTEGER NOT NULL,
            utr TEXT UNIQUE,
            status TEXT NOT NULL DEFAULT \'Awaiting Payment\'
                CHECK (status IN (\'Awaiting Payment\', \'Submitted\', \'Verified\', \'Rejected\')),
            submitted_at TEXT,
            reviewed_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS upi_settings (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            upi_id TEXT NOT NULL DEFAULT \'\',
            payee TEXT NOT NULL DEFAULT \'\',
            enabled INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $agentColumns = array_column($db->query('PRAGMA table_info(agent_applications)')->fetchAll(), 'name');
    $agentMigrations = [
        'password_hash' => 'TEXT',
        'plan' => 'TEXT',
        'amount_paise' => 'INTEGER',
        'payment_ref' => 'TEXT',
        'utr' => 'TEXT',
        'payment_status' => "TEXT NOT NULL DEFAULT 'Not Due'",
        'payment_submitted_at' => 'TEXT',
        'payment_reviewed_at' => 'TEXT',
        'companion_id' => 'INTEGER',
        'active_at' => 'TEXT',
    ];
    foreach ($agentMigrations as $column => $type) {
        if (!in_array($column, $agentColumns, true)) {
            $db->exec("ALTER TABLE agent_applications ADD COLUMN {$column} {$type}");
        }
    }
    $patronColumns = array_column($db->query('PRAGMA table_info(patrons)')->fetchAll(), 'name');
    $profileColumns = [
        'password_hash' => 'TEXT',
        'height_cm' => 'INTEGER',
        'figure' => 'TEXT',
        'hair_color' => 'TEXT',
        'skin_tone' => 'TEXT',
        'city' => 'TEXT',
        'state' => 'TEXT',
        'country' => 'TEXT',
        'postal_code' => 'TEXT',
        'interests' => 'TEXT',
        'origin' => 'TEXT',
        'chest_size' => 'TEXT',
        'penis_size' => 'TEXT',
        'sensitive_data_consent' => 'INTEGER NOT NULL DEFAULT 0',
    ];
    foreach ($profileColumns as $column => $type) {
        if (!in_array($column, $patronColumns, true)) {
            $db->exec("ALTER TABLE patrons ADD COLUMN {$column} {$type}");
        }
    }

    $legacyProfilePhoto = 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=800&q=80';
    $driveProfilePhoto = 'https://drive.google.com/thumbnail?id=1XkU45-woMLNPnKJrUCocBP3ZRNHGUKbU&sz=w1000';
    $photoUpdate = $db->prepare('UPDATE companions SET photo = ? WHERE photo = ?');
    $photoUpdate->execute([$driveProfilePhoto, $legacyProfilePhoto]);

    // if ((int) $db->query('SELECT COUNT(*) FROM companions')->fetchColumn() === 0) {
    //     $seed = $db->prepare(
    //         'INSERT INTO companions (name, title, category, rate, overnight, location, description, badge, photo)
    //          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    //     );
    //     $companions = [
    //         [
    //             'Sophia Du Pres', 'Artiste & Fine Dining Companion', 'Dinner & Events',
    //             '₹4,500/hr', '₹25,000', 'South Delhi / Gurgaon',
    //             'A cultured companion for dinners, galas, and private events.',
    //             'Incall / Outcall',
    //             'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80',
    //         ],
    //         [
    //             'Elena Rostova', 'Wellness & Massage Specialist', 'Sensual Massage',
    //             '₹3,500/hr', '₹18,000', 'Bandra West, Mumbai',
    //             'A welcoming wellness specialist offering relaxing massage experiences.',
    //             'Incall Private Suite',
    //             'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=800&q=80',
    //         ],
    //     ];
    //     foreach ($companions as $companion) {
    //         $seed->execute($companion);
    //     }
    // }

    if ((int) $db->query('SELECT COUNT(*) FROM companions')->fetchColumn() === 0) {

        $seed = $db->prepare(
            'INSERT INTO companions
            (name, title, category, rate, overnight, location, description, badge, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        // Fictional Indian sample names for a fresh demo database.
        $firstNames = [
            'Aanya', 'Diya', 'Isha', 'Kavya', 'Meera', 'Naina', 'Riya', 'Saanvi', 'Tara', 'Zoya',
            'Anika', 'Avni', 'Ira', 'Kiara', 'Mira', 'Navya', 'Pari', 'Sia', 'Veda', 'Yashvi'
        ];

        $lastNames = [
            'Agarwal', 'Bhatia', 'Chopra', 'Desai', 'Iyer',
            'Joshi', 'Kapoor', 'Khanna', 'Malhotra', 'Mehta',
            'Menon', 'Mukherjee', 'Nair', 'Patel', 'Rao',
            'Reddy', 'Shah', 'Sharma', 'Singh', 'Varma'
        ];

        $categories = [
            ['Artiste & Fine Dining Companion', 'Dinner & Events'],
            ['Wellness & Massage Specialist', 'Sensual Massage'],
            ['Luxury Lifestyle Companion', 'Luxury Events'],
            ['Travel & Holiday Companion', 'Travel'],
            ['Corporate Events Companion', 'Corporate Events'],
            ['Fashion & Social Events Companion', 'Fashion & Events'],
            ['Private Party Companion', 'Private Events'],
            ['Cultural Events Companion', 'Cultural Events'],
            ['Lifestyle & Dining Companion', 'Dining'],
            ['Professional Social Companion', 'Social Events']
        ];

        $locations = [
            'South Delhi / Gurgaon',
            'Bandra West, Mumbai',
            'Connaught Place, Delhi',
            'Cyber City, Gurgaon',
            'Jubilee Hills, Hyderabad',
            'Indiranagar, Bangalore',
            'Park Street, Kolkata',
            'Banjara Hills, Hyderabad',
            'Powai, Mumbai',
            'Koramangala, Bangalore',
            'Golf Course Road, Gurgaon',
            'Greater Kailash, Delhi',
            'Andheri West, Mumbai',
            'Whitefield, Bangalore',
            'Vasant Kunj, Delhi',
            'Salt Lake, Kolkata',
            'Noida Sector 18',
            'Hauz Khas, Delhi',
            'Lower Parel, Mumbai',
            'MG Road, Pune'
        ];

        $descriptions = [
            'A cultured companion for dinners, galas, and private events.',
            'A welcoming wellness specialist offering relaxing experiences.',
            'An elegant personality for premium social gatherings.',
            'A friendly travel companion for memorable holiday experiences.',
            'A sophisticated companion for corporate and networking events.',
            'A stylish personality with an interest in fashion and lifestyle.',
            'A warm and engaging companion for private social occasions.',
            'Enjoy meaningful conversations and cultural experiences.',
            'A refined dining companion for premium restaurants and events.',
            'A professional social companion with excellent communication skills.'
        ];

        $rates = [
            '₹2,500/hr', '₹3,000/hr', '₹3,500/hr',
            '₹4,000/hr', '₹4,500/hr', '₹5,000/hr',
            '₹5,500/hr'
        ];

        $overnightPrices = [
            '₹12,000', '₹15,000', '₹18,000',
            '₹20,000', '₹22,000', '₹25,000',
            '₹30,000'
        ];

        $badges = [
            'Incall / Outcall',
            'Incall Private Suite',
            'Outcall Available',
            'Events & Reservations',
            'Hotel / Event Visits'
        ];

        $photos = [
            'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=800&q=80',
            'https://drive.google.com/thumbnail?id=1XkU45-woMLNPnKJrUCocBP3ZRNHGUKbU&sz=w1000',
            'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=800&q=80',
            'https://images.unsplash.com/photo-1524250502761-1ac6f2e30d43?auto=format&fit=crop&w=800&q=80'
        ];

        $companions = [
            [
                'Aanya Sharma',
                'Artiste & Fine Dining Companion',
                'Dinner & Events',
                '₹4,500/hr',
                '₹25,000',
                'South Delhi / Gurgaon',
                'A cultured companion for dinners, galas, and private events.',
                'Incall / Outcall',
                $photos[0]
            ],
            [
                'Meera Iyer',
                'Wellness & Massage Specialist',
                'Sensual Massage',
                '₹3,500/hr',
                '₹18,000',
                'Bandra West, Mumbai',
                'A welcoming wellness specialist offering relaxing experiences.',
                'Incall Private Suite',
                $photos[1]
            ]
        ];

        // Generate 118 additional demo records
        $index = 0;

        foreach ($firstNames as $firstName) {
            foreach ($lastNames as $lastName) {

                if ($index >= 118) {
                    break 2;
                }

                $category = $categories[$index % count($categories)];

                $companions[] = [
                    $firstName . ' ' . $lastName,
                    $category[0],
                    $category[1],
                    $rates[$index % count($rates)],
                    $overnightPrices[$index % count($overnightPrices)],
                    $locations[$index % count($locations)],
                    $descriptions[$index % count($descriptions)],
                    $badges[$index % count($badges)],
                    $photos[$index % count($photos)]
                ];

                $index++;
            }
        }

        // Insert all 120 records
        $db->beginTransaction();

        try {
            foreach ($companions as $companion) {
                $seed->execute($companion);
            }

            $db->commit();

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    return $db;
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function app_csrf_token(): string
{
    app_start_session();
    return (string) $_SESSION['csrf'];
}

function app_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_admin_exists(): bool
{
    return (int) app_db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function app_require_admin(): void
{
    app_start_session();
    if (empty($_SESSION['admin_id'])) {
        app_json(['error' => 'Please sign in to continue.'], 401);
    }
}

function app_upi_config(): ?array
{
    $stored = app_db()->query('SELECT upi_id, payee, enabled FROM upi_settings WHERE id = 1')->fetch();
    if ($stored) {
        if (!(int) $stored['enabled']) {
            return null;
        }
        $upiId = trim((string) $stored['upi_id']);
        $payee = trim((string) $stored['payee']);
    } else {
        $upiId = trim((string) (getenv('APP_UPI_ID') ?: ''));
        $payee = trim((string) (getenv('APP_UPI_PAYEE') ?: ''));
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,63}@[A-Za-z0-9][A-Za-z0-9.-]{1,63}$/', $upiId)
        || $payee === '' || mb_strlen($payee) > 50) {
        // Opt-in demo mode (APP_UPI_DEMO=1): a clearly fake payee on a handle that
        // no UPI app can resolve, so the QR flow can be previewed without real money.
        if (!$stored && getenv('APP_UPI_DEMO') === '1') {
            return ['upi_id' => 'demo.merchant@example', 'payee' => 'Velvet & Gold (DEMO)', 'demo' => true];
        }
        return null;
    }

    return ['upi_id' => $upiId, 'payee' => $payee];
}

function app_membership_prices(): array
{
    $defaults = [
        'Velvet Pass' => 1499,
        'Gold Reserve' => 4999,
        'Elite VIP' => 12500,
    ];
    $environmentNames = [
        'Velvet Pass' => 'APP_PRICE_VELVET_PASS',
        'Gold Reserve' => 'APP_PRICE_GOLD_RESERVE',
        'Elite VIP' => 'APP_PRICE_ELITE_VIP',
    ];
    foreach ($environmentNames as $tier => $name) {
        $configured = getenv($name);
        if ($configured !== false && preg_match('/^[1-9]\d{0,6}$/', $configured)) {
            $defaults[$tier] = (int) $configured;
        }
    }
    return $defaults;
}

function app_agent_plans(): array
{
    $plans = [
        'Young Unmarried' => 2500,
        'Married' => 4500,
        'Mature' => 6500,
    ];
    $environmentNames = [
        'Young Unmarried' => 'APP_AGENT_PRICE_YOUNG_UNMARRIED',
        'Married' => 'APP_AGENT_PRICE_MARRIED',
        'Mature' => 'APP_AGENT_PRICE_MATURE',
    ];
    foreach ($environmentNames as $plan => $name) {
        $configured = getenv($name);
        if ($configured !== false && preg_match('/^[1-9]\d{0,6}$/', $configured)) {
            $plans[$plan] = (int) $configured;
        }
    }
    return $plans;
}

function app_upi_intent(array $upi, string $reference, int $amountPaise, string $purpose = 'membership'): string
{
    $query = http_build_query([
        'pa' => $upi['upi_id'],
        'pn' => $upi['payee'],
        'tr' => $reference,
        'tn' => 'Velvet Gold ' . $purpose . ' ' . $reference,
        'am' => number_format($amountPaise / 100, 2, '.', ''),
        'cu' => 'INR',
    ], '', '&', PHP_QUERY_RFC3986);
    return 'upi://pay?' . $query;
}
