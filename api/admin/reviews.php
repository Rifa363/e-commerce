<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

try {
    $db = (new Database())->getConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = $_GET['status'] ?? 'pending';
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status ulasan tidak valid.']);
            exit;
        }
        $sql = 'SELECT r.*, p.name AS product_name, u.name AS customer_name, u.email AS customer_email
                FROM product_reviews r
                JOIN products p ON p.id = r.product_id
                JOIN users u ON u.id = r.user_id';
        if ($status !== 'all') {
            $sql .= ' WHERE r.status = :status';
        }
        $sql .= ' ORDER BY r.created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($status === 'all' ? [] : [':status' => $status]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        header('Allow: GET, PUT');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'));
    $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id || !in_array($data->status ?? '', ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('ID atau status ulasan tidak valid.');
    }
    $stmt = $db->prepare('UPDATE product_reviews SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $data->status, ':id' => $id]);
    if ($stmt->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM product_reviews WHERE id = :id');
        $exists->execute([':id' => $id]);
        if (!$exists->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ulasan tidak ditemukan.']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Status ulasan diperbarui.']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Review moderation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Status ulasan tidak dapat diperbarui.']);
}
