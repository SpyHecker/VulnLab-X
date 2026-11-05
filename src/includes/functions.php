<?php
/**
 * Shared helpers used across the application.
 *
 * The comments tagged with a "VULNERABILITY" id map to the write-up in
 * ../solution.md, so the lab and its documentation stay in sync.
 */

/**
 * Single PDO connection for SQLite.
 *
 * The database is created and seeded automatically the first time the app is
 * opened, which keeps `docker compose up` a one step experience.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0777, true) && !is_dir(DATA_DIR)) {
        throw new PDOException('The data directory is missing and cannot be created: ' . DATA_DIR);
    }

    $isNewDatabase = !file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNewDatabase) {
        install_database($pdo, false);
    }

    return $pdo;
}

/* -------------------------------------------------------------------------
 * Output helpers
 * ---------------------------------------------------------------------- */

/** Escape a value for HTML output. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format an amount as US dollars. */
function money($amount): string
{
    return '$' . number_format((float) $amount, 2);
}

/** Send a redirect and stop the request. */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** Build the URL for a product image. */
function product_image_url(?string $image): string
{
    if ($image === null || $image === '') {
        return '/assets/img/placeholder.svg';
    }

    // VULNERABILITY [SSRF-2]: values starting with http(s):// are trusted and
    // rendered as-is. Any user who can write a product image (sellers, admins)
    // can therefore point visitors at an arbitrary third party URL.
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    if (str_starts_with($image, '/')) {
        return $image;
    }

    return '/assets/img/' . $image;
}

/* -------------------------------------------------------------------------
 * Flash messages (one-shot notifications stored in the session)
 * ---------------------------------------------------------------------- */

function flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/* -------------------------------------------------------------------------
 * Users and authorisation
 * ---------------------------------------------------------------------- */

function find_user(int $id): ?array
{
    global $db;
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/** The signed in user, or null for guests. */
function current_user(): ?array
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }

    global $db;
    $resolved = true;

    if (!empty($_SESSION['user_id'])) {
        $user = find_user((int) $_SESSION['user_id']);
        return $user;
    }

    // VULNERABILITY [AUTH-2]: the "remember me" cookie is just
    // base64("<id>:<username>"). Nothing is signed or hashed with a server
    // secret, so the cookie can be forged for any account.
    if (!empty($_COOKIE['remember_me'])) {
        $decoded = base64_decode($_COOKIE['remember_me'], true);

        if ($decoded !== false && str_contains($decoded, ':')) {
            [$rememberedId, $rememberedName] = explode(':', $decoded, 2);
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND username = ?');
            $stmt->execute([(int) $rememberedId, $rememberedName]);
            $user = $stmt->fetch() ?: null;

            if ($user) {
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
            }

            return $user;
        }
    }

    $user = null;

    return $user;
}

function is_admin(): bool
{
    // VULNERABILITY [AUTH-8]: the role is read from a client controlled
    // cookie before the session is even consulted, so `Cookie: role=admin`
    // grants administrator access.
    if (($_COOKIE['role'] ?? '') === 'admin') {
        return true;
    }

    $user = current_user();

    return ($user['role'] ?? '') === 'admin';
}

function is_seller(): bool
{
    if (in_array($_COOKIE['role'] ?? '', ['seller', 'admin'], true)) {
        return true;
    }

    $user = current_user();

    return in_array($user['role'] ?? '', ['seller', 'admin'], true);
}

function require_login(): void
{
    if (current_user() === null) {
        flash('Please sign in to continue.', 'error');
        redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('Administrator access is required.', 'error');
        redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    }
}

function require_seller(): void
{
    if (!is_seller()) {
        flash('A seller account is required for that page.', 'error');
        redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/seller/'));
    }
}

/* -------------------------------------------------------------------------
 * Activity log
 * ---------------------------------------------------------------------- */

/**
 * Record an event in the activity log that the admin dashboard displays.
 */
function log_activity(string $action, string $message): void
{
    global $db;

    $user = current_user();

    try {
        $stmt = $db->prepare(
            'INSERT INTO logs (user_id, username, action, message, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'] ?? null,
            $user['username'] ?? 'guest',
            $action,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? '',
            // VULNERABILITY [XSS-4]: the browser supplied user agent is stored
            // verbatim and rendered unescaped by the admin log viewer.
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (PDOException $e) {
        // Logging must never break the request.
    }
}

/* -------------------------------------------------------------------------
 * Shopping cart
 *
 * The cart lives in the session. Lines are keyed by product id and keep the
 * price that was sent by the browser when the product was added.
 * ---------------------------------------------------------------------- */

function cart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function cart_add(int $productId, string $name, float $price, int $quantity, string $image = ''): void
{
    $cart = cart();

    if (isset($cart[$productId])) {
        $cart[$productId]['qty'] += $quantity;
    } else {
        $cart[$productId] = [
            'name' => $name,
            'price' => $price,
            'qty' => $quantity,
            'image' => $image,
        ];
    }

    $_SESSION['cart'] = $cart;
}

function cart_set_quantity(int $productId, int $quantity): void
{
    $cart = cart();

    if ($quantity === 0) {
        unset($cart[$productId]);
    } elseif (isset($cart[$productId])) {
        $cart[$productId]['qty'] = $quantity;
    }

    $_SESSION['cart'] = $cart;
}

function cart_remove(int $productId): void
{
    $cart = cart();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
    // Coupons are discarded together with the cart.
    unset($_SESSION['coupons']);
}

function cart_count(): int
{
    $count = 0;

    foreach (cart() as $item) {
        $count += (int) $item['qty'];
    }

    return $count;
}

function cart_total(): float
{
    $total = 0.0;

    foreach (cart() as $item) {
        $total += (float) $item['price'] * (int) $item['qty'];
    }

    return round($total, 2);
}

/**
 * Discounts collected from the coupon form, applied one after another.
 */
function cart_coupons(): array
{
    return $_SESSION['coupons'] ?? [];
}

function cart_discount(float $subtotal): float
{
    $discount = 0.0;

    foreach (cart_coupons() as $coupon) {
        if (($coupon['type'] ?? 'percent') === 'percent') {
            // VULNERABILITY [LOGIC-3]: every coupon is applied to the full
            // subtotal, stacking is unlimited and the result is never capped.
            $discount += $subtotal * ((float) $coupon['value'] / 100);
        } else {
            $discount += (float) $coupon['value'];
        }
    }

    return round($discount, 2);
}
