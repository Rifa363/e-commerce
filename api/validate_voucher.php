<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/database.php';
require_once '../config/voucher.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new InvalidArgumentException('Metode request tidak didukung.');
    }

    $data = json_decode(file_get_contents('php://input'));
    if (!$data || !isset($data->code) || !is_string($data->code) || trim($data->code) === ''
        || empty($data->items) || !is_array($data->items)) {
        throw new InvalidArgumentException('Masukkan kode voucher dan produk di keranjang.');
    }
    $shippingMap = ['instant' => 25000, 'sameday' => 18000, 'reguler' => 12000];
    $shippingMethod = $data->shipping_method ?? 'reguler';
    if (!is_string($shippingMethod) || !isset($shippingMap[$shippingMethod])) {
        throw new InvalidArgumentException('Metode pengiriman tidak valid.');
    }

    $quantities = [];
    foreach ($data->items as $item) {
        $id = filter_var($item->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($item->qty ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id || !$quantity) {
            throw new InvalidArgumentException('Data produk di keranjang tidak valid.');
        }
        $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
    }

    $db = (new Database())->getConnection();
    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, price, stock, is_available FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $product) {
        $products[$product['id']] = $product;
    }

    $subtotal = 0;
    foreach ($quantities as $id => $quantity) {
        if (!isset($products[$id]) || !(int)$products[$id]['is_available']) {
            throw new InvalidArgumentException('Salah satu produk sudah tidak tersedia.');
        }
        if ((int)$products[$id]['stock'] < $quantity) {
            throw new InvalidArgumentException('Stok produk tidak mencukupi.');
        }
        $subtotal += (int)round((float)$products[$id]['price']) * $quantity;
    }

    $voucher = getVoucherDiscount($db, $data->code, $subtotal);
    echo json_encode([
        'success' => true,
        'code' => $voucher['code'],
        'subtotal' => $subtotal,
        'discount' => $voucher['discount'],
        'shipping_cost' => $shippingMap[$shippingMethod],
        'total' => max(0, $subtotal - $voucher['discount']) + $shippingMap[$shippingMethod]
    ]);
} catch (InvalidArgumentException $e) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Voucher validation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Voucher tidak dapat diperiksa saat ini.']);
}
