<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/mailer.php';
require_once 'chat_service.php';

try {
    $db = (new Database())->getConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$productId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Produk tidak valid.']);
            exit;
        }

        $productStmt = $db->prepare('SELECT id, name FROM products WHERE id = :id AND is_available = 1');
        $productStmt->execute([':id' => $productId]);
        $product = $productStmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan.']);
            exit;
        }

        $reviewsStmt = $db->prepare(
            "SELECT r.id, r.rating, r.review, r.created_at, u.name AS customer_name
             FROM product_reviews r JOIN users u ON u.id = r.user_id
             WHERE r.product_id = :product_id AND r.status = 'approved'
             ORDER BY r.created_at DESC LIMIT 50"
        );
        $reviewsStmt->execute([':product_id' => $productId]);
        $reviews = $reviewsStmt->fetchAll();
        $summaryStmt = $db->prepare(
            "SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating
             FROM product_reviews WHERE product_id = :product_id AND status = 'approved'"
        );
        $summaryStmt->execute([':product_id' => $productId]);
        $summary = $summaryStmt->fetch();

        $eligibility = ['logged_in' => false, 'can_review' => false, 'review_status' => null];
        if (isLoggedIn() && ($_SESSION['role'] ?? '') === 'customer') {
            $existingStmt = $db->prepare('SELECT status FROM product_reviews WHERE product_id = :product_id AND user_id = :user_id');
            $existingStmt->execute([':product_id' => $productId, ':user_id' => $_SESSION['user_id']]);
            $existing = $existingStmt->fetch();
            $purchasedStmt = $db->prepare(
                "SELECT 1 FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.user_id = :user_id AND oi.product_id = :product_id AND o.status = 'completed'
                 LIMIT 1"
            );
            $purchasedStmt->execute([':user_id' => $_SESSION['user_id'], ':product_id' => $productId]);
            $eligibility = [
                'logged_in' => true,
                'can_review' => (bool)$purchasedStmt->fetchColumn() && !$existing,
                'review_status' => $existing['status'] ?? null
            ];
        }

        echo json_encode([
            'success' => true,
            'product' => $product,
            'average_rating' => $summary['average_rating'] !== null ? round((float)$summary['average_rating'], 1) : null,
            'review_count' => (int)$summary['review_count'],
            'reviews' => $reviews,
            'eligibility' => $eligibility
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
        exit;
    }
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'customer') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Masuk sebagai customer untuk menulis ulasan.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'));
    $productId = filter_var($data->product_id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rating = filter_var($data->rating ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $review = is_string($data->review ?? null) ? trim($data->review) : '';
    if (!$productId || !$rating || mb_strlen($review) < 10 || mb_strlen($review) > 2000) {
        throw new InvalidArgumentException('Pilih rating 1-5 dan tulis ulasan 10-2000 karakter.');
    }

    $eligibleStmt = $db->prepare(
        "SELECT p.name AS product_name, u.name AS customer_name, u.email AS customer_email
         FROM products p CROSS JOIN users u
         WHERE p.id = :product_id AND p.is_available = 1 AND u.id = :user_id AND u.role = 'customer'
           AND EXISTS (
               SELECT 1 FROM orders o JOIN order_items oi ON oi.order_id = o.id
               WHERE o.user_id = u.id AND oi.product_id = p.id AND o.status = 'completed'
           )"
    );
    $eligibleStmt->execute([':product_id' => $productId, ':user_id' => $_SESSION['user_id']]);
    $eligibility = $eligibleStmt->fetch();
    if (!$eligibility) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ulasan hanya dapat diberikan untuk produk dari pesanan yang sudah selesai.']);
        exit;
    }

    $db->beginTransaction();
    $insert = $db->prepare(
        "INSERT INTO product_reviews (product_id, user_id, rating, review, status)
         VALUES (:product_id, :user_id, :rating, :review, 'pending')"
    );
    $insert->execute([
        ':product_id' => $productId,
        ':user_id' => $_SESSION['user_id'],
        ':rating' => $rating,
        ':review' => $review
    ]);

    $conversationId = getOrCreateChatConversation($db, (int)$_SESSION['user_id']);
    saveChatMessage(
        $db,
        $conversationId,
        (int)$_SESSION['user_id'],
        'customer',
        "Ulasan baru untuk {$eligibility['product_name']}\nRating: {$rating}/5\nUlasan: {$review}"
    );
    $db->commit();

    $emailBody = '<h2>Ulasan produk menunggu moderasi</h2><p>Produk: ' . emailEscape($eligibility['product_name']) .
        '</p><p>Customer: ' . emailEscape($eligibility['customer_name']) . '</p><p>Rating: ' . (int)$rating .
        '/5</p><blockquote>' . nl2br(emailEscape($review)) . '</blockquote>';
    $emailSent = sendAdminNotificationEmail(
        'Ulasan baru menunggu moderasi: ' . $eligibility['product_name'],
        $emailBody,
        "Ulasan baru menunggu moderasi\nProduk: {$eligibility['product_name']}\nCustomer: {$eligibility['customer_name']}\nRating: $rating/5\n$review"
    );
    echo json_encode([
        'success' => true,
        'message' => 'Ulasan dikirim dan akan tampil setelah admin menyetujuinya.',
        'email_sent' => $emailSent,
        'conversation_id' => $conversationId
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Anda sudah pernah mengirim ulasan untuk produk ini.']);
        exit;
    }
    error_log('Product review request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ulasan tidak dapat diproses saat ini.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Product review request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ulasan tidak dapat diproses saat ini.']);
}
