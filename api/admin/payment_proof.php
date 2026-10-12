<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    http_response_code(400);
    exit('ID pesanan tidak valid.');
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare('SELECT payment_proof FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $proof = $stmt->fetchColumn();

    if (!$proof || !preg_match('#^uploads/payment-proofs/([a-f0-9]{32})\.(jpg|png|webp)$#', $proof, $matches)) {
        http_response_code(404);
        exit('Bukti transfer tidak ditemukan.');
    }

    $baseDir = realpath(__DIR__ . '/../../uploads/payment-proofs');
    $filePath = $baseDir ? realpath($baseDir . DIRECTORY_SEPARATOR . $matches[1] . '.' . $matches[2]) : false;
    if (!$baseDir || !$filePath || dirname($filePath) !== $baseDir || !is_file($filePath)) {
        http_response_code(404);
        exit('Bukti transfer tidak ditemukan.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($filePath);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($allowedTypes[$matches[2]]) || $mime !== $allowedTypes[$matches[2]]) {
        http_response_code(415);
        exit('Format bukti transfer tidak valid.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="bukti-transfer-' . $orderId . '.' . $matches[2] . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
} catch (Throwable $e) {
    error_log('Payment proof view failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Bukti transfer tidak dapat ditampilkan.');
}
