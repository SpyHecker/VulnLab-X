<?php
/**
 * Account registration.
 */
require_once __DIR__ . '/config.php';

$errors = [];
$username = '';
$email = '';
$bio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $bio = (string) ($_POST['bio'] ?? '');

    // VULNERABILITY [AUTH-5]: mass assignment. The role is taken from the
    // request, so adding `role=admin` to the form makes an administrator.
    $role = (string) ($_POST['role'] ?? 'customer');

    if (strlen($username) < 3) {
        $errors[] = 'The username must be at least 3 characters long.';
    }

    // VULNERABILITY [AUTH-1]: the only password rule is "not empty" and the
    // value is stored in plain text below.
    if ($password === '') {
        $errors[] = 'Please choose a password.';
    }

    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            // VULNERABILITY [SQLI-4]: the insert is built by concatenation, so
            // a quote in any field breaks out of the value.
            $sql = "INSERT INTO users (username, password, email, role, bio)
                    VALUES ('$username', '$password', '$email', '$role', '$bio')";

            $db->exec($sql);
            $userId = (int) $db->lastInsertId();

            // VULNERABILITY [AUTH-3]: no session id rotation on login either.
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;

            log_activity('register', 'Created the account ' . $username);

            // VULNERABILITY [XSS-5]: user input ends up in a flash message that
            // the layout prints unescaped.
            flash('Welcome to VulnMart, ' . $username . '!', 'success');

            redirect('/');
        } catch (PDOException $e) {
            // VULNERABILITY [MISC-1]: raw database errors, including whether a
            // username already exists.
            $errors[] = 'Could not create the account: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h1>Create an account</h1>
    <p class="muted">It takes a few seconds and keeps your orders together.</p>

    <?php if ($errors): ?>
        <div class="alert alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/register.php">
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="<?= h($username) ?>" autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= h($email) ?>" autocomplete="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" required>
        </div>

        <div class="form-group">
            <label for="confirm">Repeat password</label>
            <input id="confirm" type="password" name="confirm" autocomplete="new-password" required>
        </div>

        <div class="form-group">
            <label for="bio">Short bio <span class="muted">(optional)</span></label>
            <textarea id="bio" name="bio" placeholder="Tell other shoppers a little about yourself…"><?= h($bio) ?></textarea>
        </div>

        <button type="submit">Create account</button>
    </form>

    <p class="form-hint">Already registered? <a href="/login.php">Log in</a>.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
