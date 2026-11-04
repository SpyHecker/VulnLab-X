<?php
/**
 * Store front: product catalogue, category filter and search.
 */
require_once __DIR__ . '/config.php';

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$products = [];
$error = '';

try {
    $categories = $db->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

    $conditions = [];

    if ($search !== '') {
        // VULNERABILITY [SQLI-1]: the search term is concatenated straight into
        // the query, so `' UNION SELECT ...` style payloads are executed.
        $conditions[] = "(name LIKE '%$search%' OR description LIKE '%$search%')";
    }

    if ($category !== '') {
        // VULNERABILITY [SQLI-5]: the category filter has the same problem.
        $conditions[] = "category = '$category'";
    }

    $sql = 'SELECT * FROM products';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY name';

    $products = $db->query($sql)->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    // VULNERABILITY [MISC-1]: the raw driver error (including the query text)
    // is displayed to the visitor.
    $error = $e->getMessage();
}

$pageTitle = 'Shop';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Products</h1>
    <p class="muted"><?= count($products) ?> item(s) available</p>
</div>

<form class="search-bar" method="get" action="/">
    <input type="search" name="q" placeholder="Search products…" value="<?= h($search) ?>" autocomplete="off">
    <?php if ($category !== ''): ?>
        <input type="hidden" name="category" value="<?= h($category) ?>">
    <?php endif; ?>
    <button type="submit">Search</button>
</form>

<div class="row" style="margin-bottom: 1.5rem;">
    <a class="badge" href="/">All</a>
    <?php foreach ($categories as $availableCategory): ?>
        <a class="badge<?= $availableCategory === $category ? ' badge--admin' : '' ?>"
           href="/?category=<?= urlencode($availableCategory) ?>"><?= h($availableCategory) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($search !== ''): ?>
    <?php // VULNERABILITY [XSS-1]: the search term is reflected without escaping. ?>
    <p class="search-summary">Showing results for: <strong><?= $search ?></strong></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><strong>Query failed:</strong> <?= h($error) ?></div>
<?php endif; ?>

<?php if (!$products): ?>
    <p class="empty-state">No products found.</p>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <article class="product-card">
                <a class="product-card__link" href="/product.php?id=<?= h($product['id']) ?>">
                    <img class="product-card__image"
                         src="<?= h(product_image_url($product['image'])) ?>"
                         alt="<?= h($product['name']) ?>">
                </a>
                <div class="product-card__body">
                    <h2 class="product-card__title">
                        <a class="product-card__link" href="/product.php?id=<?= h($product['id']) ?>"><?= h($product['name']) ?></a>
                    </h2>
                    <p class="product-card__description"><?= h($product['description']) ?></p>
                    <p class="price"><?= money($product['price']) ?></p>
                    <span class="badge"><?= h($product['category']) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
