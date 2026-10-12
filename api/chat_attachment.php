<?php
require_once '../config/database.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Silakan masuk untuk melihat foto chat.');
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['customer', 'admin'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}
$messageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$messageId) {
    http_response_code(400);
    exit('ID foto chat tidak valid.');
}

try {
    $db = (new Database())->getConnection();
    $sql = 'SELECT m.attachment_path
            FROM chat_messages m
            JOIN chat_conversations c ON c.id = m.conversation_id
            WHERE m.id = :id';
    $params = [':id' => $messageId];
    if ($role === 'customer') {
        $sql .= ' AND c.user_id = :user_id';
        $params[':user_id'] = $_SESSION['user_id'];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $path = $stmt->fetchColumn();
    if (!$path || !preg_match('#^uploads/chat-images/([a-f0-9]{32})\.(jpg|png|webp)$#', $path, $matches)) {
        http_response_code(404);
        exit('Foto chat tidak ditemukan.');
    }

    $baseDir = realpath(__DIR__ . '/../uploads/chat-images');
    $filePath = $baseDir ? realpath($baseDir . DIRECTORY_SEPARATOR . $matches[1] . '.' . $matches[2]) : false;
    if (!$baseDir || !$filePath || dirname($filePath) !== $baseDir || !is_file($filePath)) {
        http_response_code(404);
        exit('Foto chat tidak ditemukan.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
    $allowedTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (!isset($allowedTypes[$matches[2]]) || $mime !== $allowedTypes[$matches[2]]) {
        http_response_code(415);
        exit('Format foto chat tidak valid.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: inline; filename="chat-' . $messageId . '.' . $matches[2] . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
} catch (Throwable $e) {
    error_log('Chat attachment view failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Foto chat tidak dapat ditampilkan.');
}
