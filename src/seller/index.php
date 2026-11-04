<?php
/**
 * Seller dashboard.
 */
require_once dirname(__DIR__) . '/config.php';

require_seller();

$me = current_user();
$sellerId = (int) ($me['id'] ?? 0);

if (is_admin() && !empty($_GET['all'])) {
    $products = $db->query('SELECT * FROM products ORDER BY id')->fetchAll();
} else {
    $stmt = $db->prepare('SELECT * FROM products WHERE seller_id = ? ORDER BY id');
    $stmt->execute([$sellerId]);
    $products = $stmt->fetchAll();
}

$totalStock = 0;
$inventoryValue = 0.0;

foreach ($products as $product) {
    $totalStock += (int) $product['stock'];
    $inventoryValue += (float) $product['price'] * (int) $product['stock'];
}

$pageTitle = 'Seller dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Seller dashboard</h1>
    <span class="badge badge--seller"><?= h($me['username'] ?? 'seller') ?></span>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat__label">Products</div>
        <div class="stat__value"><?= count($products) ?></div>
        <div class="stat__hint">listed by you</div>
    </div>
    <div class="stat">
        <div class="stat__label">Units in stock</div>
        <div class="stat__value"><?= $totalStock ?></div>
        <div class="stat__hint">across all products</div>
    </div>
    <div class="stat">
        <div class="stat__label">Inventory value</div>
        <div class="stat__value"><?= money($inventoryValue) ?></div>
        <div class="stat__hint">price × stock</div>
    </div>
</div>

<div class="row" style="margin-bottom: 1.5rem;">
    <a class="button" href="/seller/product_edit.php">New product</a>
    <a class="button secondary" href="/seller/bulk_import.php">Bulk import (XML)</a>
    <a class="button secondary" href="/seller/image_proxy.php">Image fetcher</a>
</div>

<?php if (!$products): ?>
    <p class="empty-state">You have no products yet. <a href="/seller/product_edit.php">Add the first one</a>.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td class="monospace"><?= (int) $product['id'] ?></td>
                <td><a href="/product.php?id=<?= (int) $product['id'] ?>"><?= h($product['name']) ?></a></td>
                <td><span class="badge"><?= h($product['category']) ?></span></td>
                <td><?= money($product['price']) ?></td>
                <td><?= (int) $product['stock'] ?></td>
                <td><a class="button small secondary" href="/seller/product_edit.php?id=<?= (int) $product['id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
