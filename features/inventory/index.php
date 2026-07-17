<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

// Quick restock action.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'restock') {
        $id  = (int) $_POST['id'];
        $amt = (float) $_POST['amount'];
        $pdo->prepare('UPDATE inventory SET stock_level = stock_level + ? WHERE id = ?')
            ->execute([$amt, $id]);
        flash('success', 'Stock updated.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM inventory WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('success', 'Ingredient removed.');
    }
    redirect('features/inventory/index.php');
}

$items = $pdo->query('SELECT * FROM inventory ORDER BY ingredient_name')->fetchAll();

$lowCount = 0;
foreach ($items as $i) {
    if ($i['stock_level'] <= $i['alert_threshold']) $lowCount++;
}

$pageTitle = 'Inventory';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-box-seam"></i> Inventory</h3>
    <a class="btn btn-jms" href="<?= e(url('features/inventory/add_ingredient.php')) ?>">
        <i class="bi bi-plus-lg"></i> Add ingredient
    </a>
</div>

<?php if ($lowCount > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong><?= $lowCount ?></strong> ingredient(s) at or below their alert threshold. Restock soon!
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Ingredient</th><th>Stock</th><th>Alert @</th><th>Status</th><th style="width:280px">Restock</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i):
                $out = $i['stock_level'] <= 0;
                $low = !$out && $i['stock_level'] <= $i['alert_threshold'];
                $rowClass = $out ? 'out-stock' : ($low ? 'low-stock' : '');
            ?>
                <tr class="<?= $rowClass ?>">
                    <td><strong><?= e($i['ingredient_name']) ?></strong></td>
                    <td><?= rtrim(rtrim(number_format($i['stock_level'], 2), '0'), '.') ?> <span class="text-muted small"><?= e($i['unit']) ?></span></td>
                    <td class="text-muted"><?= rtrim(rtrim(number_format($i['alert_threshold'], 2), '0'), '.') ?></td>
                    <td>
                        <?php if ($out): ?><span class="badge bg-danger">Out of stock</span>
                        <?php elseif ($low): ?><span class="badge bg-warning text-dark">Low</span>
                        <?php else: ?><span class="badge bg-success">OK</span><?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= e(url('features/inventory/index.php')) ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="restock">
                            <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                            <input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="+ amount" required>
                            <button class="btn btn-sm btn-outline-success">Add</button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('features/inventory/edit_ingredient.php?id=' . $i['id'])) ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="<?= e(url('features/inventory/index.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= e($i['ingredient_name']) ?>?"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
