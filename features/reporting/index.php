<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$revenueWhere = "status <> 'Cancelled'";

$today = $pdo->query(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount),0) AS revenue
     FROM orders WHERE $revenueWhere AND DATE(order_date) = CURDATE()"
)->fetch();

$month = $pdo->query(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount),0) AS revenue
     FROM orders WHERE $revenueWhere
       AND YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())"
)->fetch();

$allTime = $pdo->query(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount),0) AS revenue
     FROM orders WHERE $revenueWhere"
)->fetch();

$daily = $pdo->query(
    "SELECT DATE(order_date) AS d, COUNT(*) AS orders, SUM(total_amount) AS revenue
     FROM orders WHERE $revenueWhere
     GROUP BY DATE(order_date)
     ORDER BY d DESC LIMIT 14"
)->fetchAll();

$monthly = $pdo->query(
    "SELECT DATE_FORMAT(order_date, '%Y-%m') AS m, COUNT(*) AS orders, SUM(total_amount) AS revenue
     FROM orders WHERE $revenueWhere
     GROUP BY DATE_FORMAT(order_date, '%Y-%m')
     ORDER BY m DESC LIMIT 12"
)->fetchAll();

$topItems = $pdo->query(
    "SELECT oi.product_name,
            SUM(oi.quantity) AS qty,
            SUM(oi.quantity * oi.unit_price) AS revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'Cancelled'
     GROUP BY oi.product_name
     ORDER BY qty DESC LIMIT 10"
)->fetchAll();

$maxDaily = 0;
foreach ($daily as $d) { $maxDaily = max($maxDaily, (float) $d['revenue']); }

$pageTitle = 'Sales Reports';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Sales Reports</h3>
    <div>
        <a class="btn btn-outline-success" href="<?= e(url('features/reporting/export_csv.php?type=daily')) ?>"><i class="bi bi-download"></i> Daily CSV</a>
        <a class="btn btn-outline-success" href="<?= e(url('features/reporting/export_csv.php?type=monthly')) ?>"><i class="bi bi-download"></i> Monthly CSV</a>
        <a class="btn btn-outline-success" href="<?= e(url('features/reporting/export_csv.php?type=top')) ?>"><i class="bi bi-download"></i> Top items CSV</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card stat-card"><div class="card-body">
        <div class="small text-muted text-uppercase">Today</div>
        <div class="h3 mb-0"><?= money($today['revenue']) ?></div>
        <div class="small text-muted"><?= (int) $today['orders'] ?> orders</div>
    </div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body">
        <div class="small text-muted text-uppercase">This month</div>
        <div class="h3 mb-0"><?= money($month['revenue']) ?></div>
        <div class="small text-muted"><?= (int) $month['orders'] ?> orders</div>
    </div></div></div>
    <div class="col-md-4"><div class="card stat-card"><div class="card-body">
        <div class="small text-muted text-uppercase">All time</div>
        <div class="h3 mb-0"><?= money($allTime['revenue']) ?></div>
        <div class="small text-muted"><?= (int) $allTime['orders'] ?> orders</div>
    </div></div></div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <h5>Daily revenue (last 14 days)</h5>
            <?php if (!$daily): ?>
                <p class="text-muted">No sales data yet.</p>
            <?php else: ?>
                <?php foreach ($daily as $d):
                    $pct = $maxDaily > 0 ? round($d['revenue'] / $maxDaily * 100) : 0; ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><?= e($d['d']) ?> <span class="text-muted">(<?= (int) $d['orders'] ?>)</span></span>
                            <span><?= money($d['revenue']) ?></span>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <h5>Top selling items</h5>
            <table class="table table-sm align-middle">
                <thead><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                <?php if (!$topItems): ?>
                    <tr><td colspan="4" class="text-muted text-center">No data.</td></tr>
                <?php endif; ?>
                <?php foreach ($topItems as $n => $t): ?>
                    <tr>
                        <td><?= $n + 1 ?></td>
                        <td><?= e($t['product_name']) ?></td>
                        <td class="text-end"><?= (int) $t['qty'] ?></td>
                        <td class="text-end"><?= money($t['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Monthly revenue</strong></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Orders</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
            <?php if (!$monthly): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">No data.</td></tr>
            <?php endif; ?>
            <?php foreach ($monthly as $m): ?>
                <tr><td><?= e($m['m']) ?></td><td><?= (int) $m['orders'] ?></td><td class="text-end"><?= money($m['revenue']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
