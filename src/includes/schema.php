<?php
/**
 * Database schema and seed data.
 *
 * `install_database()` is called automatically the first time the app opens
 * (see functions.php) and can be triggered again from /init_db.php, which is
 * also how the lab is reset between exercises.
 */

/**
 * Create the tables (if needed) and optionally seed the demo data.
 *
 * @return string[] Human readable progress messages.
 */
function install_database(PDO $db, bool $reset = false, bool $seed = true): array
{
    $messages = [];

    if ($reset) {
        $db->exec('PRAGMA foreign_keys = OFF');
        foreach (['order_items', 'orders', 'reviews', 'logs', 'products', 'users', 'coupons'] as $table) {
            $db->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $db->exec('PRAGMA foreign_keys = ON');
        $messages[] = 'Dropped all existing tables.';
    }

    create_schema($db);
    $messages[] = 'Schema is up to date.';

    if ($seed) {
        $messages = array_merge($messages, seed_database($db));
    }

    return $messages;
}

function create_schema(PDO $db): void
{
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            username   TEXT    NOT NULL UNIQUE,
            password   TEXT    NOT NULL,
            email      TEXT    NOT NULL,
            role       TEXT    NOT NULL DEFAULT 'customer',
            bio        TEXT    NOT NULL DEFAULT '',
            balance    REAL    NOT NULL DEFAULT 100.0,
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS products (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            description TEXT    NOT NULL DEFAULT '',
            price       REAL    NOT NULL DEFAULT 0,
            image       TEXT    NOT NULL DEFAULT '',
            category    TEXT    NOT NULL DEFAULT 'misc',
            stock       INTEGER NOT NULL DEFAULT 0,
            seller_id   INTEGER,
            created_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS reviews (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            user_id    INTEGER,
            author     TEXT    NOT NULL,
            rating     INTEGER NOT NULL DEFAULT 5,
            comment    TEXT    NOT NULL DEFAULT '',
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS orders (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id          INTEGER NOT NULL,
            total            REAL    NOT NULL DEFAULT 0,
            status           TEXT    NOT NULL DEFAULT 'pending',
            coupon           TEXT    NOT NULL DEFAULT '',
            shipping_address TEXT    NOT NULL DEFAULT '',
            notes            TEXT    NOT NULL DEFAULT '',
            created_at       TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS order_items (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id   INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            name       TEXT    NOT NULL,
            price      REAL    NOT NULL DEFAULT 0,
            quantity   INTEGER NOT NULL DEFAULT 1
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS coupons (
            id     INTEGER PRIMARY KEY AUTOINCREMENT,
            code   TEXT    NOT NULL UNIQUE,
            type   TEXT    NOT NULL DEFAULT 'percent',
            value  REAL    NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1
        )
    SQL);

    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS logs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER,
            username   TEXT    NOT NULL DEFAULT 'guest',
            action     TEXT    NOT NULL DEFAULT '',
            message    TEXT    NOT NULL DEFAULT '',
            ip_address TEXT    NOT NULL DEFAULT '',
            user_agent TEXT    NOT NULL DEFAULT '',
            created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    SQL);

    $db->exec('CREATE INDEX IF NOT EXISTS idx_products_category ON products (category)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_reviews_product ON reviews (product_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_orders_user ON orders (user_id)');
}

/**
 * Insert the demo catalogue, accounts and a couple of orders.
 */
function seed_database(PDO $db): array
{
    $messages = [];

    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($userCount === 0) {
        $insertUser = $db->prepare(
            'INSERT INTO users (username, password, email, role, bio, balance) VALUES (?, ?, ?, ?, ?, ?)'
        );

        // VULNERABILITY [AUTH-1]: passwords are stored in plain text.
        $users = [
            [
                'admin',
                'supersecretadmin123',
                'admin@vulnmart.local',
                'admin',
                "Platform administrator.\n\nFLAG{1d0r_pr0f1l3_r34d}\n\nBackups of the internal notes are kept next to the database (flag_backup.txt).",
                500.00,
            ],
            ['alice', 'alice123', 'alice@example.com', 'customer', 'Coffee, cameras and clean code.', 150.00],
            ['bob', 'bob123', 'bob@example.com', 'customer', 'Collector of mechanical keyboards.', 75.00],
            ['seller', 'seller123', 'seller@vulnmart.local', 'seller', 'Official VulnMart seller account.', 250.00],
        ];

        foreach ($users as $user) {
            $insertUser->execute($user);
        }

        $messages[] = 'Seeded ' . count($users) . ' users (admin, alice, bob, seller).';
    }

    $productCount = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();

    if ($productCount === 0) {
        $insertProduct = $db->prepare(
            'INSERT INTO products (name, description, price, image, category, stock, seller_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $products = [
            ['Laptop', 'A 14 inch laptop with 16 GB of RAM, a 1 TB SSD and all-day battery life.', 999.99, 'laptop.svg', 'electronics', 12, 4],
            ['Smartphone', 'Latest model with a 120 Hz OLED display and a 50 MP camera.', 699.99, 'phone.svg', 'electronics', 25, 4],
            ['Headphones', 'Noise cancelling over-ear headphones with 30 hours of playback.', 199.99, 'headphones.svg', 'accessories', 40, 4],
            ['Mechanical Keyboard', 'Hot-swappable mechanical keyboard with an aluminium case and RGB backlight.', 129.50, 'keyboard.svg', 'accessories', 30, 4],
            ['Smart Watch', 'Fitness tracking, GPS and a five day battery in a titanium case.', 249.00, 'watch.svg', 'electronics', 18, 4],
            ['Camera', 'Mirrorless camera with a 24 MP sensor and a compact prime lens.', 549.00, 'camera.svg', 'electronics', 7, 4],
            ['Backpack', 'Water resistant 25 litre backpack with a padded laptop sleeve.', 79.99, 'backpack.svg', 'accessories', 60, 4],
            ['Coffee Maker', 'Programmable coffee maker with a thermal carafe and a timer.', 89.50, 'coffee.svg', 'home', 22, 4],
        ];

        foreach ($products as $product) {
            $insertProduct->execute($product);
        }

        $messages[] = 'Seeded ' . count($products) . ' products.';
    }

    $reviewCount = (int) $db->query('SELECT COUNT(*) FROM reviews')->fetchColumn();

    if ($reviewCount === 0) {
        $insertReview = $db->prepare(
            'INSERT INTO reviews (product_id, user_id, author, rating, comment) VALUES (?, ?, ?, ?, ?)'
        );

        $insertReview->execute([1, 2, 'alice', 5, 'Fantastic battery life, the keyboard is great too.']);
        $insertReview->execute([1, 3, 'bob', 4, 'Runs a little hot under load, otherwise excellent.']);
        $insertReview->execute([3, 2, 'alice', 5, 'The best headphones I have owned so far.']);

        $messages[] = 'Seeded 3 product reviews.';
    }

    $couponCount = (int) $db->query('SELECT COUNT(*) FROM coupons')->fetchColumn();

    if ($couponCount === 0) {
        $insertCoupon = $db->prepare('INSERT INTO coupons (code, type, value, active) VALUES (?, ?, ?, 1)');

        $insertCoupon->execute(['WELCOME10', 'percent', 10]);
        $insertCoupon->execute(['VIP20', 'percent', 20]);
        $insertCoupon->execute(['SHIP5', 'amount', 5]);

        $messages[] = 'Seeded 3 discount coupons.';
    }

    $orderCount = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();

    if ($orderCount === 0) {
        $insertOrder = $db->prepare(
            'INSERT INTO orders (user_id, total, status, coupon, shipping_address, notes) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertItem = $db->prepare(
            'INSERT INTO order_items (order_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)'
        );

        // Order 1 belongs to bob. It is used to demonstrate the IDOR in
        // order.php (visit /order.php?id=1 while signed in as alice).
        $insertOrder->execute([
            3,
            699.99,
            'paid',
            '',
            '12 Baker Street, London',
            'FLAG{1d0r_0rd3r_d3t41l} — internal note: express courier, do not reschedule.',
        ]);
        $orderId = (int) $db->lastInsertId();
        $insertItem->execute([$orderId, 2, 'Smartphone', 699.99, 1]);

        $insertOrder->execute([
            2,
            1199.98,
            'shipped',
            'WELCOME10',
            '5 Market Road, Manchester',
            'Gift wrap requested.',
        ]);
        $orderId = (int) $db->lastInsertId();
        $insertItem->execute([$orderId, 1, 'Laptop', 999.99, 1]);
        $insertItem->execute([$orderId, 3, 'Headphones', 199.99, 1]);

        $messages[] = 'Seeded 2 orders.';
    }

    $logCount = (int) $db->query('SELECT COUNT(*) FROM logs')->fetchColumn();

    if ($logCount === 0) {
        $insertLog = $db->prepare(
            'INSERT INTO logs (user_id, username, action, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)'
        );

        $insertLog->execute([2, 'alice', 'login', 'Signed in successfully.', '198.51.100.24', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/128.0']);
        $insertLog->execute([null, 'guest', 'login_failed', 'Failed login for admin.', '203.0.113.66', '<script>alert("stored XSS in the admin log viewer")</script>']);
        $insertLog->execute([4, 'seller', 'product_updated', 'Updated stock for Camera.', '198.51.100.31', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) Safari/17.5']);

        $messages[] = 'Seeded 3 activity log entries.';
    }

    // The backup file lives outside the web root on purpose: it is the target
    // for the local file read vulnerabilities (XXE, object injection).
    $flagFile = DATA_DIR . '/flag_backup.txt';

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0777, true);
    }

    if (!file_exists($flagFile)) {
        @file_put_contents($flagFile, <<<'TXT'
VulnMart internal backup notes
==============================

FLAG{l0c4l_f1l3_1nclus10n}

This file is stored outside the web root. It is only reachable through the
file handling bugs built into the lab (XML external entities in the seller
bulk import, or object injection in the preferences cookie).
TXT);
        $messages[] = 'Created data/flag_backup.txt.';
    }

    return $messages;
}
