<?php
/**
 * Product recipe editor (Epic 003 - US009 support).
 * Defines which ingredients (and how much) a product consumes, so stock
 * is deducted automatically when the product is ordered.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$pstmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$pstmt->execute([$productId]);
$product = $pstmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('features/menu-management/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $ingredientId = (int) $_POST['ingredient_id'];
        $qty          = (float) $_POST['quantity_used'];
        if ($ingredientId && $qty > 0) {
            // INSERT ... ON DUPLICATE updates the quantity if it already exists.
            $pdo->prepare(
                'INSERT INTO product_ingredients (product_id, ingredient_id, quantity_used)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE quantity_used = VALUES(quantity_used)'
            )->execute([$productId, $ingredientId, $qty]);
            flash('success', 'Recipe updated.');
        }
    } elseif ($action === 'remove') {
        $pdo->prepare('DELETE FROM product_ingredients WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('success', 'Ingredient removed from recipe.');
    }
    redirect('features/inventory/product_recipe.php?product_id=' . $productId);
}

$recipe = $pdo->prepare(
    'SELECT pi.*, i.ingredient_name, i.unit
     FROM product_ingredients pi
     JOIN inventory i ON i.id = pi.ingredient_id
     WHERE pi.product_id = ?
     ORDER BY i.ingredient_name'
);
$recipe->execute([$productId]);
$recipe = $recipe->fetchAll();

$ingredients = $pdo->query('SELECT * FROM inventory ORDER BY ingredient_name')->fetchAll();

$pageTitle = 'Recipe: ' . $product['name'];
require_once APP_ROOT . '/includes/header.php';
?>
<h3 class="mb-1"><i class="bi bi-list-check"></i> Recipe</h3>
<p class="text-muted">Ingredients consumed per order of <strong><?= e($product['name']) ?></strong>.</p>

<div class="row">
    <div class="col-md-7 mb-3">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light"><tr><th>Ingredient</th><th>Qty used</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$recipe): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">No ingredients defined — stock won't be deducted.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recipe as $r): ?>
                        <tr>
                            <td><?= e($r['ingredient_name']) ?></td>
                            <td><?= rtrim(rtrim(number_format($r['quantity_used'], 2), '0'), '.') ?> <?= e($r['unit']) ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('features/inventory/product_recipe.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm"><div class="card-body">
            <h6>Add / update ingredient</h6>
            <form method="post" action="<?= e(url('features/inventory/product_recipe.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                <input type="hidden" name="action" value="add">
                <div class="mb-2">
                    <label class="form-label">Ingredient</label>
                    <select name="ingredient_id" class="form-select" required>
                        <option value="">— choose —</option>
                        <?php foreach ($ingredients as $i): ?>
                            <option value="<?= (int) $i['id'] ?>"><?= e($i['ingredient_name']) ?> (<?= e($i['unit']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity used per order</label>
                    <input type="number" step="0.01" min="0.01" name="quantity_used" class="form-control" required>
                </div>
                <button class="btn btn-jms w-100">Save to recipe</button>
            </form>
        </div></div>
    </div>
</div>
<a class="btn btn-link mt-2" href="<?= e(url('features/menu-management/index.php')) ?>">&laquo; Back to menu management</a>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
