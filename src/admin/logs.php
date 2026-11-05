<?php
/**
 * Admin activity log viewer.
 */
require_once dirname(__DIR__) . '/config.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    // VULNERABILITY [CSRF-4]: clearing the audit trail needs no token.
    $db->exec('DELETE FROM logs');
    flash('The activity log was cleared.', 'info');
    redirect('/admin/logs.php');
}

$filter = trim((string) ($_GET['q'] ?? ''));

if ($filter !== '') {
    // VULNERABILITY [SQLI-8]: another concatenated query, here in the log filter.
    $logs = $db->query(
        "SELECT * FROM logs WHERE action LIKE '%$filter%' OR message LIKE '%$filter%' OR username LIKE '%$filter%'
         ORDER BY id DESC LIMIT 200"
    )->fetchAll();
} else {
    $logs = $db->query('SELECT * FROM logs ORDER BY id DESC LIMIT 200')->fetchAll();
}

$pageTitle = 'Admin — activity log';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Activity log</h1>
    <p class="muted"><?= count($logs) ?> shown (max 200)</p>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<form class="search-bar" method="get" action="/admin/logs.php">
    <input type="search" name="q" placeholder="Filter by action, message or user…" value="<?= h($filter) ?>">
    <button type="submit">Filter</button>
</form>

<table class="table table--compact">
    <thead>
        <tr>
            <th>#</th>
            <th>When</th>
            <th>User</th>
            <th>Action</th>
            <th>Message</th>
            <th>IP</th>
            <th>User agent</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td class="monospace"><?= (int) $log['id'] ?></td>
            <td class="muted"><?= h($log['created_at']) ?></td>
            <td><?= h($log['username']) ?></td>
            <td class="monospace"><?= h($log['action']) ?></td>
            <?php
            // VULNERABILITY [XSS-4]: message and user agent are stored from the
            // request and printed here without escaping. A failed login with a
            // crafted User-Agent header therefore fires in the admin panel.
            ?>
            <td><?= $log['message'] ?></td>
            <td class="monospace"><?= h($log['ip_address']) ?></td>
            <td class="monospace"><?= $log['user_agent'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<form method="post" action="/admin/logs.php" style="margin-top: 1.5rem;">
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="danger small">Clear the log</button>
</form>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
