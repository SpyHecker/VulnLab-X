<?php
/**
 * Checkout: turns the session cart into an order.
 */
require_once __DIR__ . '/config.php';

require_login();

$me = current_user();
$cart = cart();
$error = '';
$subtotal = cart_total();
$discount = cart_discount($subtotal);
$total = round($subtotal - $discount, 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim((string) ($_POST['shipping_address'] ?? ''));

    if (!$cart) {
        $error = 'Your cart is empty.';
    } elseif ($address === '') {
        $error = 'A shipping address is required.';
    } else {
        try {
            // VULNERABILITY [LOGIC-1]: the order total is the sum of the prices
            // the browser put in the cart, so the customer decides what to pay.
            // VULNERABILITY [LOGIC-5]: a negative total is accepted and later
            // turns into store credit instead of being rejected.
            $status = $total < 0 ? 'flagged' : 'paid';

            $stmt = $db->prepare(
                'INSERT INTO orders (user_id, total, status, coupon, shipping_address, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int) $me['id'],
                $total,
                $status,
                implode(',', array_column(cart_coupons(), 'code')),
                $address,
                '',
            ]);

            $orderId = (int) $db->lastInsertId();

            $insertItem = $db->prepare(
                'INSERT INTO order_items (order_id, product_id, name, price, quantity) VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($cart as $productId => $item) {
                $insertItem->execute([
                    $orderId,
                    (int) $productId,
                    $item['name'],
                    (float) $item['price'],
                    (int) $item['qty'],
                ]);

                // VULNERABILITY [LOGIC-6]: stock is adjusted with a blind UPDATE
                // outside any transaction. Two concurrent checkouts both pass
                // the availability check and oversell, and a negative quantity
                // simply increases the stock again.
                $db->exec(
                    'UPDATE products SET stock = stock - ' . (int) $item['qty']
                    . ' WHERE id = ' . (int) $productId
                );
            }

            if ($total < 0) {
                // VULNERABILITY [LOGIC-5]: paying a negative amount credits the
                // customer's balance. Repeat the trick to drain the shop.
                $db->exec('UPDATE users SET balance = balance + ' . abs($total) . ' WHERE id = ' . (int) $me['id']);
            }

            log_activity('order_placed', 'Placed order #' . $orderId . ' for ' . money($total));
            cart_clear();

            redirect('/checkout.php?order=' . $orderId);
        } catch (PDOException $e) {
            $error = 'Could not place the order: ' . $e->getMessage();
        }
    }
}

$placedOrder = null;
$placedItems = [];

if (!empty($_GET['order'])) {
    // VULNERABILITY [IDOR-1]: the confirmation loads any order id, it is not
    // limited to orders that belong to the current user.
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([(int) $_GET['order']]);
    $placedOrder = $stmt->fetch() ?: null;

    if ($placedOrder) {
        $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt->execute([(int) $placedOrder['id']]);
        $placedItems = $stmt->fetchAll();
    }
}

$pageTitle = $placedOrder ? 'Order #' . $placedOrder['id'] : 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<?php if ($placedOrder): ?>
    <div class="page-head">
        <h1>Thank you!</h1>
        <span class="badge badge--<?= h($placedOrder['status']) ?>"><?= h($placedOrder['status']) ?></span>
    </div>

    <div class="alert alert--success">
        Order <strong>#<?= (int) $placedOrder['id'] ?></strong> was placed for
        <strong><?= money($placedOrder['total']) ?></strong>. It will be shipped to
        <?= h($placedOrder['shipping_address']) ?>.
    </div>

    <?php if ((float) $placedOrder['total'] < 0): ?>
        <div class="alert alert--warning">
            <strong>Total below zero — the shop credited your account instead of charging you.</strong><br>
            FLAG{l0g1c_fl4w_n3g4t1v3}
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Items</h2>
        <table class="table table--compact">
            <thead>
                <tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
            <?php foreach ($placedItems as $item): ?>
                <tr>
                    <td><?= h($item['name']) ?></td>
                    <td><?= money($item['price']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td><?= money((float) $item['price'] * (int) $item['quantity']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 1.5rem;">
            <a class="button" href="/orders.php">View all orders</a>
            <a class="button secondary" href="/">Continue shopping</a>
        </p>
    </div>
<?php else: ?>
    <div class="page-head">
        <h1>Checkout</h1>
        <p class="muted">Signed in as <?= h($me['username']) ?></p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$cart): ?>
        <p class="empty-state">Your cart is empty. <a href="/">Browse the catalogue</a>.</p>
    <?php else: ?>
        <div class="card">
            <h2>Review your order</h2>
            <table class="table table--compact">
                <thead>
                    <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($cart as $productId => $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= money($item['price']) ?></td>
                        <td><?= (int) $item['qty'] ?></td>
                        <td><?= money((float) $item['price'] * (int) $item['qty']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <table class="table table--compact" style="margin-top: 1rem;">
                <tbody>
                    <tr><th>Subtotal</th><td><?= money($subtotal) ?></td></tr>
                    <tr><th>Discount</th><td><?= money($discount) ?></td></tr>
                    <tr><th>Total</th><td><strong><?= money($total) ?></strong></td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Shipping</h2>

            <form method="post" action="/checkout.php">
                <div class="form-group">
                    <label for="shipping_address">Shipping address</label>
                    <textarea id="shipping_address" name="shipping_address" placeholder="Street, city, postcode, country"></textarea>
                </div>

                <p class="form-hint">This is a mock checkout: no payment provider is contacted.</p>

                <button type="submit">Place order</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
