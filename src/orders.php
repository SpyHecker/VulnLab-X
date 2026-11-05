<?php
/**
 * The signed in customer's order history.
 */
require_once __DIR__ . '/config.php';

require_login();

$me = current_user();

$stmt = $db->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_lines
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.id DESC'
);
$stmt->execute([(int) $me['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Your orders';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Your orders</h1>
    <p class="muted"><?= count($orders) ?> order(s)</p>
</div>

<?php if (!$orders): ?>
    <p class="empty-state">You have not ordered anything yet. <a href="/">Start shopping</a>.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Placed</th>
                <th>Lines</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td class="monospace">#<?= (int) $order['id'] ?></td>
                <td><?= h($order['created_at']) ?></td>
                <td><?= (int) $order['item_lines'] ?></td>
                <td><?= money($order['total']) ?></td>
                <td><span class="badge badge--<?= h($order['status']) ?>"><?= h($order['status']) ?></span></td>
                <td><a class="button small secondary" href="/order.php?id=<?= (int) $order['id'] ?>">Details</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
