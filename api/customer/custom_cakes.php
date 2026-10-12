<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../config/mailer.php';
require_once '../chat_service.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk untuk memesan custom cake.']);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Fitur ini hanya tersedia untuk akun customer.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT order_code, cake_type, servings, flavor, filling, frosting, design_description,
                    cake_message, allergies, event_at, budget, admin_quote, status, customer_notes,
                    admin_notes, created_at, updated_at
             FROM custom_cake_orders WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'));
    if (!$data) {
        throw new InvalidArgumentException('Data pre-order tidak valid.');
    }

    $cakeType = trim((string)($data->cake_type ?? ''));
    $flavor = trim((string)($data->flavor ?? ''));
    $design = trim((string)($data->design_description ?? ''));
    $servings = filter_var($data->servings ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
    if ($cakeType === '' || mb_strlen($cakeType) > 100 || $flavor === '' || mb_strlen($flavor) > 100
        || !$servings || $design === '' || mb_strlen($design) < 10 || mb_strlen($design) > 3000) {
        throw new InvalidArgumentException('Isi jenis kue, jumlah porsi, rasa, dan detail desain (10-3000 karakter).');
    }

    $parseOptionalText = static function ($value, $field, $limit) {
        if (!is_string($value ?? '')) {
            throw new InvalidArgumentException($field . ' tidak valid.');
        }
        $value = trim($value);
        if (mb_strlen($value) > $limit) {
            throw new InvalidArgumentException($field . ' maksimal ' . $limit . ' karakter.');
        }
        return $value === '' ? null : $value;
    };
    $filling = $parseOptionalText($data->filling ?? '', 'Isian', 100);
    $frosting = $parseOptionalText($data->frosting ?? '', 'Frosting', 100);
    $cakeMessage = $parseOptionalText($data->cake_message ?? '', 'Tulisan kue', 100);
    $allergies = $parseOptionalText($data->allergies ?? '', 'Informasi alergi', 1000);
    $customerNotes = $parseOptionalText($data->customer_notes ?? '', 'Catatan', 1000);

    $eventAtInput = $data->event_at ?? '';
    if (!is_string($eventAtInput)) {
        throw new InvalidArgumentException('Tanggal acara tidak valid.');
    }
    $jakarta = new DateTimeZone('Asia/Jakarta');
    $eventAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $eventAtInput, $jakarta);
    if (!$eventAt || $eventAt->format('Y-m-d\TH:i') !== $eventAtInput
        || $eventAt < new DateTimeImmutable('+48 hours', $jakarta)) {
        throw new InvalidArgumentException('Pilih jadwal acara minimal 48 jam dari sekarang.');
    }

    $budgetRaw = $data->budget ?? '';
    if ($budgetRaw !== '' && (!is_numeric($budgetRaw) || (float)$budgetRaw < 0 || (float)$budgetRaw > 999999999999)) {
        throw new InvalidArgumentException('Anggaran tidak valid.');
    }
    $budget = $budgetRaw === '' ? null : round((float)$budgetRaw, 2);

    $userStmt = $db->prepare("SELECT name, email FROM users WHERE id = :id AND role = 'customer'");
    $userStmt->execute([':id' => $_SESSION['user_id']]);
    $customer = $userStmt->fetch();
    if (!$customer) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi customer tidak valid. Silakan masuk kembali.']);
        exit;
    }

    $orderCode = 'CC' . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));
    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO custom_cake_orders
         (user_id, order_code, cake_type, servings, flavor, filling, frosting, design_description,
          cake_message, allergies, event_at, budget, customer_notes)
         VALUES (:user_id, :code, :type, :servings, :flavor, :filling, :frosting, :design,
                 :cake_message, :allergies, :event_at, :budget, :notes)'
    );
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':code' => $orderCode,
        ':type' => $cakeType,
        ':servings' => $servings,
        ':flavor' => $flavor,
        ':filling' => $filling,
        ':frosting' => $frosting,
        ':design' => $design,
        ':cake_message' => $cakeMessage,
        ':allergies' => $allergies,
        ':event_at' => $eventAt->format('Y-m-d H:i:s'),
        ':budget' => $budget,
        ':notes' => $customerNotes
    ]);

    $conversationId = getOrCreateChatConversation($db, (int)$_SESSION['user_id']);
    $chatMessage = "Pre-order custom cake baru\nKode: {$orderCode}\nJenis: {$cakeType}\nPorsi: {$servings}\nRasa: {$flavor}\nTanggal acara: " .
        $eventAt->format('d/m/Y H:i') . "\nDetail desain: {$design}";
    saveChatMessage($db, $conversationId, (int)$_SESSION['user_id'], 'customer', $chatMessage);
    $db->commit();

    $eventLabel = $eventAt->format('d/m/Y H:i');
    $emailBody = '<h2>Pre-order custom cake baru</h2><p>Kode: ' . emailEscape($orderCode) .
        '</p><p>Customer: ' . emailEscape($customer['name']) . ' (' . emailEscape($customer['email']) .
        ')</p><p>Jenis: ' . emailEscape($cakeType) . '<br>Porsi: ' . (int)$servings .
        '<br>Rasa: ' . emailEscape($flavor) . '<br>Acara: ' . emailEscape($eventLabel) .
        '</p><p>Desain: ' . nl2br(emailEscape($design)) . '</p>';
    $textBody = "Pre-order custom cake baru\nKode: $orderCode\nCustomer: {$customer['name']} ({$customer['email']})\nJenis: $cakeType\nPorsi: $servings\nRasa: $flavor\nAcara: $eventLabel\nDesain: $design";
    $adminEmailSent = sendAdminNotificationEmail('Pre-order custom cake baru: ' . $orderCode, $emailBody, $textBody);
    $customerEmailSent = sendConfiguredEmail(
        $customer['email'],
        $customer['name'],
        'Pre-order custom cake diterima: ' . $orderCode,
        '<h2>Permintaan custom cake diterima</h2><p>Terima kasih. Tim RiRa Cakes akan meninjau desain dan membalas melalui chat di aplikasi untuk konfirmasi harga.</p><p>Kode pre-order: <strong>' . emailEscape($orderCode) . '</strong></p>',
        "Permintaan custom cake diterima. Kode pre-order: $orderCode. Tim RiRa Cakes akan membalas melalui chat di aplikasi untuk konfirmasi harga."
    );

    echo json_encode([
        'success' => true,
        'message' => 'Permintaan pre-order berhasil dikirim. Tim kami akan membalas melalui chat untuk konfirmasi harga.',
        'order_code' => $orderCode,
        'conversation_id' => $conversationId,
        'admin_email_sent' => $adminEmailSent,
        'customer_email_sent' => $customerEmailSent
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Custom cake pre-order failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Pre-order tidak dapat disimpan saat ini. Silakan coba lagi.']);
}
