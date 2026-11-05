<?php
/**
 * Admin user management.
 */
require_once dirname(__DIR__) . '/config.php';

require_admin();

$me = current_user();
$error = '';
$search = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULNERABILITY [CSRF-4]: role changes and deletions are plain POSTs with no
    // anti-CSRF token, so a malicious page can demote or delete accounts.
    $action = (string) ($_POST['action'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);

    try {
        if ($action === 'role') {
            $role = (string) ($_POST['role'] ?? 'customer');

            // VULNERABILITY [IDOR-5]: no privilege check on the target account,
            // any role string is accepted and an administrator can even demote
            // themselves by accident.
            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $userId]);

            log_activity('user_role_changed', 'Set role of user #' . $userId . ' to ' . $role);
            flash('Role updated for user #' . $userId . '.', 'success');
        } elseif ($action === 'delete') {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);

            log_activity('user_deleted', 'Deleted user #' . $userId);
            flash('User #' . $userId . ' was deleted.', 'info');
        }
    } catch (PDOException $e) {
        $error = 'The change failed: ' . $e->getMessage();
    }

    redirect('/admin/users.php');
}

try {
    if ($search !== '') {
        // VULNERABILITY [SQLI-7]: the admin search is injectable too, which turns
        // an admin-only page into a way to dump every table (including the
        // password column).
        $sql = "SELECT * FROM users
                WHERE username LIKE '%$search%' OR email LIKE '%$search%' OR bio LIKE '%$search%'
                ORDER BY id";

        $users = $db->query($sql)->fetchAll();
    } else {
        $users = $db->query('SELECT * FROM users ORDER BY id')->fetchAll();
    }
} catch (PDOException $e) {
    $users = [];
    $error = 'Query failed: ' . $e->getMessage();
}

$pageTitle = 'Admin — users';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Users</h1>
    <p class="muted"><?= count($users) ?> account(s)</p>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<form class="search-bar" method="get" action="/admin/users.php">
    <input type="search" name="q" placeholder="Search by username, email or bio…" value="<?= h($search) ?>">
    <button type="submit">Search</button>
</form>

<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Balance</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td class="monospace"><?= (int) $user['id'] ?></td>
            <td>
                <a href="/profile.php?id=<?= (int) $user['id'] ?>"><?= h($user['username']) ?></a>
                <?php if ((int) $user['id'] === (int) ($me['id'] ?? 0)): ?>
                    <span class="badge">you</span>
                <?php endif; ?>
            </td>
            <td><?= h($user['email']) ?></td>
            <td><span class="badge badge--<?= h($user['role']) ?>"><?= h($user['role']) ?></span></td>
            <td><?= money($user['balance']) ?></td>
            <td class="muted"><?= h($user['created_at']) ?></td>
            <td>
                <div class="table__actions">
                    <form method="post" action="/admin/users.php" class="row" style="gap: 0.35rem;">
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <select name="role" style="padding: 0.3rem; border-radius: 6px; border: 1px solid var(--border-color);">
                            <?php foreach (['customer', 'seller', 'admin'] as $role): ?>
                                <option value="<?= $role ?>"<?= $user['role'] === $role ? ' selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="small secondary">Set role</button>
                    </form>

                    <form method="post" action="/admin/users.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <button type="submit" class="small danger">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
