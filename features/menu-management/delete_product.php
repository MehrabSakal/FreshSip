<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('features/menu-management/index.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

flash('success', 'Product deleted.');
redirect('features/menu-management/index.php');
