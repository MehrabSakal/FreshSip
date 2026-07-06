<?php
/**
 * Itemised receipt (Epic 002 - Billing Engine).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$orderId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Receipt not found.');
    redirect('index.php');
}

// Only the owner, or staff/admin, may view a receipt.
$user = current_user();
if ($order['user_id'] != $user['id'] && !has_role('Admin', 'Staff')) {
    http_response_code(403);
    die('Not allowed to view this receipt.');
}

$items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$orderId]);
$items = $items->fetchAll();

$pageTitle = 'Receipt #' . $orderId;
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4" id="receipt">
                <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-cup-straw text-success"></i> FreshSip Beverages</h4>
                        <small class="text-muted">Juice Bar Management System</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">Receipt #<?= (int) $order['id'] ?></div>
                        <small class="text-muted"><?= e($order['order_date']) ?></small>
                    </div>
                </div>

                <div class="row mb-3 small">
                    <div class="col">
                        <div class="text-muted">Billed to</div>
                        <div><?= e($order['customer_name']) ?></div>
                        <div class="text-muted"><?= e($order['customer_email']) ?></div>
                    </div>
                    <div class="col text-end">
                        <div class="text-muted">Payment</div>
                        <div><?= e($order['payment_method']) ?></div>
                        <span class="badge bg-<?= $order['status'] === 'Served' ? 'success' : 'warning text-dark' ?>">
                            <?= e($order['status']) ?>
                        </span>
                    </div>
                </div>

                <table class="table table-sm">
                    <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Unit</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <?= e($it['product_name']) ?>
                                <?php if ($it['customizations']): ?>
                                    <div class="small text-muted"><?= e($it['customizations']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int) $it['quantity'] ?></td>
                            <td class="text-end"><?= money($it['unit_price']) ?></td>
                            <td class="text-end"><?= money($it['unit_price'] * $it['quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="d-flex justify-content-end">
                    <table class="table table-sm w-auto">
                        <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= money($order['subtotal']) ?></td></tr>
                        <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td class="text-muted">Loyalty discount (<?= (int) $order['points_redeemed'] ?> pts)</td>
                                <td class="text-end text-danger">-<?= money($order['discount']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="fs-5"><th>Total</th><th class="text-end"><?= money($order['total_amount']) ?></th></tr>
                        <tr><td class="text-muted">Points earned</td><td class="text-end text-success">+<?= (int) $order['points_earned'] ?></td></tr>
                    </table>
                </div>

                <p class="text-center text-muted small mb-0 mt-2">Thank you for choosing FreshSip! 🥤</p>
            </div>
        </div>

        <div class="text-center mt-3 d-print-none">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            <?php if (has_role('Customer')): ?>
                <a class="btn btn-jms" href="<?= e(url('index.php')) ?>">Order again</a>
            <?php elseif (has_role('Admin')): ?>
                <a class="btn btn-jms" href="<?= e(url('admin/dashboard.php')) ?>">Back to dashboard</a>
            <?php else: ?>
                <a class="btn btn-jms" href="<?= e(url('features/kitchen-display/index.php')) ?>">Back to kitchen</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
