<?php
/**
 * Create a customer, staff member or administrator (Epic 004 - US014).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$values = [
    'name' => trim($_POST['name'] ?? ''),
    'email' => strtolower(trim($_POST['email'] ?? '')),
    'role' => $_POST['role'] ?? 'Customer',
    'loyalty_points' => max(0, (int) ($_POST['loyalty_points'] ?? 0)),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $password = $_POST['password'] ?? '';
    $roles = ['Admin', 'Staff', 'Customer'];

    if ($values['name'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a name and a valid email address.');
    } elseif (!in_array($values['role'], $roles, true)) {
        flash('error', 'Select a valid role.');
    } elseif (strlen($password) < 8) {
        flash('error', 'Password must contain at least 8 characters.');
    } else {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $exists->execute([$values['email']]);
        if ($exists->fetchColumn()) {
            flash('error', 'That email address is already in use.');
        } else {
            $pdo->prepare(
                'INSERT INTO users (name, email, password, role, loyalty_points) VALUES (?,?,?,?,?)'
            )->execute([
                $values['name'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $values['role'],
                $values['loyalty_points'],
            ]);
            flash('success', $values['name'] . ' was created.');
            redirect('features/user-management/index.php');
        }
    }
}

$pageTitle = 'Create User';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center mb-3">
            <a class="btn btn-link ps-0" href="<?= e(url('features/user-management/index.php')) ?>">&laquo; Users</a>
            <h3 class="mb-0"><i class="bi bi-person-plus"></i> Create account</h3>
        </div>
        <div class="card shadow-sm"><div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" required maxlength="100" value="<?= e($values['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required maxlength="150" value="<?= e($values['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <?php foreach (['Customer', 'Staff', 'Admin'] as $role): ?>
                                <option <?= $values['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Loyalty points</label>
                        <input class="form-control" type="number" min="0" name="loyalty_points"
                               value="<?= (int) $values['loyalty_points'] ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Temporary password</label>
                        <input class="form-control" type="password" name="password" required minlength="8"
                               autocomplete="new-password">
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                </div>
                <button class="btn btn-jms mt-4"><i class="bi bi-check-lg"></i> Create account</button>
            </form>
        </div></div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
