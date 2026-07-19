<?php
/**
 * Edit an existing account (Epic 004 - US014).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$userId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, name, email, role, loyalty_points, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    flash('error', 'User not found.');
    redirect('features/user-management/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $role = $_POST['role'] ?? '';
    $points = max(0, (int) ($_POST['loyalty_points'] ?? 0));
    $password = $_POST['password'] ?? '';
    $roles = ['Admin', 'Staff', 'Customer'];
    $error = null;

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a name and a valid email address.';
    } elseif (!in_array($role, $roles, true)) {
        $error = 'Select a valid role.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'A new password must contain at least 8 characters.';
    } elseif ((int) $account['id'] === (int) current_user()['id'] && $role !== 'Admin') {
        $error = 'You cannot demote your own active administrator account.';
    }

    if ($error) {
        flash('error', $error);
        $account = array_merge($account, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'loyalty_points' => $points,
        ]);
    } else {
        try {
            $pdo->beginTransaction();

            // Lock all current admins so concurrent demotions cannot remove the last one.
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'Admin' FOR UPDATE")->fetchAll();
            $freshAccountStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE');
            $freshAccountStmt->execute([$userId]);
            $freshRole = $freshAccountStmt->fetchColumn();
            if ($freshRole === 'Admin' && $role !== 'Admin' && count($admins) <= 1) {
                throw new RuntimeException('FreshSip must keep at least one administrator.');
            }

            $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $exists->execute([$email, $userId]);
            if ($exists->fetchColumn()) {
                throw new RuntimeException('That email address is already in use.');
            }

            if ($password !== '') {
                $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, role = ?, loyalty_points = ?, password = ? WHERE id = ?'
                )->execute([$name, $email, $role, $points, password_hash($password, PASSWORD_DEFAULT), $userId]);
            } else {
                $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, role = ?, loyalty_points = ? WHERE id = ?'
                )->execute([$name, $email, $role, $points, $userId]);
            }
            $pdo->commit();

            if ($userId === (int) current_user()['id']) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['loyalty_points'] = $points;
            }

            flash('success', $name . ' was updated.');
            redirect('features/user-management/index.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The account could not be updated.');
            $account = array_merge($account, [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'loyalty_points' => $points,
            ]);
        }
    }
}

$pageTitle = 'Edit User';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center mb-3">
            <a class="btn btn-link ps-0" href="<?= e(url('features/user-management/index.php')) ?>">&laquo; Users</a>
            <h3 class="mb-0"><i class="bi bi-person-gear"></i> Edit account</h3>
        </div>
        <div class="card shadow-sm"><div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" required maxlength="100" value="<?= e($account['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required maxlength="150" value="<?= e($account['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <?php foreach (['Customer', 'Staff', 'Admin'] as $role): ?>
                                <option <?= $account['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Loyalty points</label>
                        <input class="form-control" type="number" min="0" name="loyalty_points"
                               value="<?= (int) $account['loyalty_points'] ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">New password <span class="text-muted">(optional)</span></label>
                        <input class="form-control" type="password" name="password" minlength="8"
                               autocomplete="new-password">
                        <div class="form-text">Leave blank to keep the current password.</div>
                    </div>
                </div>
                <button class="btn btn-jms mt-4"><i class="bi bi-check-lg"></i> Save changes</button>
            </form>
        </div></div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
