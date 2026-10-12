<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/mailer.php';
requireAdmin();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $status = $_GET['status'] ?? 'all';
        
        if ($status === 'all') {
            $stmt = $db->query("SELECT o.*, u.name as customer_name FROM orders o 
                                LEFT JOIN users u ON o.user_id = u.id 
                                ORDER BY o.created_at DESC");
        } else {
            $stmt = $db->prepare("SELECT o.*, u.name as customer_name FROM orders o 
                                  LEFT JOIN users u ON o.user_id = u.id 
                                  WHERE o.status = :s ORDER BY o.created_at DESC");
            $stmt->execute([':s' => $status]);
        }
        
        $orders = $stmt->fetchAll();
        
        // Get items for each order
        foreach ($orders as &$order) {
            $stmt = $db->prepare("SELECT oi.*, p.name as product_name, p.image_url 
                                  FROM order_items oi 
                                  JOIN products p ON oi.product_id = p.id 
                                  WHERE oi.order_id = :oid");
            $stmt->execute([':oid' => $order['id']]);
            $order['items'] = $stmt->fetchAll();
        }
        
        echo json_encode(['success' => true, 'data' => $orders]);
    }
    
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"));
        
        $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $statuses = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
        if (!$id || !in_array($data->status ?? '', $statuses, true)) {
            throw new InvalidArgumentException('ID atau status pesanan tidak valid.');
        }
        $stmt = $db->prepare("UPDATE orders SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $data->status, ':id' => $id]);

        $orderStmt = $db->prepare(
            'SELECT order_code, recipient_name, recipient_email, total_amount FROM orders WHERE id = :id'
        );
        $orderStmt->execute([':id' => $id]);
        $order = $orderStmt->fetch();
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan.']);
            exit;
        }

        $emailSent = false;
        if (!empty($order['recipient_email'])) {
            $emailSent = sendConfiguredEmail(
                $order['recipient_email'],
                $order['recipient_name'],
                'Status pesanan diperbarui: ' . $order['order_code'],
                '<h2>Status pesanan diperbarui</h2><p>Pesanan <strong>' . emailEscape($order['order_code']) .
                    '</strong> sekarang berstatus <strong>' . emailEscape($data->status) .
                    '</strong>.</p><p>Total pesanan: Rp ' . number_format((float)$order['total_amount'], 0, ',', '.') . '</p>',
                "Status pesanan {$order['order_code']} sekarang {$data->status}. Total pesanan: Rp " .
                    number_format((float)$order['total_amount'], 0, ',', '.') . '.'
            );
        }
        echo json_encode([
            'success' => true,
            'message' => 'Status diupdate',
            'email_sent' => $emailSent,
            'email_recipient_available' => !empty($order['recipient_email'])
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>