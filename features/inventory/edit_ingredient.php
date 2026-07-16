<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM inventory WHERE id = ?');
$stmt->execute([$id]);
$ing = $stmt->fetch();

if (!$ing) {
    flash('error', 'Ingredient not found.');
    redirect('features/inventory/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name      = trim($_POST['ingredient_name'] ?? '');
    $unit      = trim($_POST['unit'] ?? 'units');
    $stock     = (float) ($_POST['stock_level'] ?? 0);
    $threshold = (float) ($_POST['alert_threshold'] ?? 0);

    if ($name === '') {
        flash('error', 'Ingredient name is required.');
        redirect('features/inventory/edit_ingredient.php?id=' . $id);
    }
    $pdo->prepare(
        'UPDATE inventory SET ingredient_name=?, unit=?, stock_level=?, alert_threshold=? WHERE id=?'
    )->execute([$name, $unit, $stock, $threshold, $id]);
    flash('success', 'Ingredient updated.');
    redirect('features/inventory/index.php');
}

$pageTitle = 'Edit ingredient';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h3 class="mb-3"><i class="bi bi-pencil-square"></i> Edit ingredient</h3>
    <div class="card shadow-sm"><div class="card-body">
        <form method="post" action="<?= e(url('features/inventory/edit_ingredient.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ing['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Ingredient name</label>
                <input type="text" name="ingredient_name" class="form-control" value="<?= e($ing['ingredient_name']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="<?= e($ing['unit']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock level</label>
                    <input type="number" step="0.01" name="stock_level" class="form-control" value="<?= e($ing['stock_level']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Alert threshold</label>
                    <input type="number" step="0.01" name="alert_threshold" class="form-control" value="<?= e($ing['alert_threshold']) ?>">
                </div>
            </div>
            <button class="btn btn-jms">Update</button>
            <a class="btn btn-link" href="<?= e(url('features/inventory/index.php')) ?>">Cancel</a>
        </form>
    </div></div>
</div></div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
