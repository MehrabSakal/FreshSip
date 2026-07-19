<?php
/**
 * Menu Management (Epic 001) - product listing for admins.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$products = $pdo->query(
    'SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$pageTitle = 'Menu Management';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-card-list"></i> Menu Management</h3>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(url('features/menu-management/categories.php')) ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a class="btn btn-jms" href="<?= e(url('features/menu-management/add_product.php')) ?>">
            <i class="bi bi-plus-lg"></i> Add product
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Name</th><th>Category</th><th>Price</th>
                    <th>Visibility</th><th>Stock</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No products yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= (int) $p['id'] ?></td>
                    <td>
                        <i class="bi bi-<?= e($p['icon']) ?> text-success"></i>
                        <strong><?= e($p['name']) ?></strong>
                        <div class="small text-muted"><?= e($p['description']) ?></div>
                    </td>
                    <td><?= e($p['category_name'] ?? '—') ?></td>
                    <td><?= money($p['price']) ?></td>
                    <td>
                        <?php if ($p['is_available']): ?>
                            <span class="badge bg-success">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (product_has_stock($pdo, (int) $p['id'])): ?>
                            <span class="badge bg-success">In stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Sold out</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('features/menu-management/edit_product.php?id=' . $p['id'])) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-info" href="<?= e(url('features/inventory/product_recipe.php?product_id=' . $p['id'])) ?>" title="Recipe">
                            <i class="bi bi-list-check"></i>
                        </a>
                        <form method="post" action="<?= e(url('features/menu-management/delete_product.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= e($p['name']) ?>?">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
