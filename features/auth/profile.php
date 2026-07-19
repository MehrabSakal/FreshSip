<?php
/**
 * Account profile - lets any logged-in user update their name, email and
 * password. Customers also see their loyalty balance and a quick link to
 * their order history.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $errors = [];

    if ($action === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (!$errors) {
            $dupe = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $dupe->execute([$email, $user['id']]);
            if ($dupe->fetch()) {
                $errors[] = 'That email is already in use by another account.';
            }
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                ->execute([$name, $email, $user['id']]);
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;
            flash('success', 'Profile updated.');
        } else {
            flash('error', implode(' ', $errors));
        }
        redirect('features/auth/profile.php');
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (strlen($new) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed successfully.');
        } else {
            flash('error', implode(' ', $errors));
        }
        redirect('features/auth/profile.php');
    }
}

// Fresh values + loyalty balance from DB.
$stmt = $pdo->prepare('SELECT name, email, role, loyalty_points, created_at FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$account = $stmt->fetch();

$pageTitle = 'My Profile';
require_once APP_ROOT . '/includes/header.php';
?>
<h3 class="mb-3"><i class="bi bi-person-gear text-success"></i> My Profile</h3>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <div class="display-1 text-success"><i class="bi bi-person-circle"></i></div>
                <h5 class="mb-0"><?= e($account['name']) ?></h5>
                <div class="text-muted small mb-2"><?= e($account['email']) ?></div>
                <span class="badge bg-<?= $account['role'] === 'Admin' ? 'dark' : ($account['role'] === 'Staff' ? 'info' : 'success') ?>">
                    <?= e($account['role']) ?>
                </span>
                <hr>
                <div class="small text-muted">Member since</div>
                <div><?= e(date('M j, Y', strtotime($account['created_at']))) ?></div>

                <?php if ($account['role'] === 'Customer'): ?>
                    <hr>
                    <div class="small text-muted">Loyalty balance</div>
                    <div class="h4 mb-2"><i class="bi bi-star-fill text-warning"></i> <?= (int) $account['loyalty_points'] ?> pts</div>
                    <a class="btn btn-sm btn-outline-success" href="<?= e(url('features/order-management/my_orders.php')) ?>">
                        <i class="bi bi-bag-check"></i> View my orders
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Account details</strong></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('features/auth/profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($account['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($account['email']) ?>" required>
                    </div>
                    <button class="btn btn-jms"><i class="bi bi-save"></i> Save changes</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Change password</strong></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('features/auth/profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label class="form-label">Current password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm new password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <button class="btn btn-outline-secondary"><i class="bi bi-key"></i> Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
