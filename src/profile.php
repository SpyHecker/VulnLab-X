<?php
/**
 * User profile: view and update.
 */
require_once __DIR__ . '/config.php';

require_login();

$me = current_user();

// VULNERABILITY [IDOR-2]: the profile that is loaded is chosen purely by the
// `id` parameter (or by the posted user_id), never by the session.
$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? $me['id']);
$profile = find_user($userId);
$error = '';

if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert--error">No user with id ' . h($userId) . ' exists.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $bio = (string) ($_POST['bio'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // VULNERABILITY [CSRF-1]: no anti-CSRF token is checked, so any site can
    // submit this form on behalf of a signed in visitor.
    // VULNERABILITY [IDOR-3]: the row that is written is taken from the hidden
    // user_id field, so the form can be pointed at any account.
    try {
        if ($password !== '') {
            // VULNERABILITY [AUTH-7]: changing the password does not require
            // the current one, and the new value is stored in plain text.
            $stmt = $db->prepare('UPDATE users SET email = ?, bio = ?, password = ? WHERE id = ?');
            $stmt->execute([$email, $bio, $password, $userId]);
        } else {
            $stmt = $db->prepare('UPDATE users SET email = ?, bio = ? WHERE id = ?');
            $stmt->execute([$email, $bio, $userId]);
        }

        log_activity('profile_updated', 'Updated the profile of ' . $profile['username']);
        flash('Profile saved.', 'success');
        redirect('/profile.php?id=' . $userId);
    } catch (PDOException $e) {
        $error = 'Could not save the profile: ' . $e->getMessage();
    }

    $profile = find_user($userId);
}

$isOwnProfile = (int) $profile['id'] === (int) $me['id'];
$pageTitle = $profile['username'] . ' — profile';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1><?= h($profile['username']) ?></h1>
    <span class="badge badge--<?= h($profile['role']) ?>"><?= h($profile['role']) ?></span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$isOwnProfile): ?>
    <div class="alert alert--info">
        You are viewing somebody else's profile (<span class="monospace">/profile.php?id=<?= (int) $profile['id'] ?></span>).
    </div>
<?php endif; ?>

<div class="card">
    <h2>About</h2>
    <?php // VULNERABILITY [XSS-3]: the biography is rendered without escaping, so stored HTML/JS runs for every visitor. ?>
    <div><?= $profile['bio'] !== '' ? nl2br($profile['bio']) : '<span class="muted">No biography yet.</span>' ?></div>
</div>

<div class="card">
    <h2>Account details</h2>
    <table class="table table--compact">
        <tbody>
            <tr><th>Username</th><td><?= h($profile['username']) ?></td></tr>
            <tr><th>Email</th><td><?= h($profile['email']) ?></td></tr>
            <tr><th>Role</th><td><span class="badge badge--<?= h($profile['role']) ?>"><?= h($profile['role']) ?></span></td></tr>
            <tr><th>Store credit</th><td><?= money($profile['balance']) ?></td></tr>
            <tr><th>Member since</th><td><?= h($profile['created_at']) ?></td></tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Edit profile</h2>

    <form method="post" action="/profile.php?id=<?= (int) $profile['id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int) $profile['id'] ?>">

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= h($profile['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio"><?= h($profile['bio']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password">
            <p class="form-hint">Leave empty to keep the current password. The current password is not required to set a new one.</p>
        </div>

        <button type="submit">Save profile</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
