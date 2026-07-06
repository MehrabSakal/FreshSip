<?php
/**
 * Customer order centre.
 * Shows live status of in-progress orders (auto-refreshing) plus the full
 * order history with per-order actions: view receipt, reorder, cancel.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$user = current_user();

$orders = $pdo->prepare(
    'SELECT id, order_date, subtotal, discount, total_amount, points_earned,
            points_redeemed, payment_method, status
     FROM orders WHERE user_id = ? ORDER BY order_date DESC'
);
$orders->execute([$user['id']]);
$orders = $orders->fetchAll();

// Pull item summaries for every order in one query.
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $pdo->prepare(
        "SELECT order_id, product_name, quantity, customizations
         FROM order_items WHERE order_id IN ($ph)"
    );
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() as $it) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}

$stats = [
    'total'  => count($orders),
    'active' => count(array_filter($orders, fn($o) => in_array($o['status'], ['Pending', 'Preparing'], true))),
    'spent'  => array_sum(array_map(
        fn($o) => $o['status'] === 'Cancelled' ? 0 : (float) $o['total_amount'],
        $orders
    )),
];

$pageTitle = 'My Orders';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-bag-check text-success"></i> My Orders</h3>
    <a class="btn btn-jms" href="<?= e(url('index.php')) ?>"><i class="bi bi-plus-lg"></i> New order</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card stat-card"><div class="card-body">
            <div class="small text-muted text-uppercase">Total orders</div>
            <div class="display-6 fw-bold"><?= (int) $stats['total'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card stat-card"><div class="card-body">
            <div class="small text-muted text-uppercase">In progress</div>
            <div class="display-6 fw-bold text-primary"><?= (int) $stats['active'] ?></div>
        </div></div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card stat-card text-white" style="background:var(--jms-green)"><div class="card-body">
            <div class="small text-uppercase">Total spent</div>
            <div class="display-6 fw-bold"><?= money($stats['spent']) ?></div>
        </div></div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> In progress</h5>
    <span class="text-muted small"><i class="bi bi-arrow-repeat"></i> Live &middot; auto-refreshes</span>
</div>
<div id="myOrdersLive" class="mb-4" data-endpoint="<?= e(url('features/order-management/orders_status.php')) ?>">
    <?php include __DIR__ . '/orders_status.php'; ?>
</div>

<h5 class="mb-2"><i class="bi bi-clock-history"></i> Order history</h5>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Order</th><th>Date</th><th>Items</th><th>Total</th>
                    <th>Payment</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$orders): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">
                    You have not placed any orders yet.
                    <a href="<?= e(url('index.php')) ?>">Browse the menu</a>.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($orders as $o):
                $items = $itemsByOrder[$o['id']] ?? [];
            ?>
                <tr>
                    <td class="fw-semibold">#<?= (int) $o['id'] ?></td>
                    <td class="small"><?= e(date('M j, Y g:i A', strtotime($o['order_date']))) ?></td>
                    <td class="small" style="max-width:260px">
                        <?php
                        $names = array_map(fn($it) => (int) $it['quantity'] . '× ' . $it['product_name'], $items);
                        echo e(implode(', ', $names));
                        ?>
                    </td>
                    <td><?= money($o['total_amount']) ?></td>
                    <td class="small"><?= e($o['payment_method']) ?></td>
                    <td><span class="badge bg-<?= order_status_badge($o['status']) ?>"><?= e($o['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('features/billing/receipt.php?id=' . $o['id'])) ?>">
                            <i class="bi bi-receipt"></i>
                        </a>
                        <form method="post" action="<?= e(url('features/order-management/order_action.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reorder">
                            <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                            <button class="btn btn-sm btn-outline-success" title="Add these items to your cart">
                                <i class="bi bi-arrow-repeat"></i> Reorder
                            </button>
                        </form>
                        <?php if ($o['status'] === 'Pending'): ?>
                            <form method="post" action="<?= e(url('features/order-management/order_action.php')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                        data-confirm="Cancel order #<?= (int) $o['id'] ?>? Any redeemed points will be refunded.">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
