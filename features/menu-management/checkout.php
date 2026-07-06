<?php
/**
 * Checkout (Epic 002) - payment method + loyalty redemption.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$cart = cart();
if (!$cart) {
    flash('info', 'Your cart is empty.');
    redirect('features/order-management/cart.php');
}

$user     = current_user();
$subtotal = cart_total();

// Refresh live loyalty balance from DB.
$stmt = $pdo->prepare('SELECT loyalty_points FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$points = (int) $stmt->fetchColumn();

// Max points redeemable = whichever is smaller: what they own, or enough
// to cover the whole subtotal.
$maxByBill    = (int) floor($subtotal * POINTS_PER_DOLLAR_REDEEM);
$maxRedeemable = min($points, $maxByBill);

$pageTitle = 'Checkout';
require_once APP_ROOT . '/includes/header.php';
?>
<h3 class="mb-3"><i class="bi bi-credit-card"></i> Checkout</h3>
<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card shadow-sm"><div class="card-body">
            <h5>Order summary</h5>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($cart as $item): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>
                            <?= (int) $item['quantity'] ?> &times; <?= e($item['name']) ?>
                            <?php if ($item['customizations']): ?>
                                <small class="text-muted">(<?= e($item['customizations']) ?>)</small>
                            <?php endif; ?>
                        </span>
                        <span><?= money($item['price'] * $item['quantity']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex justify-content-between fs-5">
                <strong>Subtotal</strong><strong><?= money($subtotal) ?></strong>
            </div>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm"><div class="card-body">
            <form method="post" action="<?= e(url('features/billing/process_payment.php')) ?>">
                <?= csrf_field() ?>

                <h5>Payment method</h5>
                <div class="mb-3">
                    <?php foreach (['Cash' => 'cash-coin', 'Card' => 'credit-card-2-front', 'UPI' => 'phone'] as $m => $ic): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method"
                                   id="pm<?= $m ?>" value="<?= $m ?>" <?= $m === 'Cash' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="pm<?= $m ?>">
                                <i class="bi bi-<?= $ic ?>"></i> <?= $m ?>
                                <?php if ($m !== 'Cash'): ?><small class="text-muted">(placeholder)</small><?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h5>Loyalty points</h5>
                <p class="small text-muted mb-1">
                    You have <strong><?= $points ?></strong> points.
                    <?= POINTS_PER_DOLLAR_REDEEM ?> points = <?= money(1) ?> off.
                </p>
                <?php if ($maxRedeemable > 0): ?>
                    <div class="mb-3">
                        <label class="form-label">Redeem points (0 – <?= $maxRedeemable ?>)</label>
                        <input type="number" name="redeem_points" class="form-control"
                               min="0" max="<?= $maxRedeemable ?>" step="<?= POINTS_PER_DOLLAR_REDEEM ?>" value="0">
                    </div>
                <?php else: ?>
                    <p class="small text-muted">No points available to redeem yet.</p>
                    <input type="hidden" name="redeem_points" value="0">
                <?php endif; ?>

                <button class="btn btn-jms btn-lg w-100">
                    <i class="bi bi-bag-check"></i> Place order &amp; pay
                </button>
            </form>
        </div></div>
        <a class="btn btn-link mt-2" href="<?= e(url('features/order-management/cart.php')) ?>">&laquo; Back to cart</a>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
