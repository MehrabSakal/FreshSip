<?php
/**
 * process_payment.php  (the doc's "process_order.php")
 *
 * Core order transaction (Epic 001/002/003). In a single DB transaction it:
 *   1. Inserts the order + order_items
 *   2. Deducts ingredient stock via the product recipe (product_ingredients)
 *   3. Applies loyalty redemption and awards new points
 * If anything fails (e.g. not enough stock) the whole thing is rolled back.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('features/order-management/cart.php');
}
csrf_check();

$cart = cart();
if (!$cart) {
    flash('info', 'Your cart is empty.');
    redirect('features/order-management/cart.php');
}

$user           = current_user();
$paymentMethod  = in_array($_POST['payment_method'] ?? '', ['Cash', 'Card', 'UPI'], true)
                    ? $_POST['payment_method'] : 'Cash';
$redeemPoints   = max(0, (int) ($_POST['redeem_points'] ?? 0));

$subtotal = cart_total();

try {
    $pdo->beginTransaction();

    // --- Lock the user row and validate the redemption ---------------
    $stmt = $pdo->prepare('SELECT loyalty_points FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$user['id']]);
    $currentPoints = (int) $stmt->fetchColumn();

    $redeemPoints = min($redeemPoints, $currentPoints);
    $discount     = $redeemPoints / POINTS_PER_DOLLAR_REDEEM;      // 10 pts => $1
    $discount     = min($discount, $subtotal);                    // never below 0
    // Recompute the actual points consumed after clamping to subtotal.
    $redeemPoints = (int) round($discount * POINTS_PER_DOLLAR_REDEEM);

    $total        = round($subtotal - $discount, 2);
    $pointsEarned = (int) floor($total * POINTS_PER_DOLLAR);       // $1 => 1 pt

    // --- Check ingredient availability across the whole cart ---------
    $needed = []; // ingredient_id => qty required
    $recipeStmt = $pdo->prepare(
        'SELECT ingredient_id, quantity_used FROM product_ingredients WHERE product_id = ?'
    );
    foreach ($cart as $item) {
        $recipeStmt->execute([$item['product_id']]);
        foreach ($recipeStmt->fetchAll() as $r) {
            $needed[$r['ingredient_id']] =
                ($needed[$r['ingredient_id']] ?? 0) + $r['quantity_used'] * $item['quantity'];
        }
    }

    foreach ($needed as $ingredientId => $qty) {
        $chk = $pdo->prepare('SELECT ingredient_name, stock_level FROM inventory WHERE id = ? FOR UPDATE');
        $chk->execute([$ingredientId]);
        $ing = $chk->fetch();
        if (!$ing || $ing['stock_level'] < $qty) {
            throw new RuntimeException(
                'Out of stock: ' . ($ing['ingredient_name'] ?? 'an ingredient')
                . '. Please adjust your order.'
            );
        }
    }

    // --- Insert the order -------------------------------------------
    $pdo->prepare(
        'INSERT INTO orders
            (user_id, subtotal, discount, total_amount, points_earned, points_redeemed, payment_method, status)
         VALUES (?,?,?,?,?,?,?, "Pending")'
    )->execute([$user['id'], $subtotal, $discount, $total, $pointsEarned, $redeemPoints, $paymentMethod]);
    $orderId = (int) $pdo->lastInsertId();

    // --- Insert order items -----------------------------------------
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, unit_price, quantity, customizations)
         VALUES (?,?,?,?,?,?)'
    );
    foreach ($cart as $item) {
        $itemStmt->execute([
            $orderId, $item['product_id'], $item['name'],
            $item['price'], $item['quantity'], $item['customizations'] ?: null,
        ]);
    }

    // --- Deduct ingredient stock ------------------------------------
    $deduct = $pdo->prepare('UPDATE inventory SET stock_level = stock_level - ? WHERE id = ?');
    foreach ($needed as $ingredientId => $qty) {
        $deduct->execute([$qty, $ingredientId]);
    }

    // --- Update loyalty points --------------------------------------
    $newPoints = $currentPoints - $redeemPoints + $pointsEarned;
    $pdo->prepare('UPDATE users SET loyalty_points = ? WHERE id = ?')
        ->execute([$newPoints, $user['id']]);

    $pdo->commit();

    // Sync session + clear cart.
    $_SESSION['user']['loyalty_points'] = $newPoints;
    cart_clear();

    flash('success', 'Order #' . $orderId . ' placed! You earned ' . $pointsEarned . ' points.');
    redirect('features/billing/receipt.php?id=' . $orderId);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Order failed: ' . $e->getMessage());
    redirect('features/billing/checkout.php');
}
