<?php
/**
 * JSON cart API.
 *
 * Accepts the same actions as cart.php but answers with JSON so a front end can
 * update the cart without a full page reload.
 */
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

$raw = file_get_contents('php://input');
$input = $_POST;

if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$action = (string) ($input['action'] ?? 'view');
$message = '';
$ok = true;

switch ($action) {
    case 'add':
        $productId = (int) ($input['product_id'] ?? 0);
        $quantity = (int) ($input['qty'] ?? 1);

        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $ok = false;
            $message = 'Unknown product.';
            break;
        }

        // VULNERABILITY [LOGIC-1]: same trust problem as cart.php — the unit
        // price is whatever the client sends.
        $price = isset($input['price']) ? (float) $input['price'] : (float) $product['price'];

        cart_add($productId, $product['name'], $price, $quantity, $product['image']);
        $message = 'Added to the cart.';
        break;

    case 'update':
        cart_set_quantity((int) ($input['product_id'] ?? 0), (int) ($input['qty'] ?? 1));
        $message = 'Cart updated.';
        break;

    case 'remove':
        cart_remove((int) ($input['product_id'] ?? 0));
        $message = 'Item removed.';
        break;

    case 'clear':
        cart_clear();
        $message = 'Cart emptied.';
        break;

    default:
        $message = 'Cart contents.';
        break;
}

$subtotal = cart_total();
$discount = cart_discount($subtotal);

echo json_encode([
    'ok' => $ok,
    'message' => $message,
    'cart' => array_values(cart()),
    'count' => cart_count(),
    'subtotal' => $subtotal,
    'discount' => $discount,
    'total' => round($subtotal - $discount, 2),
    'coupons' => array_column(cart_coupons(), 'code'),
], JSON_PRETTY_PRINT);
