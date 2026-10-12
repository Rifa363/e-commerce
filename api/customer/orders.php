<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
require_once '../../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk untuk melihat riwayat transaksi.']);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Riwayat ini hanya tersedia untuk akun customer.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare(
        "SELECT o.id, o.order_code, o.recipient_name, o.recipient_phone, o.address,
                o.shipping_method, o.shipping_cost, o.subtotal, o.total_amount,
                o.discount, o.voucher_code, o.payment_method, o.payment_details,
                o.payment_proof, o.status, o.created_at
         FROM orders o
         WHERE o.user_id = :user_id
         ORDER BY o.created_at DESC"
    );
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $orders = $stmt->fetchAll();

    if ($orders) {
        $placeholders = implode(',', array_fill(0, count($orders), '?'));
        $itemsStmt = $db->prepare(
            "SELECT oi.order_id, oi.quantity, oi.price, p.name AS product_name
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.order_id, oi.id"
        );
        $itemsStmt->execute(array_column($orders, 'id'));
        $itemsByOrder = [];
        foreach ($itemsStmt->fetchAll() as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
            $order['item_count'] = array_sum(array_column($order['items'], 'quantity'));
            $order['has_payment_proof'] = !empty($order['payment_proof']);
            unset($order['payment_proof']);
            $order['status_display'] = [
                'pending' => 'Menunggu pembayaran',
                'paid' => 'Dibayar',
                'processing' => 'Diproses',
                'shipped' => 'Dikirim',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan'
            ][$order['status']] ?? $order['status'];
            $order['payment_method_display'] = [
                'transfer' => 'Transfer bank',
                'qris' => 'QRIS',
                'cod' => 'Bayar di tempat (COD)'
            ][$order['payment_method']] ?? $order['payment_method'];
            $order['shipping_method_display'] = [
                'instant' => 'Instant (2-3 jam)',
                'sameday' => 'Same Day',
                'reguler' => 'Reguler (1-2 hari)'
            ][$order['shipping_method']] ?? $order['shipping_method'];
        }
        unset($order);
    }

    echo json_encode(['success' => true, 'data' => $orders], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Customer order history failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Riwayat transaksi tidak dapat dimuat. Silakan coba lagi.']);
}
