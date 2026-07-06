<?php
/**
 * Cart / order summary (Epic 001).
 * Handles quantity updates, line removal and clearing.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $cart   = cart();

    if ($action === 'update') {
        $key = $_POST['key'] ?? '';
        $qty = (int) ($_POST['quantity'] ?? 1);
        if (isset($cart[$key])) {
            if ($qty <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['quantity'] = $qty;
            }
        }
        $_SESSION['cart'] = $cart;
    } elseif ($action === 'remove') {
        unset($cart[$_POST['key'] ?? '']);
        $_SESSION['cart'] = $cart;
    } elseif ($action === 'clear') {
        cart_clear();
    }
    redirect('features/order-management/cart.php');
}

$cart = cart();
$pageTitle = 'Your Cart';
require_once APP_ROOT . '/includes/header.php';
?>
<h3 class="mb-3"><i class="bi bi-cart3"></i> Your order</h3>

<?php if (!$cart): ?>
    <div class="card shadow-sm"><div class="card-body text-center py-5">
        <i class="bi bi-cart-x display-4 text-muted"></i>
        <p class="mt-3 mb-3">Your cart is empty.</p>
        <a class="btn btn-jms" href="<?= e(url('index.php')) ?>">Browse the menu</a>
    </div></div>
<?php else: ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Item</th><th>Unit</th><th style="width:150px">Qty</th><th>Subtotal</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($cart as $key => $item): ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= e($item['icon']) ?> text-success"></i>
                            <strong><?= e($item['name']) ?></strong>
                            <?php if ($item['customizations']): ?>
                                <div>
                                    <?php foreach (explode(', ', $item['customizations']) as $c): ?>
                                        <span class="customization-tag"><?= e($c) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= money($item['price']) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('features/order-management/cart.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="key" value="<?= e($key) ?>">
                                <input type="number" min="0" name="quantity" value="<?= (int) $item['quantity'] ?>"
                                       class="form-control form-control-sm" data-cart-qty style="width:90px">
                            </form>
                        </td>
                        <td><strong><?= money($item['price'] * $item['quantity']) ?></strong></td>
                        <td class="text-end">
                            <form method="post" action="<?= e(url('features/order-management/cart.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="key" value="<?= e($key) ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total</th>
                        <th colspan="2"><?= money(cart_total()) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <div>
            <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>">
                <i class="bi bi-arrow-left"></i> Keep shopping
            </a>
            <form method="post" action="<?= e(url('features/order-management/cart.php')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-outline-danger" data-confirm="Empty the cart?">Clear cart</button>
            </form>
        </div>
        <a class="btn btn-jms btn-lg" href="<?= e(url('features/billing/checkout.php')) ?>">
            Checkout <i class="bi bi-arrow-right"></i>
        </a>
    </div>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
