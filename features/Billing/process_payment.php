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

$cart = cart();
$intent = $_SESSION['payment_intent'] ?? null;
$intentToken = $_GET['intent'] ?? '';
$usingConfirmedIntent = $_SERVER['REQUEST_METHOD'] === 'GET'
    && $intent
    && $intentToken !== ''
    && hash_equals($intent['token'] ?? '', $intentToken)
    && !empty($intent['confirmed'])
    && (int) ($intent['user_id'] ?? 0) === (int) current_user()['id']
    && hash_equals($intent['cart_hash'] ?? '', hash('sha256', serialize($cart)));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$usingConfirmedIntent) {
    redirect('features/order-management/cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

if (!$cart) {
    flash('info', 'Your cart is empty.');
    redirect('features/order-management/cart.php');
}

$user           = current_user();
$requestedPaymentMethod = $_POST['payment_method'] ?? '';
if (!$usingConfirmedIntent && !in_array($requestedPaymentMethod, ['Cash', 'Card', 'UPI'], true)) {
    flash('error', 'Select a valid payment method.');
    redirect('features/billing/checkout.php');
}
$paymentMethod  = $usingConfirmedIntent
                    ? $intent['payment_method']
                    : $requestedPaymentMethod;
$redeemPoints   = $usingConfirmedIntent
                    ? max(0, (int) $intent['redeem_points'])
                    : max(0, (int) ($_POST['redeem_points'] ?? 0));
$paymentStatus  = $usingConfirmedIntent ? 'Paid' : 'Pending';
$paymentReference = $usingConfirmedIntent ? $intent['reference'] : null;

// Digital payments require a separate confirmation before any order is made.
if (!$usingConfirmedIntent && in_array($paymentMethod, ['Card', 'UPI'], true)) {
    $token = bin2hex(random_bytes(24));
    $_SESSION['payment_intent'] = [
        'token' => $token,
        'user_id' => (int) $user['id'],
        'cart_hash' => hash('sha256', serialize($cart)),
        'payment_method' => $paymentMethod,
        'redeem_points' => $redeemPoints,
        'confirmed' => false,
        'reference' => null,
    ];
    redirect('features/billing/payment_confirm.php?intent=' . urlencode($token));
}

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

    // Build one immutable requirement plan for validation, snapshots and deduction.
    $itemRequirementPlan = [];
    $needed = [];
    foreach ($cart as $key => $item) {
        $itemRequirementPlan[$key] = product_ingredient_requirements(
            $pdo,
            (int) $item['product_id'],
            $item['customizations'] ?? ''
        );
        foreach ($itemRequirementPlan[$key] as $ingredientId => $requirement) {
            if (!isset($needed[$ingredientId])) {
                $needed[$ingredientId] = ['name' => $requirement['name'], 'quantity' => 0.0];
            }
            $needed[$ingredientId]['quantity'] +=
                $requirement['quantity'] * (int) $item['quantity'];
        }
    }

    // Lock ingredients in a stable order to reduce deadlock risk.
    ksort($needed);
    $stockStmt = $pdo->prepare(
        'SELECT ingredient_name, stock_level FROM inventory WHERE id = ? FOR UPDATE'
    );
    foreach ($needed as $ingredientId => $requirement) {
        $stockStmt->execute([$ingredientId]);
        $ingredient = $stockStmt->fetch();
        if (!$ingredient || (float) $ingredient['stock_level'] < $requirement['quantity']) {
            throw new RuntimeException(
                'Out of stock: ' . ($ingredient['ingredient_name'] ?? $requirement['name'])
                . '. Please adjust your order.'
            );
        }
    }

    // --- Insert the order -------------------------------------------
    $pdo->prepare(
        'INSERT INTO orders
            (user_id, subtotal, discount, total_amount, points_earned, points_redeemed,
             payment_method, payment_status, payment_reference, status)
         VALUES (?,?,?,?,?,?,?,?,?, "Pending")'
    )->execute([
        $user['id'], $subtotal, $discount, $total, $pointsEarned, $redeemPoints,
        $paymentMethod, $paymentStatus, $paymentReference,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    // --- Insert order items -----------------------------------------
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, unit_price, quantity, customizations)
         VALUES (?,?,?,?,?,?)'
    );
    $ingredientSnapshotStmt = $pdo->prepare(
        'INSERT INTO order_item_ingredients
            (order_item_id, ingredient_id, ingredient_name, quantity_used)
         VALUES (?,?,?,?)'
    );
    foreach ($cart as $key => $item) {
        $itemStmt->execute([
            $orderId, $item['product_id'], $item['name'],
            $item['price'], $item['quantity'], $item['customizations'] ?: null,
        ]);
        $orderItemId = (int) $pdo->lastInsertId();
        foreach ($itemRequirementPlan[$key] as $ingredientId => $requirement) {
            $ingredientSnapshotStmt->execute([
                $orderItemId,
                $ingredientId,
                $requirement['name'],
                $requirement['quantity'] * (int) $item['quantity'],
            ]);
        }
    }

    // --- Deduct ingredient stock ------------------------------------
    $deduct = $pdo->prepare('UPDATE inventory SET stock_level = stock_level - ? WHERE id = ?');
    foreach ($needed as $ingredientId => $requirement) {
        $deduct->execute([$requirement['quantity'], $ingredientId]);
    }

    // --- Update loyalty points --------------------------------------
    $newPoints = $currentPoints - $redeemPoints + $pointsEarned;
    $pdo->prepare('UPDATE users SET loyalty_points = ? WHERE id = ?')
        ->execute([$newPoints, $user['id']]);

    $pdo->commit();

    // Sync session + clear cart.
    $_SESSION['user']['loyalty_points'] = $newPoints;
    cart_clear();
    unset($_SESSION['payment_intent']);

    flash('success', 'Order #' . $orderId . ' placed! You earned ' . $pointsEarned . ' points.');
    redirect('features/billing/receipt.php?id=' . $orderId);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    unset($_SESSION['payment_intent']);
    flash('error', 'Order failed: ' . $e->getMessage());
    redirect('features/billing/checkout.php');
}
