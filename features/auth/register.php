<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $errors = [];
    if ($name === '')                              $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))$errors[] = 'A valid email is required.';
    if (strlen($password) < 6)                     $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                    $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        // Public sign-up always creates a Customer account.
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)')
            ->execute([$name, $email, $hash, 'Customer']);
        flash('success', 'Account created — please log in.');
        redirect('features/auth/login.php');
    }
    flash('error', implode(' ', $errors));
    $_SESSION['old'] = ['name' => $name, 'email' => $email];
    redirect('features/auth/register.php');
}

$old = $_SESSION['old'] ?? ['name' => '', 'email' => ''];
unset($_SESSION['old']);

$pageTitle = 'Sign up';
require_once APP_ROOT . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-3">Create a customer account</h4>
                <form method="post" action="<?= e(url('features/auth/register.php')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="confirm" class="form-control" required>
                    </div>
                    <button class="btn btn-jms w-100">Sign up</button>
                </form>
                <hr>
                <p class="mb-0">Already registered? <a href="<?= e(url('features/auth/login.php')) ?>">Log in</a></p>
            </div>
        </div>
    </div>
</div>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
