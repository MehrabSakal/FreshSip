<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Admin');

$type = $_GET['type'] ?? 'daily';

switch ($type) {
    case 'monthly':
        $sql = "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, COUNT(*) AS orders,
                       SUM(total_amount) AS revenue
                FROM orders WHERE status <> 'Cancelled'
                GROUP BY DATE_FORMAT(order_date, '%Y-%m') ORDER BY month DESC";
        $headers  = ['Month', 'Orders', 'Revenue'];
        $filename = 'monthly_sales.csv';
        break;

    case 'top':
        $sql = "SELECT oi.product_name AS item, SUM(oi.quantity) AS qty_sold,
                       SUM(oi.quantity * oi.unit_price) AS revenue
                FROM order_items oi JOIN orders o ON o.id = oi.order_id
                WHERE o.status <> 'Cancelled'
                GROUP BY oi.product_name ORDER BY qty_sold DESC";
        $headers  = ['Item', 'Qty Sold', 'Revenue'];
        $filename = 'top_selling_items.csv';
        break;

    case 'daily':
    default:
        $sql = "SELECT DATE(order_date) AS date, COUNT(*) AS orders,
                       SUM(total_amount) AS revenue
                FROM orders WHERE status <> 'Cancelled'
                GROUP BY DATE(order_date) ORDER BY date DESC";
        $headers  = ['Date', 'Orders', 'Revenue'];
        $filename = 'daily_sales.csv';
        break;
}

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
