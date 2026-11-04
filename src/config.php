<?php
/**
 * VulnMart bootstrap.
 *
 * Every request starts here: error reporting, the session, the SQLite
 * connection and the shared helpers.
 *
 * WARNING: this application is intentionally insecure. See ../solution.md for
 * the full list of vulnerabilities and their fixes.
 */

// VULNERABILITY [MISC-1]: development error reporting is enabled, so PHP
// warnings, stack traces and raw PDO messages are shown to visitors.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// VULNERABILITY [AUTH-3]: the session cookie is readable from JavaScript
// (HttpOnly is off) and, further down, the session id is never regenerated
// after a successful login, which allows session fixation.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_NAME', 'VulnMart');
define('APP_ROOT', __DIR__);
define('DATA_DIR', getenv('DATA_DIR') ?: dirname(__DIR__) . '/data');
define('DB_FILE', getenv('DB_PATH') ?: DATA_DIR . '/vulnmart.db');
define('UPLOAD_DIR', APP_ROOT . '/uploads');

require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/schema.php';

try {
    $db = db();
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>Database error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p>Open <a href="/init_db.php">init_db.php</a> to create the database.</p>';
    exit;
}
