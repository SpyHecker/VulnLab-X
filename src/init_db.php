<?php
/**
 * Database installer / reset page.
 *
 * VULNERABILITY [MISC-2]: the installer stays reachable in production, needs no
 * authentication and happily wipes every table when asked to reset the lab.
 */
require_once __DIR__ . '/config.php';

$results = [];
$action = (string) ($_POST['action'] ?? '');

if ($action === 'install' || $action === 'reset') {
    try {
        $results = install_database($db, $action === 'reset');
        log_activity('database_' . $action, 'Database ' . $action . 'ed from init_db.php');
    } catch (PDOException $e) {
        $results = ['Error: ' . $e->getMessage()];
    }
}

$tables = ['users', 'products', 'reviews', 'orders', 'order_items', 'coupons', 'logs'];
$counts = [];

foreach ($tables as $table) {
    try {
        $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    } catch (PDOException $e) {
        $counts[$table] = '—';
    }
}

$pageTitle = 'Database setup';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Database setup</h1>
    <p class="muted monospace"><?= h(DB_FILE) ?></p>
</div>

<?php if ($results): ?>
    <div class="alert alert--success">
        <strong>Done.</strong>
        <ul>
            <?php foreach ($results as $line): ?>
                <li><?= h($line) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Current contents</h2>
    <table class="table table--compact">
        <thead>
            <tr><th>Table</th><th>Rows</th></tr>
        </thead>
        <tbody>
        <?php foreach ($counts as $table => $count): ?>
            <tr>
                <td class="monospace"><?= h($table) ?></td>
                <td><?= h($count) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row" style="margin-top: 1.5rem;">
        <form method="post" action="/init_db.php">
            <input type="hidden" name="action" value="install">
            <button type="submit">Create missing data</button>
        </form>

        <form method="post" action="/init_db.php">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="danger">Reset the whole lab</button>
        </form>

        <a class="button secondary" href="/">Back to the shop</a>
    </div>

    <p class="form-hint">
        Resetting drops every table and re-seeds the demo catalogue, accounts, coupons and orders.
    </p>
</div>

<div class="card">
    <h2>Demo accounts</h2>
    <table class="table table--compact">
        <thead>
            <tr><th>Username</th><th>Password</th><th>Role</th></tr>
        </thead>
        <tbody>
            <tr><td class="monospace">admin</td><td class="monospace">supersecretadmin123</td><td><span class="badge badge--admin">admin</span></td></tr>
            <tr><td class="monospace">alice</td><td class="monospace">alice123</td><td><span class="badge badge--customer">customer</span></td></tr>
            <tr><td class="monospace">bob</td><td class="monospace">bob123</td><td><span class="badge badge--customer">customer</span></td></tr>
            <tr><td class="monospace">seller</td><td class="monospace">seller123</td><td><span class="badge badge--seller">seller</span></td></tr>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
