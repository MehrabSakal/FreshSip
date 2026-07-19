<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

// Warn if demo accounts are missing (common when setup.php was not opened).
$demoEmails = ['admin@freshsip.test', 'staff@freshsip.test'];
$missingDemo = [];
foreach ($demoEmails as $demoEmail) {
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$demoEmail]);
    if (!$check->fetch()) {
        $missingDemo[] = $demoEmail;
    }
}

$oldEmail = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Store a safe subset of the user in the session.
        $_SESSION['user'] = [
            'id'             => (int) $user['id'],
            'name'           => $user['name'],
            'email'          => $user['email'],
            'role'           => $user['role'],
            'loyalty_points' => (int) $user['loyalty_points'],
        ];
        session_regenerate_id(true);
        flash('success', 'Welcome back, ' . $user['name'] . '!');

        // Send staff/admin straight to their workspace.
        if ($user['role'] === 'Admin') redirect('admin/dashboard.php');
        if ($user['role'] === 'Staff') redirect('features/kitchen-display/index.php');
        redirect('index.php');
    }
    $_SESSION['login_email'] = $email;
    flash('error', 'Invalid email or password. Demo password is password123 — run setup.php if you have not yet.');
    redirect('features/auth/login.php');
}

$pageTitle = 'Login';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-3">Log in</h4>

                <?php if ($missingDemo): ?>
                    <div class="alert alert-warning">
                        Demo Admin/Staff accounts are not in the database yet.
                        Open <a href="<?= e(url('setup.php')) ?>"><strong>setup.php</strong></a> once, then try again.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('features/auth/login.php')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($oldEmail) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-jms w-100">Log in</button>
                </form>
                <hr>
                <p class="small text-muted mb-1">Demo accounts (password: <code>password123</code>):</p>
                <ul class="small text-muted mb-2">
                    <li><strong>Admin:</strong> admin@freshsip.test</li>
                    <li><strong>Staff:</strong> staff@freshsip.test</li>
                    <li><strong>Customer:</strong> alice@freshsip.test</li>
                </ul>
                <p class="small mb-2">
                    First time? Run
                    <a href="<?= e(url('setup.php')) ?>">setup.php</a>
                    after importing the database.
                </p>
                <p class="mb-0">No account? <a href="<?= e(url('features/auth/register.php')) ?>">Sign up</a></p>
            </div>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
