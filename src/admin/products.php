<?php
/**
 * Admin product management.
 */
require_once dirname(__DIR__) . '/config.php';

require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULNERABILITY [CSRF-4]: creating and deleting products needs no token.
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = (string) ($_POST['description'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $image = trim((string) ($_POST['image'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'misc'));
            $stock = (int) ($_POST['stock'] ?? 0);

            // VULNERABILITY [AUTH-5]: seller_id is taken from the request as
            // well, so a product can be attributed to any seller.
            $sellerId = isset($_POST['seller_id']) ? (int) $_POST['seller_id'] : null;

            if ($name === '') {
                $error = 'A product name is required.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO products (name, description, price, image, category, stock, seller_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $description, $price, $image, $category, $stock, $sellerId]);

                log_activity('product_created', 'Created product ' . $name);

                // VULNERABILITY [XSS-5]: the flash message embeds the raw name and
                // the layout prints it unescaped.
                flash('Product "' . $name . '" created.', 'success');
                redirect('/admin/products.php');
            }
        } elseif ($action === 'delete') {
            $productId = (int) ($_POST['product_id'] ?? 0);

            $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$productId]);

            log_activity('product_deleted', 'Deleted product #' . $productId);
            flash('Product #' . $productId . ' was deleted.', 'info');
            redirect('/admin/products.php');
        }
    } catch (PDOException $e) {
        $error = 'The change failed: ' . $e->getMessage();
    }
}

$products = $db->query(
    'SELECT p.*, u.username AS seller
     FROM products p
     LEFT JOIN users u ON u.id = p.seller_id
     ORDER BY p.id'
)->fetchAll();

$pageTitle = 'Admin — products';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-head">
    <h1>Products</h1>
    <p class="muted"><?= count($products) ?> product(s)</p>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Seller</th>
            <th>Actions</th>
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
            <td><?= h($product['seller'] ?? '—') ?></td>
            <td>
                <div class="table__actions">
                    <a class="button small secondary" href="/seller/product_edit.php?id=<?= (int) $product['id'] ?>">Edit</a>
                    <form method="post" action="/admin/products.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <button type="submit" class="small danger">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="card" style="margin-top: 2rem;">
    <h2>Add a product</h2>

    <form method="post" action="/admin/products.php">
        <input type="hidden" name="action" value="create">

        <div class="row" style="align-items: flex-start;">
            <div class="form-group" style="flex: 1 1 260px;">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" required>
            </div>

            <div class="form-group" style="flex: 1 1 160px;">
                <label for="category">Category</label>
                <input id="category" type="text" name="category" value="misc">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description"></textarea>
        </div>

        <div class="row" style="align-items: flex-start;">
            <div class="form-group" style="flex: 1 1 140px;">
                <label for="price">Price</label>
                <input id="price" type="number" step="0.01" name="price" value="0">
            </div>

            <div class="form-group" style="flex: 1 1 140px;">
                <label for="stock">Stock</label>
                <input id="stock" type="number" name="stock" value="0">
            </div>

            <div class="form-group" style="flex: 2 1 260px;">
                <label for="image">Image</label>
                <input id="image" type="text" name="image" placeholder="laptop.svg or https://…">
                <p class="form-hint">A file name from /assets/img, an absolute path, or any remote URL.</p>
            </div>
        </div>

        <button type="submit">Create product</button>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
