<?php
/**
 * Sign in.
 */
require_once __DIR__ . '/config.php';

$error = '';
$username = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // VULNERABILITY [AUTH-9]: there is no rate limiting, lockout or delay, so
    // passwords can be brute forced as fast as the server answers.
    try {
        // VULNERABILITY [SQLI-3]: both fields are concatenated into the query.
        // `admin' -- ` or `' OR '1'='1' -- ` bypasses authentication entirely.
        $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
        $user = $db->query($query)->fetch();

        if ($user) {
            // VULNERABILITY [AUTH-3]: the session id is never regenerated after
            // a successful login, so a fixed session can be hijacked.
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if (!empty($_POST['remember'])) {
                // VULNERABILITY [AUTH-2]: the cookie is base64("id:username"),
                // unsigned, and valid for 30 days. It can be forged offline.
                setcookie(
                    'remember_me',
                    base64_encode($user['id'] . ':' . $user['username']),
                    time() + 60 * 60 * 24 * 30,
                    '/'
                );
            }

            log_activity('login', 'Signed in successfully.');

            // VULNERABILITY [AUTH-4]: open redirect — `next` is used verbatim,
            // so `?next=https://evil.example` leaves the site after login.
            if ($next !== '') {
                redirect($next);
            }

            redirect('/');
        }

        $error = 'Invalid credentials.';
        log_activity('login_failed', 'Failed login for ' . $username);
    } catch (PDOException $e) {
        // VULNERABILITY [MISC-1]: the SQL error is shown to the visitor.
        $error = 'Database error: ' . $e->getMessage();
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h1>Log in</h1>
    <p class="muted">Sign in to manage your orders and profile.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <?php if ($next !== ''): ?>
            <input type="hidden" name="next" value="<?= h($next) ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="<?= h($username) ?>" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me for 30 days</span>
        </label>

        <button type="submit">Log in</button>
    </form>

    <p class="form-hint">No account yet? <a href="/register.php">Create one</a>.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
