<?php
/**
 * Live status fragment for the customer "My Orders" page.
 * Renders a progress tracker for the current user's ACTIVE orders
 * (Pending / Preparing). Loaded once server-side, then refreshed via AJAX
 * so customers can watch their order advance without reloading.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$user = current_user();

$stmt = $pdo->prepare(
    "SELECT id, order_date, total_amount, status
     FROM orders
     WHERE user_id = ? AND status IN ('Pending','Preparing')
     ORDER BY order_date DESC"
);
$stmt->execute([$user['id']]);
$active = $stmt->fetchAll();

$itemStmt = $pdo->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');
$steps    = order_status_steps();

if (!$active) {
    echo '<div class="card shadow-sm"><div class="card-body text-center text-muted py-5">'
       . '<i class="bi bi-cup-straw display-5"></i>'
       . '<p class="mt-2 mb-0">No orders in progress right now.</p></div></div>';
    return;
}
?>
<div class="row g-3">
<?php foreach ($active as $o):
    $itemStmt->execute([$o['id']]);
    $items       = $itemStmt->fetchAll();
    $currentStep = array_search($o['status'], $steps, true);
?>
    <div class="col-md-6">
        <div class="card shadow-sm h-100 ticket status-<?= e($o['status']) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>Order #<?= (int) $o['id'] ?></strong>
                        <div class="small text-muted"><?= e(date('M j, Y g:i A', strtotime($o['order_date']))) ?></div>
                    </div>
                    <span class="badge bg-<?= order_status_badge($o['status']) ?>"><?= e($o['status']) ?></span>
                </div>

                <div class="order-tracker d-flex justify-content-between mb-3">
                    <?php foreach ($steps as $i => $step):
                        $state = $i < $currentStep ? 'done' : ($i === $currentStep ? 'active' : 'todo');
                        $icon  = ['Pending' => 'hourglass-split', 'Preparing' => 'fire', 'Served' => 'check2-circle'][$step];
                    ?>
                        <div class="tracker-step tracker-<?= $state ?> text-center">
                            <div class="tracker-dot"><i class="bi bi-<?= $icon ?>"></i></div>
                            <div class="tracker-label small"><?= e($step) ?></div>
                        </div>
                        <?php if ($i < count($steps) - 1): ?>
                            <div class="tracker-bar <?= $i < $currentStep ? 'tracker-done' : '' ?>"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="small text-muted">
                    <?php
                    $names = array_map(fn($it) => (int) $it['quantity'] . '× ' . $it['product_name'], $items);
                    echo e(implode(', ', $names));
                    ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="fw-bold"><?= money($o['total_amount']) ?></span>
                    <span class="small text-muted">
                        <i class="bi bi-info-circle"></i>
                        <?= $o['status'] === 'Pending' ? 'Waiting for the bar to start' : 'Being prepared now' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
