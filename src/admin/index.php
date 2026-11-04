<?php
/**
 * Administration dashboard.
 */
require_once dirname(__DIR__) . '/config.php';

// VULNERABILITY [AUTH-8]: require_admin() trusts the `role` cookie, so adding
// `Cookie: role=admin` in a browser or a curl request opens this panel.
require_admin();

$cache = new Cache(DATA_DIR . '/cache/dashboard.json');

if ($cache->isFresh(60)) {
    $stats = json_decode((string) $cache->read(), true) ?: [];
} else {
    $stats = [
        'users' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'products' => (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'orders' => (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'reviews' => (int) $db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        'revenue' => (float) $db->query('SELECT COALESCE(SUM(total), 0) FROM orders')->fetchColumn(),
        'low_stock' => (int) $db->query('SELECT COUNT(*) FROM products WHERE stock < 10')->fetchColumn(),
        'open_orders' => (int) $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'paid')")->fetchColumn(),
    ];

    $cache->write(json_encode($stats));
}

$lowStock = $db->query('SELECT id, name, stock FROM products WHERE stock < 15 ORDER BY stock ASC LIMIT 5')->fetchAll();

// VULNERABILITY [XSS-4]: the user agent is rendered unescaped below.
$recentLogs = $db->query('SELECT * FROM logs ORDER BY id DESC LIMIT 8')->fetchAll();

$pageTitle = 'Admin dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Admin dashboard</h1>
    <span class="badge badge--admin">administrator</span>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="alert alert--info">
    <strong>FLAG{4dm1n_p4n3l_4cc3ss}</strong> — you reached the admin area.
</div>

<div class="stats">
    <div class="stat">
        <div class="stat__label">Users</div>
        <div class="stat__value"><?= $stats['users'] ?? 0 ?></div>
        <div class="stat__hint">registered accounts</div>
    </div>
    <div class="stat">
        <div class="stat__label">Products</div>
        <div class="stat__value"><?= $stats['products'] ?? 0 ?></div>
        <div class="stat__hint"><?= $stats['low_stock'] ?? 0 ?> low on stock</div>
    </div>
    <div class="stat">
        <div class="stat__label">Orders</div>
        <div class="stat__value"><?= $stats['orders'] ?? 0 ?></div>
        <div class="stat__hint"><?= $stats['open_orders'] ?? 0 ?> still open</div>
    </div>
    <div class="stat">
        <div class="stat__label">Revenue</div>
        <div class="stat__value"><?= money($stats['revenue'] ?? 0) ?></div>
        <div class="stat__hint"><?= $stats['reviews'] ?? 0 ?> reviews</div>
    </div>
</div>

<div class="row" style="align-items: flex-start; gap: 1.5rem;">
    <div class="card" style="flex: 1 1 340px;">
        <h2>Low stock</h2>
        <?php if (!$lowStock): ?>
            <p class="muted">Everything is well stocked.</p>
        <?php else: ?>
            <table class="table table--compact">
                <thead><tr><th>Product</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $product): ?>
                    <tr>
                        <td><a href="/product.php?id=<?= (int) $product['id'] ?>"><?= h($product['name']) ?></a></td>
                        <td><?= (int) $product['stock'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card" style="flex: 1 1 440px;">
        <h2>Latest activity</h2>
        <table class="table table--compact">
            <thead><tr><th>Who</th><th>Action</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= h($log['username']) ?></td>
                    <td><?= h($log['action']) ?> — <?= h($log['message']) ?></td>
                    <td class="muted"><?= h($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><a class="button small secondary" href="/admin/logs.php">Open the full log</a></p>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
