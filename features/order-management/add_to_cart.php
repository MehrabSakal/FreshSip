<?php
/**
 * Add a product (with customizations) to the session cart.
 * Epic 001 - US001-US003 customization: "no sugar", "extra ice", "add protein".
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = min(99, max(1, (int) ($_POST['quantity'] ?? 1)));

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_available = 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Sorry, that item is not available.');
    redirect('index.php');
}

// Collect supported, conflict-free customizations from the shared catalog.
$chosen  = normalize_customizations($_POST['customizations'] ?? []);
$custKey = implode('|', $chosen);
$custStr = customizations_to_string($chosen);
$unitPrice = round((float) $product['price'] + customization_surcharge($chosen), 2);

// Line key: same product + same customizations stacks together.
$key = $productId . ':' . md5($custKey);

$cart = cart();
if (isset($cart[$key])) {
    $cart[$key]['quantity'] += $quantity;
} else {
    $cart[$key] = [
        'product_id'     => $productId,
        'name'           => $product['name'],
        'price'          => $unitPrice,
        'icon'           => $product['icon'],
        'quantity'       => $quantity,
        'customizations' => $custStr,
    ];
}

if ($stockError = cart_stock_error($pdo, $cart)) {
    flash('error', $stockError);
    redirect('index.php');
}

$_SESSION['cart'] = $cart;

flash('success', $product['name'] . ' added to cart.');
redirect('features/order-management/cart.php');
