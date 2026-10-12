<?php
function getVoucherDiscount(PDO $db, $code, $subtotal, $lock = false) {
    if (!is_string($code)) {
        throw new InvalidArgumentException('Format kode voucher tidak valid.');
    }
    $code = strtoupper(trim((string)$code));
    if ($code === '') {
        return ['code' => null, 'discount' => 0];
    }
    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
        throw new InvalidArgumentException('Format kode voucher tidak valid.');
    }

    $sql = "SELECT * FROM vouchers WHERE code = :code AND is_active = 1 LIMIT 1";
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([':code' => $code]);
    $voucher = $stmt->fetch();
    if (!$voucher) {
        throw new InvalidArgumentException('Kode voucher tidak ditemukan atau sudah tidak aktif.');
    }

    $now = new DateTimeImmutable();
    if ($voucher['starts_at'] && new DateTimeImmutable($voucher['starts_at']) > $now) {
        throw new InvalidArgumentException('Voucher belum mulai berlaku.');
    }
    if ($voucher['expires_at'] && new DateTimeImmutable($voucher['expires_at']) < $now) {
        throw new InvalidArgumentException('Masa berlaku voucher sudah berakhir.');
    }
    if ($voucher['usage_limit'] !== null && (int)$voucher['usage_count'] >= (int)$voucher['usage_limit']) {
        throw new InvalidArgumentException('Kuota penggunaan voucher sudah habis.');
    }
    if ((float)$subtotal < (float)$voucher['minimum_order']) {
        throw new InvalidArgumentException(
            'Minimum belanja untuk voucher ini adalah Rp ' . number_format((float)$voucher['minimum_order'], 0, ',', '.') . '.'
        );
    }

    $discount = $voucher['discount_type'] === 'percentage'
        ? (int)round((float)$subtotal * (float)$voucher['discount_value'] / 100)
        : (int)round((float)$voucher['discount_value']);
    $discount = min(max(0, $discount), (int)round((float)$subtotal));

    return [
        'id' => (int)$voucher['id'],
        'code' => $code,
        'discount' => $discount,
        'discount_type' => $voucher['discount_type'],
        'discount_value' => (float)$voucher['discount_value']
    ];
}
