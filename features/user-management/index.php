<?php
/**
 * Admin user directory (Epic 004 - US014).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT u.id, u.name, u.email, u.role, u.loyalty_points, u.created_at,
               COUNT(o.id) AS order_count,
               COALESCE(SUM(CASE WHEN o.status <> "Cancelled" THEN o.total_amount ELSE 0 END), 0) AS spend
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term];
}
$sql .= ' GROUP BY u.id, u.name, u.email, u.role, u.loyalty_points, u.created_at
          ORDER BY FIELD(u.role, "Admin", "Staff", "Customer"), u.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0"><i class="bi bi-people"></i> User Management</h3>
    <a class="btn btn-jms" href="<?= e(url('features/user-management/create_user.php')) ?>">
        <i class="bi bi-person-plus"></i> Create account
    </a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-sm-8 col-md-5">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by name or email">
    </div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
    <?php if ($search !== ''): ?>
        <div class="col-auto"><a class="btn btn-link" href="<?= e(url('features/user-management/index.php')) ?>">Clear</a></div>
    <?php endif; ?>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>User</th><th>Role</th><th>Points</th><th>Orders</th>
                    <th>Spend</th><th>Joined</th><th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td>
                        <strong><?= e($account['name']) ?></strong>
                        <?php if ((int) $account['id'] === (int) current_user()['id']): ?>
                            <span class="badge bg-light text-dark">You</span>
                        <?php endif; ?>
                        <div class="small text-muted"><?= e($account['email']) ?></div>
                    </td>
                    <td><span class="badge bg-<?= $account['role'] === 'Admin' ? 'danger' : ($account['role'] === 'Staff' ? 'primary' : 'success') ?>"><?= e($account['role']) ?></span></td>
                    <td><?= (int) $account['loyalty_points'] ?></td>
                    <td><?= (int) $account['order_count'] ?></td>
                    <td><?= money($account['spend']) ?></td>
                    <td class="small"><?= e(date('M j, Y', strtotime($account['created_at']))) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('features/user-management/edit_user.php?id=' . $account['id'])) ?>">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
