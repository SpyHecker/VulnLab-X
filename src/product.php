<?php
/**
 * Product detail page.
 */
require_once __DIR__ . '/config.php';

$id = $_GET['id'] ?? '';
$product = null;
$error = '';

if ($id === '') {
    $error = 'No product id was supplied.';
} else {
    try {
        // VULNERABILITY [SQLI-2]: the id coming from the query string is
        // concatenated into the query without casting or binding it.
        $product = $db->query('SELECT * FROM products WHERE id = ' . $id)->fetch();

        if (!$product) {
            $error = 'Product not found.';
        }
    } catch (PDOException $e) {
        // VULNERABILITY [MISC-1]: verbose database errors.
        $error = 'Query failed: ' . $e->getMessage();
    }
}

$pageTitle = $product['name'] ?? 'Product';
require __DIR__ . '/includes/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
    <p><a class="button secondary" href="/">Back to the shop</a></p>
<?php else: ?>
    <p class="muted"><a href="/">← Back to the shop</a></p>

    <article class="product-detail">
        <div>
            <?php
            // VULNERABILITY [SSRF-2]: remote image URLs are rendered as-is. If
            // the application ever fetched the image server side, the same
            // field would be a server side request forgery primitive.
            ?>
            <img class="product-detail__image"
                 src="<?= h(product_image_url($product['image'])) ?>"
                 alt="<?= h($product['name']) ?>">
        </div>

        <div class="stack">
            <div>
                <h1><?= h($product['name']) ?></h1>
                <p class="price"><?= money($product['price']) ?></p>
            </div>

            <p><?= h($product['description']) ?></p>

            <div class="product-detail__meta">
                <span class="badge"><?= h($product['category']) ?></span>
                <span><?= (int) $product['stock'] ?> in stock</span>
                <span>Product #<?= (int) $product['id'] ?></span>
            </div>
        </div>
    </article>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
