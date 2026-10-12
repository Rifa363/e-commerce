<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';
require_once '../config/session.php';
require_once 'chat_service.php';
require_once '../config/voucher.php';
require_once '../config/mailer.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk atau daftar sebelum checkout.']);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Checkout hanya tersedia untuk akun customer.']);
    exit;
}

try {
    $data = json_decode(file_get_contents("php://input"));
    if (!$data || empty($data->items) || !is_array($data->items)) {
        throw new InvalidArgumentException('Data pesanan tidak valid');
    }
    if (!in_array($data->shipping_method ?? '', ['instant', 'sameday', 'reguler'], true)
        || !in_array($data->payment_method ?? '', ['transfer', 'qris', 'cod'], true)) {
        throw new InvalidArgumentException('Metode pengiriman atau pembayaran tidak valid.');
    }
    if (trim((string)($data->recipient_name ?? '')) === ''
        || trim((string)($data->recipient_phone ?? '')) === ''
        || trim((string)($data->address ?? '')) === '') {
        throw new InvalidArgumentException('Data penerima dan alamat wajib diisi.');
    }
    $recipientEmail = trim((string)($data->recipient_email ?? ''));
    if ($recipientEmail !== '' && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Alamat email penerima tidak valid.');
    }

    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();

    $order_code = "RC" . date('Ymd') . strtoupper(substr(uniqid(), -5));

    $quantities = [];
    foreach ($data->items as $item) {
        $productId = filter_var($item->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($item->qty ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$productId || !$quantity) {
            throw new InvalidArgumentException('Data produk di keranjang tidak valid.');
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
    }
    if (!$quantities) {
        throw new InvalidArgumentException('Keranjang belanja masih kosong.');
    }

    $productIds = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $productStmt = $db->prepare("SELECT id, name, price, stock, is_available FROM products WHERE id IN ($placeholders) FOR UPDATE");
    $productStmt->execute($productIds);
    $products = [];
    foreach ($productStmt->fetchAll() as $product) {
        $products[$product['id']] = $product;
    }

    $cartItems = [];
    $subtotal = 0;
    foreach ($quantities as $productId => $quantity) {
        if (!isset($products[$productId]) || !(int)$products[$productId]['is_available']) {
            throw new InvalidArgumentException('Salah satu produk sudah tidak tersedia.');
        }
        if ((int)$products[$productId]['stock'] < $quantity) {
            throw new InvalidArgumentException('Stok produk ' . $products[$productId]['name'] . ' tidak mencukupi.');
        }
        $price = (int)round((float)$products[$productId]['price']);
        $subtotal += $price * $quantity;
        $cartItems[] = [
            'id' => (int)$productId,
            'name' => $products[$productId]['name'],
            'price' => $price,
            'qty' => $quantity
        ];
    }

    $voucherCode = $data->voucher_code ?? '';
    if (!is_string($voucherCode)) {
        throw new InvalidArgumentException('Kode voucher tidak valid.');
    }
    $voucherCode = trim($voucherCode);
    $voucher = $voucherCode !== '' ? getVoucherDiscount($db, $voucherCode, $subtotal, true) : ['code' => null, 'discount' => 0];
    $discount = $voucher['discount'];

    // Shipping cost
    $shipping_map = ['instant' => 25000, 'sameday' => 18000, 'reguler' => 12000];
    $shipping_cost = $shipping_map[$data->shipping_method] ?? 25000;
    $total = $subtotal - $discount + $shipping_cost;

    // Generate payment details berdasarkan metode
    $payment_details = '';
    if ($data->payment_method === 'transfer') {
        // Ambil rekening BCA sebagai default
        $stmt = $db->prepare("SELECT * FROM payment_accounts WHERE method = 'transfer' AND bank_name = 'BCA' LIMIT 1");
        $stmt->execute();
        $account = $stmt->fetch();
        if (!$account) {
            throw new RuntimeException('Rekening pembayaran belum dikonfigurasi');
        }
        $payment_details = json_encode([
            'bank' => $account['bank_name'],
            'account_number' => $account['account_number'],
            'account_holder' => $account['account_holder'],
            'amount' => $total,
            'deadline' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    } elseif ($data->payment_method === 'qris') {
        $stmt = $db->prepare("SELECT * FROM payment_accounts WHERE method = 'qris' LIMIT 1");
        $stmt->execute();
        $account = $stmt->fetch();
        $payment_details = json_encode([
            'qr_image' => $account['qr_image_url'] ?? 'uploads/qris-main.png',
            'amount' => $total,
            'deadline' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    } else {
        // COD
        $payment_details = json_encode([
            'note' => 'Bayar tunai saat pesanan diantar',
            'amount' => $total
        ]);
    }

    // Insert order
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("INSERT INTO orders (user_id, order_code, recipient_name, recipient_phone, recipient_email, address, 
                          shipping_method, shipping_cost, subtotal, discount, voucher_code, total_amount, payment_method, payment_details, notes) 
                          VALUES (:uid, :code, :name, :phone, :email, :addr, :ship, :scost, :sub, :discount, :voucher, :tot, :pay, :pdet, :notes)");
    $stmt->execute([
        ':uid' => $user_id,
        ':code' => $order_code,
        ':name' => htmlspecialchars($data->recipient_name),
        ':phone' => htmlspecialchars($data->recipient_phone),
        ':email' => $recipientEmail !== '' ? $recipientEmail : null,
        ':addr' => htmlspecialchars($data->address),
        ':ship' => $data->shipping_method,
        ':scost' => $shipping_cost,
        ':sub' => $subtotal,
        ':discount' => $discount,
        ':voucher' => $voucher['code'],
        ':tot' => $total,
        ':pay' => $data->payment_method,
        ':pdet' => $payment_details,
        ':notes' => htmlspecialchars($data->notes ?? '')
    ]);
    $order_id = $db->lastInsertId();

    // Insert order items & kurangi stok
    $items_detail = [];
    foreach ($cartItems as $item) {
        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                              VALUES (:oid, :pid, :qty, :price)");
        $stmt->execute([':oid' => $order_id, ':pid' => $item['id'], ':qty' => $item['qty'], ':price' => $item['price']]);

        $stmt = $db->prepare("UPDATE products SET stock = stock - :q, sold_count = sold_count + :q WHERE id = :id AND stock >= :available_qty AND is_available = 1");
        $stmt->execute([':q' => $item['qty'], ':id' => $item['id'], ':available_qty' => $item['qty']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Stok produk berubah. Muat ulang keranjang lalu coba lagi.');
        }

        $items_detail[] = ['name' => $item['name'], 'qty' => $item['qty'], 'price' => $item['price']];
    }

    if (!empty($voucher['id'])) {
        $usageStmt = $db->prepare(
            "UPDATE vouchers SET usage_count = usage_count + 1
             WHERE id = :id AND is_active = 1 AND (usage_limit IS NULL OR usage_count < usage_limit)"
        );
        $usageStmt->execute([':id' => $voucher['id']]);
        if ($usageStmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Kuota penggunaan voucher baru saja habis.');
        }
    }

    $conversationId = getOrCreateChatConversation($db, (int)$user_id);
    $chatOrderLines = array_map(
        static fn($item) => "{$item['name']} x{$item['qty']} — Rp " . number_format($item['price'] * $item['qty'], 0, ',', '.'),
        $items_detail
    );
    $chatMessage = "Pesanan baru\nKode: {$order_code}\nPenerima: {$data->recipient_name}\nTelepon: {$data->recipient_phone}\nAlamat: {$data->address}\nProduk:\n" .
        implode("\n", $chatOrderLines) . "\nTotal: Rp " . number_format($total, 0, ',', '.') .
        "\nPembayaran: {$data->payment_method}\nPengiriman: {$data->shipping_method}";
    saveChatMessage($db, $conversationId, (int)$user_id, 'customer', $chatMessage);

    $db->commit();

    $emailBody = '<h2>Pesanan baru RiRa Cakes</h2><p>Kode: <strong>' . emailEscape($order_code) .
        '</strong></p><p>Customer: ' . emailEscape($data->recipient_name) . '<br>Telepon: ' .
        emailEscape($data->recipient_phone) . '<br>Alamat: ' . nl2br(emailEscape($data->address)) .
        '</p><p>Subtotal: Rp ' . number_format($subtotal, 0, ',', '.') .
        '<br>Diskon: Rp ' . number_format($discount, 0, ',', '.') .
        '<br>Ongkir: Rp ' . number_format($shipping_cost, 0, ',', '.') .
        '<br><strong>Total: Rp ' . number_format($total, 0, ',', '.') . '</strong></p>';
    $textBody = "Pesanan baru RiRa Cakes\nKode: $order_code\nCustomer: {$data->recipient_name}\nTelepon: {$data->recipient_phone}\nSubtotal: Rp " .
        number_format($subtotal, 0, ',', '.') . "\nDiskon: Rp " . number_format($discount, 0, ',', '.') .
        "\nOngkir: Rp " . number_format($shipping_cost, 0, ',', '.') . "\nTotal: Rp " . number_format($total, 0, ',', '.');
    $adminEmailSent = sendAdminNotificationEmail('Pesanan baru: ' . $order_code, $emailBody, $textBody);
    $customerEmailSent = $recipientEmail !== '' && sendConfiguredEmail(
        $recipientEmail,
        $data->recipient_name,
        'Pesanan Anda diterima: ' . $order_code,
        '<h2>Terima kasih telah memesan di RiRa Cakes</h2><p>Pesanan <strong>' . emailEscape($order_code) .
            '</strong> berhasil kami terima.</p><p>Total pembayaran: <strong>Rp ' . number_format($total, 0, ',', '.') .
            '</strong>. Kami akan menghubungi Anda melalui chat di aplikasi untuk informasi selanjutnya.</p>',
        "Pesanan $order_code berhasil kami terima. Total pembayaran: Rp " . number_format($total, 0, ',', '.') . '.'
    );

    echo json_encode([
        "success" => true,
        "message" => "Pesanan berhasil dibuat",
        "order_code" => $order_code,
        "subtotal" => $subtotal,
        "discount" => $discount,
        "voucher_code" => $voucher['code'],
        "shipping_cost" => $shipping_cost,
        "total" => $total,
        "payment_method" => $data->payment_method,
        "payment_details" => json_decode($payment_details, true),
        "conversation_id" => $conversationId,
        "admin_email_sent" => $adminEmailSent,
        "customer_email_sent" => $customerEmailSent
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Gagal: " . $e->getMessage()]);
}
?>