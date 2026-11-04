<?php
/**
 * Shopping cart.
 */
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULNERABILITY [CSRF-2]: cart actions are plain POST requests without any
    // anti-CSRF token.
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['qty'] ?? 1);

        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $error = 'That product does not exist.';
        } else {
            // VULNERABILITY [LOGIC-1]: the price stored in the cart comes from
            // the hidden field submitted by the browser, not from the database.
            // Changing it to 0.01 makes the checkout charge a cent.
            $price = isset($_POST['price']) ? (float) $_POST['price'] : (float) $product['price'];

            // VULNERABILITY [LOGIC-2]: negative quantities are accepted as-is.
            cart_add($productId, $product['name'], $price, $quantity, $product['image']);

            flash(h($product['name']) . ' was added to your cart.', 'success');
            redirect('/cart.php');
        }
    } elseif ($action === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $productId => $quantity) {
            cart_set_quantity((int) $productId, (int) $quantity);
        }

        flash('Cart updated.', 'success');
        redirect('/cart.php');
    } elseif ($action === 'remove') {
        cart_remove((int) ($_POST['product_id'] ?? 0));
        flash('Item removed.', 'info');
        redirect('/cart.php');
    } elseif ($action === 'clear') {
        cart_clear();
        flash('Cart emptied.', 'info');
        redirect('/cart.php');
    } elseif ($action === 'coupon') {
        $code = strtoupper(trim((string) ($_POST['coupon'] ?? '')));

        $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1');
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if ($coupon) {
            // VULNERABILITY [LOGIC-3]: coupons are appended to a list instead of
            // replacing the previous one, and the same code can be applied over
            // and over again.
            $_SESSION['coupons'][] = [
                'code' => $coupon['code'],
                'type' => $coupon['type'],
                'value' => (float) $coupon['value'],
            ];

            flash('Coupon ' . h($coupon['code']) . ' applied.', 'success');
        } else {
            flash('That coupon code is not valid.', 'error');
        }

        redirect('/cart.php');
    }
}

$cart = cart();
$subtotal = cart_total();
$discount = cart_discount($subtotal);
$total = round($subtotal - $discount, 2);

$pageTitle = 'Cart';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Your cart</h1>
    <p class="muted"><?= count($cart) ?> line(s), <?= cart_count() ?> item(s)</p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$cart): ?>
    <p class="empty-state">Your cart is empty. <a href="/">Browse the catalogue</a>.</p>
<?php else: ?>
    <form method="post" action="/cart.php">
        <input type="hidden" name="action" value="update">

        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th style="width: 120px;">Quantity</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cart as $productId => $item): ?>
                <tr>
                    <td>
                        <a href="/product.php?id=<?= (int) $productId ?>"><?= h($item['name']) ?></a>
                    </td>
                    <td><?= money($item['price']) ?></td>
                    <td>
                        <input class="monospace" type="number" name="qty[<?= (int) $productId ?>]"
                               value="<?= (int) $item['qty'] ?>" style="width: 90px; padding: 0.35rem;">
                    </td>
                    <td><?= money((float) $item['price'] * (int) $item['qty']) ?></td>
                    <td>
                        <button class="small secondary" type="submit" form="remove-<?= (int) $productId ?>">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row row--between" style="margin-top: 1rem;">
            <button type="submit" class="auto-width">Update quantities</button>
            <button type="submit" class="secondary auto-width" form="clear-cart">Empty cart</button>
        </div>
    </form>

    <?php foreach ($cart as $productId => $item): ?>
        <form id="remove-<?= (int) $productId ?>" method="post" action="/cart.php" class="inline-form">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
        </form>
    <?php endforeach; ?>

    <form id="clear-cart" method="post" action="/cart.php" class="inline-form">
        <input type="hidden" name="action" value="clear">
    </form>

    <div class="card" style="margin-top: 2rem;">
        <div class="row row--between">
            <div>
                <h2>Order summary</h2>
                <table class="table table--compact">
                    <tbody>
                        <tr><th>Subtotal</th><td><?= money($subtotal) ?></td></tr>
                        <tr>
                            <th>Discount</th>
                            <td>
                                <?= money($discount) ?>
                                <?php foreach (cart_coupons() as $coupon): ?>
                                    <span class="badge"><?= h($coupon['code']) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr><th>Total</th><td><strong><?= money($total) ?></strong></td></tr>
                    </tbody>
                </table>
            </div>

            <div>
                <form method="post" action="/cart.php" class="row" style="gap: 0.5rem;">
                    <input type="hidden" name="action" value="coupon">
                    <input type="text" name="coupon" placeholder="Coupon code" style="padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                    <button type="submit" class="secondary">Apply</button>
                </form>

                <p><a class="button" href="/checkout.php">Proceed to checkout</a></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
