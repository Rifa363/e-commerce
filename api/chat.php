<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'chat_service.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk untuk menggunakan chat.']);
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['customer', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses chat tidak diizinkan.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'unread-count') {
            if ($role === 'customer') {
                $stmt = $db->prepare(
                    "SELECT COUNT(*)
                     FROM chat_messages m
                     JOIN chat_conversations c ON c.id = m.conversation_id
                     LEFT JOIN chat_read_status r ON r.conversation_id = c.id
                     WHERE c.user_id = :user_id AND m.sender_role = 'admin'
                       AND m.id > COALESCE(r.customer_last_read_message_id, 0)"
                );
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
            } else {
                $stmt = $db->query(
                    "SELECT COUNT(*)
                     FROM chat_messages m
                     JOIN chat_conversations c ON c.id = m.conversation_id
                     LEFT JOIN chat_read_status r ON r.conversation_id = c.id
                     WHERE m.sender_role = 'customer'
                       AND m.id > COALESCE(r.admin_last_read_message_id, 0)"
                );
            }
            echo json_encode(['success' => true, 'data' => ['unread_count' => (int)$stmt->fetchColumn()]]);
            exit;
        }

        $conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (isset($_GET['conversation_id']) && $_GET['conversation_id'] !== '' && !$conversationId) {
            throw new InvalidArgumentException('ID percakapan tidak valid.');
        }
        if ($role === 'admin' && !$conversationId) {
            $stmt = $db->query(
                "SELECT c.id, c.user_id, c.updated_at, u.name AS customer_name,
                        (SELECT m.id
                         FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_id,
                        (SELECT m.sender_role
                         FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_sender_role,
                        (SELECT CASE WHEN m.message IS NULL OR m.message = '' THEN '[Foto]' ELSE m.message END
                         FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                        (SELECT COUNT(*) FROM chat_messages unread
                         WHERE unread.conversation_id = c.id AND unread.sender_role = 'customer'
                           AND unread.id > COALESCE(
                               (SELECT r.admin_last_read_message_id FROM chat_read_status r WHERE r.conversation_id = c.id),
                               0
                           )) AS unread_count
                 FROM chat_conversations c
                 JOIN users u ON u.id = c.user_id
                 ORDER BY c.updated_at DESC"
            );
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        if ($role === 'customer' && !$conversationId) {
            $stmt = $db->prepare('SELECT id FROM chat_conversations WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $conversation = $stmt->fetch();
            if (!$conversation) {
                echo json_encode(['success' => true, 'data' => ['conversation' => null, 'messages' => []]]);
                exit;
            }
            $conversationId = (int)$conversation['id'];
        }

        $conversationSql = 'SELECT c.id, c.user_id, u.name AS customer_name
                            FROM chat_conversations c JOIN users u ON u.id = c.user_id
                            WHERE c.id = :id';
        $params = [':id' => $conversationId];
        if ($role === 'customer') {
            $conversationSql .= ' AND c.user_id = :user_id';
            $params[':user_id'] = $_SESSION['user_id'];
        }
        $stmt = $db->prepare($conversationSql);
        $stmt->execute($params);
        $conversation = $stmt->fetch();
        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Percakapan tidak ditemukan.']);
            exit;
        }

        $stmt = $db->prepare(
            'SELECT id, sender_id, sender_role, message, attachment_path, created_at
             FROM chat_messages WHERE conversation_id = :id ORDER BY id DESC LIMIT 100'
        );
        $stmt->execute([':id' => $conversationId]);
        $messages = array_reverse($stmt->fetchAll());
        echo json_encode(
            ['success' => true, 'data' => ['conversation' => $conversation, 'messages' => $messages]],
            JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
        exit;
    }

    $isMultipart = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;
    $data = $isMultipart ? (object)$_POST : json_decode(file_get_contents('php://input'));
    if (($data->action ?? '') === 'mark_read') {
        $conversationId = filter_var($data->conversation_id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$conversationId) {
            throw new InvalidArgumentException('ID percakapan tidak valid.');
        }
        $conversationSql = 'SELECT id FROM chat_conversations WHERE id = :id';
        $params = [':id' => $conversationId];
        if ($role === 'customer') {
            $conversationSql .= ' AND user_id = :user_id';
            $params[':user_id'] = $_SESSION['user_id'];
        }
        $stmt = $db->prepare($conversationSql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Percakapan tidak ditemukan.']);
            exit;
        }

        $incomingRole = $role === 'admin' ? 'customer' : 'admin';
        $readColumn = $role === 'admin' ? 'admin_last_read_message_id' : 'customer_last_read_message_id';
        $stmt = $db->prepare(
            'SELECT COALESCE(MAX(id), 0) FROM chat_messages
             WHERE conversation_id = :conversation_id AND sender_role = :sender_role'
        );
        $stmt->execute([':conversation_id' => $conversationId, ':sender_role' => $incomingRole]);
        $lastReadId = (int)$stmt->fetchColumn();
        $stmt = $db->prepare(
            "INSERT INTO chat_read_status (conversation_id, {$readColumn})
             VALUES (:conversation_id, :last_read_id)
             ON DUPLICATE KEY UPDATE {$readColumn} = GREATEST({$readColumn}, VALUES({$readColumn}))"
        );
        $stmt->execute([':conversation_id' => $conversationId, ':last_read_id' => $lastReadId]);
        echo json_encode(['success' => true, 'data' => ['conversation_id' => $conversationId]]);
        exit;
    }

    $message = trim((string)($data->message ?? ''));
    if (mb_strlen($message) > 2000) {
        throw new InvalidArgumentException('Pesan maksimal 2000 karakter.');
    }

    $uploadedImage = null;
    if (isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadedImage = $_FILES['image'];
        if ($uploadedImage['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $uploadedImage['error'] === UPLOAD_ERR_INI_SIZE || $uploadedImage['error'] === UPLOAD_ERR_FORM_SIZE
                    ? 'Ukuran foto melebihi batas upload server.'
                    : 'Upload foto gagal. Silakan coba lagi.'
            );
        }
        if ($uploadedImage['size'] <= 0 || $uploadedImage['size'] > 5 * 1024 * 1024
            || !is_uploaded_file($uploadedImage['tmp_name'])) {
            throw new InvalidArgumentException('Ukuran foto harus maksimal 5 MB dan file upload harus valid.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($uploadedImage['tmp_name']);
        $imageInfo = @getimagesize($uploadedImage['tmp_name']);
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowedTypes[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime) {
            throw new InvalidArgumentException('Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.');
        }
        $uploadedImage['extension'] = $allowedTypes[$mime];
    }
    if ($message === '' && $uploadedImage === null) {
        throw new InvalidArgumentException('Tulis pesan atau lampirkan foto terlebih dahulu.');
    }

    $savedImagePath = null;
    $db->beginTransaction();
    if ($role === 'customer') {
        $conversationId = getOrCreateChatConversation($db, (int)$_SESSION['user_id']);
    } else {
        $conversationId = filter_var($data->conversation_id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$conversationId) {
            throw new InvalidArgumentException('Pilih percakapan customer terlebih dahulu.');
        }
        $stmt = $db->prepare('SELECT id FROM chat_conversations WHERE id = :id');
        $stmt->execute([':id' => $conversationId]);
        if (!$stmt->fetch()) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Percakapan tidak ditemukan.']);
            exit;
        }
    }

    if ($uploadedImage !== null) {
        $uploadDir = __DIR__ . '/../uploads/chat-images/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Folder penyimpanan foto chat tidak dapat dibuat.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $uploadedImage['extension'];
        $savedImagePath = $uploadDir . $filename;
        if (!move_uploaded_file($uploadedImage['tmp_name'], $savedImagePath)) {
            throw new RuntimeException('Foto gagal disimpan. Silakan coba lagi.');
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message, attachment_path)
         VALUES (:conversation_id, :sender_id, :sender_role, :message, :attachment_path)'
    );
    $stmt->execute([
        ':conversation_id' => $conversationId,
        ':sender_id' => $_SESSION['user_id'],
        ':sender_role' => $role,
        ':message' => $message,
        ':attachment_path' => $uploadedImage === null ? null : 'uploads/chat-images/' . $filename
    ]);
    $messageId = (int)$db->lastInsertId();
    $db->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([':id' => $conversationId]);
    $db->commit();
    echo json_encode([
        'success' => true,
        'data' => ['conversation_id' => $conversationId, 'message_id' => $messageId]
    ]);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (isset($savedImagePath) && $savedImagePath && is_file($savedImagePath)) {
        unlink($savedImagePath);
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (isset($savedImagePath) && $savedImagePath && is_file($savedImagePath)) {
        unlink($savedImagePath);
    }
    error_log('In-app chat request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chat tidak dapat dimuat saat ini. Silakan coba lagi.']);
}
