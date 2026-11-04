<?php
/**
 * A single order.
 */
require_once __DIR__ . '/config.php';

require_login();

$me = current_user();
$orderId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    // VULNERABILITY [CSRF-3]: cancelling is a plain POST without a token.
    // VULNERABILITY [IDOR-4]: any order id can be cancelled, the session user is
    // never compared with the order owner.
    try {
        $stmt = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$orderId]);
        log_activity('order_cancelled', 'Cancelled order #' . $orderId);
        flash('Order #' . $orderId . ' was cancelled.', 'info');
        redirect('/order.php?id=' . $orderId);
    } catch (PDOException $e) {
        $error = 'Could not cancel the order: ' . $e->getMessage();
    }
}

$stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch() ?: null;

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert--error">No order with id ' . h($orderId) . ' exists.</div>';
    echo '<p><a class="button secondary" href="/orders.php">Your orders</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// VULNERABILITY [IDOR-1]: the order is returned to whoever asks for the id. Any
// customer can read every other customer's order by iterating /order.php?id=N.
$stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
$stmt->execute([(int) $order['id']]);
$items = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([(int) $order['user_id']]);
$customer = $stmt->fetch() ?: null;

$belongsToMe = (int) $order['user_id'] === (int) $me['id'];

$pageTitle = 'Order #' . $order['id'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Order #<?= (int) $order['id'] ?></h1>
    <span class="badge badge--<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$belongsToMe): ?>
    <div class="alert alert--info">
        This order belongs to <strong><?= h($customer['username'] ?? 'unknown') ?></strong>, not to you.
    </div>
<?php endif; ?>

<div class="card">
    <div class="row row--between">
        <div>
            <p class="muted">Placed <?= h($order['created_at']) ?> by <?= h($customer['username'] ?? 'unknown') ?> (<?= h($customer['email'] ?? '') ?>)</p>
            <p class="muted">Shipping to <?= h($order['shipping_address']) ?></p>
            <?php if ($order['coupon'] !== ''): ?>
                <p class="muted">Coupons: <?= h($order['coupon']) ?></p>
            <?php endif; ?>
        </div>
        <p class="price"><?= money($order['total']) ?></p>
    </div>

    <?php if ($order['notes'] !== ''): ?>
        <?php // VULNERABILITY [XSS-3]: order notes are printed unescaped as well. ?>
        <div class="alert alert--warning"><strong>Internal note:</strong> <?= $order['notes'] ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Items</h2>
    <table class="table table--compact">
        <thead>
            <tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/product.php?id=<?= (int) $item['product_id'] ?>"><?= h($item['name']) ?></a></td>
                <td><?= money($item['price']) ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= money((float) $item['price'] * (int) $item['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row row--between" style="margin-top: 1.5rem;">
        <a class="button secondary" href="/orders.php">Back to your orders</a>

        <?php if ($order['status'] !== 'cancelled'): ?>
            <form method="post" action="/order.php?id=<?= (int) $order['id'] ?>">
                <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="danger small">Cancel this order</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
