<?php
/**
 * Category management (add / delete) - Epic 001 Menu Management.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            try {
                $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
                flash('success', 'Category added.');
            } catch (PDOException $e) {
                flash('error', 'That category already exists.');
            }
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('success', 'Category removed.');
    }
    redirect('features/menu-management/categories.php');
}

$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Categories';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row">
    <div class="col-md-5 mb-3">
        <h4><i class="bi bi-tag"></i> Add category</h4>
        <div class="card shadow-sm"><div class="card-body">
            <form method="post" action="<?= e(url('features/menu-management/categories.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="input-group">
                    <input type="text" name="name" class="form-control" placeholder="e.g. Cold Pressed" required>
                    <button class="btn btn-jms">Add</button>
                </div>
            </form>
        </div></div>
    </div>
    <div class="col-md-7">
        <h4><i class="bi bi-tags"></i> Categories</h4>
        <div class="card shadow-sm">
            <ul class="list-group list-group-flush">
                <?php if (!$categories): ?>
                    <li class="list-group-item text-muted">No categories yet.</li>
                <?php endif; ?>
                <?php foreach ($categories as $c): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= e($c['name']) ?>
                            <span class="badge bg-light text-dark"><?= (int) $c['product_count'] ?> products</span>
                        </span>
                        <form method="post" action="<?= e(url('features/menu-management/categories.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this category?">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<a class="btn btn-link mt-3" href="<?= e(url('features/menu-management/index.php')) ?>">&laquo; Back to products</a>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
