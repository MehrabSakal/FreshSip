<?php
/**
 * Customer-facing order actions: cancel a pending order or reorder a past one.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('features/order-management/my_orders.php');
}
csrf_check();

$user    = current_user();
$action  = $_POST['action'] ?? '';
$orderId = (int) ($_POST['id'] ?? 0);

// The order must belong to the current customer.
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('features/order-management/my_orders.php');
}

if ($action === 'cancel') {
    [$ok, $msg] = cancel_order($pdo, $orderId, (int) $user['id']);
    flash($ok ? 'success' : 'error', $msg);
    redirect('features/order-management/my_orders.php');
}

if ($action === 'reorder') {
    $itemStmt = $pdo->prepare('SELECT product_id, product_name, quantity, customizations FROM order_items WHERE order_id = ?');
    $itemStmt->execute([$orderId]);

    $prodStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_available = 1');

    $cart      = cart();
    $added     = 0;
    $skipped   = 0;

    foreach ($itemStmt->fetchAll() as $it) {
        if (!$it['product_id']) {
            $skipped++;
            continue;
        }
        $prodStmt->execute([$it['product_id']]);
        $product = $prodStmt->fetch();
        if (!$product) {
            $skipped++;
            continue;
        }

        $custStr = trim((string) $it['customizations']);
        $chosen  = $custStr !== '' ? array_map('trim', explode(',', $custStr)) : [];
        $custKey = implode('|', $chosen);
        $key     = $it['product_id'] . ':' . md5($custKey);
        $qty     = max(1, (int) $it['quantity']);

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $qty;
        } else {
            $cart[$key] = [
                'product_id'     => (int) $product['id'],
                'name'           => $product['name'],
                'price'          => (float) $product['price'],
                'icon'           => $product['icon'],
                'quantity'       => $qty,
                'customizations' => $custStr,
            ];
        }
        $added++;
    }

    $_SESSION['cart'] = $cart;

    if ($added === 0) {
        flash('error', 'None of those items are available to reorder right now.');
        redirect('features/order-management/my_orders.php');
    }

    $note = $skipped > 0 ? " ($skipped item(s) unavailable were skipped.)" : '';
    flash('success', "Items from order #$orderId added to your cart.$note");
    redirect('features/order-management/cart.php');
}

flash('error', 'Unknown action.');
redirect('features/order-management/my_orders.php');
