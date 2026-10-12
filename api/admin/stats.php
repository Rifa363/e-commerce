<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

$db = (new Database())->getConnection();

try {
    // Total revenue (orders completed/paid)
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE status IN ('paid', 'processing', 'shipped', 'completed')");
    $revenue = $stmt->fetch()['revenue'];

    // Total orders
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];

    // Orders today
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
    $orders_today = $stmt->fetch()['total'];

    // Total products
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE is_available = 1");
    $total_products = $stmt->fetch()['total'];

    // Low stock alert
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stock < 10 AND is_available = 1");
    $low_stock = $stmt->fetch()['total'];

    // Pending orders
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $pending = $stmt->fetch()['total'];

    // Recent orders
    $stmt = $db->query("SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
    $recent = $stmt->fetchAll();

    // Top products
    $stmt = $db->query("SELECT name, sold_count, stock FROM products WHERE is_available = 1 ORDER BY sold_count DESC LIMIT 5");
    $top_products = $stmt->fetchAll();

    // Sales chart (last 7 days)
    $stmt = $db->query("SELECT DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as orders 
                        FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                        GROUP BY DATE(created_at) ORDER BY date ASC");
    $chart = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'revenue' => (float)$revenue,
            'total_orders' => (int)$total_orders,
            'orders_today' => (int)$orders_today,
            'total_products' => (int)$total_products,
            'low_stock' => (int)$low_stock,
            'pending_orders' => (int)$pending,
            'recent_orders' => $recent,
            'top_products' => $top_products,
            'sales_chart' => $chart
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>