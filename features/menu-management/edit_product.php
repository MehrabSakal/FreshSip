<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('features/menu-management/index.php');
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$icons = ['cup-straw', 'cup', 'cup-hot', 'flower1', 'lightning-charge', 'droplet', 'egg-fried'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float) ($_POST['price'] ?? 0);
    $category_id = $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $icon        = $_POST['icon'] ?? 'cup-straw';
    $available   = isset($_POST['is_available']) ? 1 : 0;

    if ($name === '' || $price <= 0) {
        flash('error', 'Name and a positive price are required.');
        redirect('features/menu-management/edit_product.php?id=' . $id);
    }

    $pdo->prepare(
        'UPDATE products SET name=?, description=?, price=?, category_id=?, icon=?, is_available=?
         WHERE id=?'
    )->execute([$name, $description, $price, $category_id, $icon, $available, $id]);

    flash('success', 'Product updated.');
    redirect('features/menu-management/index.php');
}

$pageTitle = 'Edit product';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-7">
        <h3 class="mb-3"><i class="bi bi-pencil-square"></i> Edit product</h3>
        <div class="card shadow-sm"><div class="card-body">
            <form method="post" action="<?= e(url('features/menu-management/edit_product.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" maxlength="255" value="<?= e($product['description']) ?>">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Price ($)</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e($product['price']) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">— none —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Icon</label>
                        <select name="icon" class="form-select">
                            <?php foreach ($icons as $ic): ?>
                                <option value="<?= e($ic) ?>" <?= $ic === $product['icon'] ? 'selected' : '' ?>><?= e($ic) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_available" id="avail" <?= $product['is_available'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="avail">Available on the menu</label>
                </div>
                <button class="btn btn-jms">Update product</button>
                <a class="btn btn-link" href="<?= e(url('features/menu-management/index.php')) ?>">Cancel</a>
            </form>
        </div></div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
