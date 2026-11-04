<?php
/**
 * Create or edit a product.
 */
require_once dirname(__DIR__) . '/config.php';

require_seller();

$me = current_user();
$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$product = null;

if ($productId > 0) {
    // VULNERABILITY [IDOR-3]: the product is loaded by id only. A seller can
    // open and overwrite any other seller's product by changing the id, and the
    // admin panel links straight into this page.
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch() ?: null;

    if (!$product) {
        $error = 'Product #' . $productId . ' does not exist.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = (string) ($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $image = trim((string) ($_POST['image'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'misc'));
    $stock = (int) ($_POST['stock'] ?? 0);

    if ($name === '') {
        $error = 'A product name is required.';
    } else {
        try {
            if ($product) {
                // VULNERABILITY [AUTH-5]: seller_id can be overwritten from the
                // request, so ownership of a product can be handed to anybody.
                $sellerId = isset($_POST['seller_id']) ? (int) $_POST['seller_id'] : (int) $product['seller_id'];

                $stmt = $db->prepare(
                    'UPDATE products
                     SET name = ?, description = ?, price = ?, image = ?, category = ?, stock = ?, seller_id = ?
                     WHERE id = ?'
                );
                $stmt->execute([$name, $description, $price, $image, $category, $stock, $sellerId, $productId]);

                log_activity('product_updated', 'Updated product ' . $name);
                flash('Product "' . $name . '" saved.', 'success');
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO products (name, description, price, image, category, stock, seller_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $description, $price, $image, $category, $stock, (int) ($me['id'] ?? 0)]);

                log_activity('product_created', 'Created product ' . $name);
                flash('Product "' . $name . '" created.', 'success');
            }

            redirect('/seller/');
        } catch (PDOException $e) {
            $error = 'Could not save the product: ' . $e->getMessage();
        }
    }
}

$pageTitle = $product ? 'Edit ' . $product['name'] : 'New product';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1><?= $product ? 'Edit product #' . (int) $product['id'] : 'New product' ?></h1>
    <p class="muted">Seller area</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card form-container--wide" style="margin: 0; max-width: none;">
    <form method="post" action="/seller/product_edit.php<?= $product ? '?id=' . (int) $product['id'] : '' ?>">
        <?php if ($product): ?>
            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
        <?php endif; ?>

        <div class="row" style="align-items: flex-start;">
            <div class="form-group" style="flex: 2 1 260px;">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="<?= h($product['name'] ?? '') ?>" required>
            </div>

            <div class="form-group" style="flex: 1 1 160px;">
                <label for="category">Category</label>
                <input id="category" type="text" name="category" value="<?= h($product['category'] ?? 'misc') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"><?= h($product['description'] ?? '') ?></textarea>
        </div>

        <div class="row" style="align-items: flex-start;">
            <div class="form-group" style="flex: 1 1 140px;">
                <label for="price">Price</label>
                <input id="price" type="number" step="0.01" name="price" value="<?= h($product['price'] ?? '0') ?>">
            </div>

            <div class="form-group" style="flex: 1 1 140px;">
                <label for="stock">Stock</label>
                <input id="stock" type="number" name="stock" value="<?= (int) ($product['stock'] ?? 0) ?>">
            </div>

            <div class="form-group" style="flex: 2 1 240px;">
                <label for="image">Image</label>
                <input id="image" type="text" name="image" value="<?= h($product['image'] ?? '') ?>" placeholder="laptop.svg">
                <p class="form-hint">A file from /assets/img, an absolute path, or any remote URL.</p>
            </div>
        </div>

        <button type="submit"><?= $product ? 'Save changes' : 'Create product' ?></button>
        <a class="button secondary" href="/seller/">Cancel</a>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
