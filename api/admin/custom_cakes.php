<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/mailer.php';
requireAdmin();

try {
    $db = (new Database())->getConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = $_GET['status'] ?? 'all';
        $allowed = ['all', 'pending', 'quoted', 'confirmed', 'in_production', 'ready', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Status pre-order tidak valid.']);
            exit;
        }
        $sql = "SELECT c.*, u.name AS customer_name, u.email AS customer_email, u.phone AS account_phone
                FROM custom_cake_orders c JOIN users u ON u.id = c.user_id";
        if ($status !== 'all') {
            $sql .= ' WHERE c.status = :status';
        }
        $sql .= ' ORDER BY c.created_at DESC';
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
    $statuses = ['pending', 'quoted', 'confirmed', 'in_production', 'ready', 'completed', 'cancelled'];
    $status = $data->status ?? '';
    if (!$id || !in_array($status, $statuses, true)) {
        throw new InvalidArgumentException('ID atau status pre-order tidak valid.');
    }
    $quoteRaw = $data->admin_quote ?? '';
    if ($quoteRaw !== '' && (!is_numeric($quoteRaw) || (float)$quoteRaw < 0 || (float)$quoteRaw > 999999999999)) {
        throw new InvalidArgumentException('Harga penawaran tidak valid.');
    }
    $quote = $quoteRaw === '' ? null : round((float)$quoteRaw, 2);
    if ($status === 'quoted' && (!$quote || $quote <= 0)) {
        throw new InvalidArgumentException('Masukkan harga penawaran sebelum mengirim status penawaran.');
    }
    $adminNotes = trim((string)($data->admin_notes ?? ''));
    if (mb_strlen($adminNotes) > 2000) {
        throw new InvalidArgumentException('Catatan admin maksimal 2000 karakter.');
    }

    $stmt = $db->prepare(
        'UPDATE custom_cake_orders SET status = :status, admin_quote = :quote, admin_notes = :notes WHERE id = :id'
    );
    $stmt->execute([':status' => $status, ':quote' => $quote, ':notes' => $adminNotes ?: null, ':id' => $id]);
    if ($stmt->rowCount() === 0) {
        $exists = $db->prepare('SELECT id FROM custom_cake_orders WHERE id = :id');
        $exists->execute([':id' => $id]);
        if (!$exists->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pre-order tidak ditemukan.']);
            exit;
        }
    }

    $orderStmt = $db->prepare(
        'SELECT c.order_code, c.status, c.admin_quote, c.admin_notes, c.event_at, c.cake_type,
                u.name, u.email
         FROM custom_cake_orders c JOIN users u ON u.id = c.user_id WHERE c.id = :id'
    );
    $orderStmt->execute([':id' => $id]);
    $order = $orderStmt->fetch();
    $emailSent = false;
    if ($order && $order['email']) {
        $quoteText = $order['admin_quote'] !== null
            ? '<p>Estimasi harga: <strong>Rp ' . number_format((float)$order['admin_quote'], 0, ',', '.') . '</strong></p>'
            : '';
        $notesText = $order['admin_notes'] ? '<p>Catatan: ' . nl2br(emailEscape($order['admin_notes'])) . '</p>' : '';
        $emailSent = sendConfiguredEmail(
            $order['email'],
            $order['name'],
            'Pembaruan pre-order custom cake: ' . $order['order_code'],
            '<h2>Status pre-order diperbarui</h2><p>Kode: <strong>' . emailEscape($order['order_code']) .
                '</strong><br>Status: ' . emailEscape($order['status']) . '</p>' . $quoteText . $notesText,
            "Status pre-order {$order['order_code']} diperbarui: {$order['status']}." .
                ($order['admin_quote'] !== null ? ' Estimasi harga: Rp ' . number_format((float)$order['admin_quote'], 0, ',', '.') . '.' : '') .
                ($order['admin_notes'] ? ' Catatan: ' . $order['admin_notes'] : '')
        );
    }

    echo json_encode(['success' => true, 'message' => 'Pre-order diperbarui.', 'email_sent' => $emailSent]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Custom cake admin update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Pre-order tidak dapat diperbarui saat ini.']);
}
