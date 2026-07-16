<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name      = trim($_POST['ingredient_name'] ?? '');
    $unit      = trim($_POST['unit'] ?? 'units');
    $stock     = (float) ($_POST['stock_level'] ?? 0);
    $threshold = (float) ($_POST['alert_threshold'] ?? 0);

    if ($name === '') {
        flash('error', 'Ingredient name is required.');
        redirect('features/inventory/add_ingredient.php');
    }
    try {
        $pdo->prepare(
            'INSERT INTO inventory (ingredient_name, unit, stock_level, alert_threshold) VALUES (?,?,?,?)'
        )->execute([$name, $unit, $stock, $threshold]);
        flash('success', 'Ingredient added.');
        redirect('features/inventory/index.php');
    } catch (PDOException $e) {
        flash('error', 'That ingredient already exists.');
        redirect('features/inventory/add_ingredient.php');
    }
}

$pageTitle = 'Add ingredient';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h3 class="mb-3"><i class="bi bi-plus-circle"></i> Add ingredient</h3>
    <div class="card shadow-sm"><div class="card-body">
        <form method="post" action="<?= e(url('features/inventory/add_ingredient.php')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Ingredient name</label>
                <input type="text" name="ingredient_name" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="units">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock level</label>
                    <input type="number" step="0.01" name="stock_level" class="form-control" value="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Alert threshold</label>
                    <input type="number" step="0.01" name="alert_threshold" class="form-control" value="0">
                </div>
            </div>
            <button class="btn btn-jms">Save</button>
            <a class="btn btn-link" href="<?= e(url('features/inventory/index.php')) ?>">Cancel</a>
        </form>
    </div></div>
</div></div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
