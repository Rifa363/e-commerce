<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

function voucherInput($data) {
    if (!is_string($data->code ?? null)
        || !is_string($data->discount_type ?? null)
        || !is_numeric($data->discount_value ?? null)
        || !is_numeric($data->minimum_order ?? 0)
        || !is_string($data->starts_at ?? '')
        || !is_string($data->expires_at ?? '')
        || !(is_string($data->usage_limit ?? '') || is_int($data->usage_limit ?? null))
        || (isset($data->is_active) && !is_bool($data->is_active) && !in_array($data->is_active, [0, 1], true))) {
        throw new InvalidArgumentException('Data voucher tidak valid.');
    }
    $code = strtoupper(trim($data->code));
    $type = $data->discount_type ?? '';
    $value = filter_var($data->discount_value ?? null, FILTER_VALIDATE_FLOAT);
    $minimum = filter_var($data->minimum_order ?? 0, FILTER_VALIDATE_FLOAT);
    $usageLimitRaw = trim((string)($data->usage_limit ?? ''));
    $usageLimit = $usageLimitRaw === '' ? null : filter_var($usageLimitRaw, FILTER_VALIDATE_INT);
    $startsAt = trim((string)($data->starts_at ?? ''));
    $expiresAt = trim((string)($data->expires_at ?? ''));

    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
        throw new InvalidArgumentException('Kode harus 3-30 karakter: huruf, angka, tanda hubung, atau garis bawah.');
    }
    if (!in_array($type, ['percentage', 'fixed'], true) || $value === false || $value <= 0) {
        throw new InvalidArgumentException('Jenis atau nilai diskon tidak valid.');
    }
    if ($type === 'percentage' && $value > 100) {
        throw new InvalidArgumentException('Diskon persentase tidak boleh lebih dari 100%.');
    }
    if ($minimum === false || $minimum < 0) {
        throw new InvalidArgumentException('Minimum belanja tidak valid.');
    }
    if ($usageLimitRaw !== '' && ($usageLimit === false || $usageLimit < 1)) {
        throw new InvalidArgumentException('Batas penggunaan harus bilangan bulat positif atau dikosongkan.');
    }

    $parseDate = static function ($value, $field) {
        if ($value === '') {
            return null;
        }
        $date = DateTime::createFromFormat('!Y-m-d\TH:i', $value);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new InvalidArgumentException($field . ' tidak valid.');
        }
        return $date->format('Y-m-d H:i:s');
    };
    $startsAt = $parseDate($startsAt, 'Tanggal mulai');
    $expiresAt = $parseDate($expiresAt, 'Tanggal berakhir');
    if ($startsAt && $expiresAt && $startsAt >= $expiresAt) {
        throw new InvalidArgumentException('Tanggal berakhir harus setelah tanggal mulai.');
    }

    return [
        ':code' => $code,
        ':type' => $type,
        ':value' => $value,
        ':minimum' => $minimum,
        ':starts' => $startsAt,
        ':expires' => $expiresAt,
        ':usage_limit' => $usageLimit,
        ':active' => !empty($data->is_active) ? 1 : 0
    ];
}

try {
    $db = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->query('SELECT * FROM vouchers ORDER BY created_at DESC');
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'));
    if (!$data) {
        throw new InvalidArgumentException('Data voucher tidak valid.');
    }

    if ($method === 'POST') {
        $values = voucherInput($data);
        $stmt = $db->prepare(
            'INSERT INTO vouchers (code, discount_type, discount_value, minimum_order, starts_at, expires_at, usage_limit, is_active)
             VALUES (:code, :type, :value, :minimum, :starts, :expires, :usage_limit, :active)'
        );
        $stmt->execute($values);
        echo json_encode(['success' => true, 'message' => 'Voucher berhasil ditambahkan.']);
        exit;
    }

    if ($method === 'PUT') {
        $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            throw new InvalidArgumentException('ID voucher tidak valid.');
        }
        $values = voucherInput($data);
        $values[':id'] = $id;
        $stmt = $db->prepare(
            'UPDATE vouchers SET code = :code, discount_type = :type, discount_value = :value,
                    minimum_order = :minimum, starts_at = :starts, expires_at = :expires,
                    usage_limit = :usage_limit, is_active = :active WHERE id = :id'
        );
        $stmt->execute($values);
        echo json_encode(['success' => true, 'message' => 'Voucher berhasil diperbarui.']);
        exit;
    }

    if ($method === 'DELETE') {
        $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) {
            throw new InvalidArgumentException('ID voucher tidak valid.');
        }
        $stmt = $db->prepare('UPDATE vouchers SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Voucher dinonaktifkan.']);
        exit;
    }

    header('Allow: GET, POST, PUT, DELETE');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log('Voucher admin request failed: ' . $e->getMessage());
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode(['success' => false, 'message' => $e->getCode() === '23000'
        ? 'Kode voucher sudah digunakan.'
        : 'Voucher tidak dapat disimpan saat ini.']);
} catch (Throwable $e) {
    error_log('Voucher admin request failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Voucher tidak dapat diproses saat ini.']);
}
