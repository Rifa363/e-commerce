<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';
require_once '../config/session.php';

function respond($status, $message) {
    http_response_code($status);
    echo json_encode(['success' => $status < 400, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Metode request tidak didukung.');
}

if (!isLoggedIn()) {
    respond(401, 'Silakan masuk ke akun customer untuk mengunggah bukti transfer.');
}
if (($_SESSION['role'] ?? '') !== 'customer') {
    respond(403, 'Unggah bukti transfer hanya tersedia untuk akun customer.');
}

$orderCode = strtoupper(trim($_POST['order_code'] ?? ''));
$phone = preg_replace('/\D+/', '', $_POST['recipient_phone'] ?? '');
if (!preg_match('/^RC\d{8}[A-F0-9]{5}$/', $orderCode) || strlen($phone) < 8) {
    respond(400, 'Kode pesanan atau nomor telepon tidak valid.');
}

if (!isset($_FILES['proof']) || !is_array($_FILES['proof'])) {
    respond(400, 'Pilih file bukti transfer terlebih dahulu.');
}

$file = $_FILES['proof'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        ? 'Ukuran file melebihi batas upload server.'
        : 'Upload file gagal. Silakan coba lagi.';
    respond(400, $message);
}
if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
    respond(400, 'Ukuran bukti transfer harus maksimal 5 MB.');
}
if (!is_uploaded_file($file['tmp_name'])) {
    respond(400, 'File upload tidak valid.');
}

try {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare(
        "SELECT id, user_id, recipient_phone, payment_method, status, payment_proof
         FROM orders WHERE order_code = :code LIMIT 1"
    );
    $stmt->execute([':code' => $orderCode]);
    $order = $stmt->fetch();

    if (!$order || (int)$order['user_id'] !== (int)$_SESSION['user_id']
        || preg_replace('/\D+/', '', $order['recipient_phone']) !== $phone) {
        respond(404, 'Pesanan tidak ditemukan. Periksa kembali kode pesanan dan nomor telepon.');
    }
    if ($order['payment_method'] !== 'transfer') {
        respond(400, 'Bukti transfer hanya dapat dikirim untuk pesanan dengan metode transfer bank.');
    }
    if ($order['status'] !== 'pending') {
        respond(409, 'Bukti hanya dapat dikirim saat pesanan masih menunggu pembayaran.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime) {
        respond(400, 'Format bukti tidak didukung. Gunakan gambar JPG, PNG, atau WEBP.');
    }

    $uploadDir = __DIR__ . '/../uploads/payment-proofs/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Folder penyimpanan bukti transfer tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Bukti transfer gagal disimpan.');
    }

    try {
        $update = $db->prepare('UPDATE orders SET payment_proof = :proof WHERE id = :id AND status = :status');
        $update->execute([':proof' => 'uploads/payment-proofs/' . $filename, ':id' => $order['id'], ':status' => 'pending']);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Status pesanan berubah. Muat ulang halaman dan coba kembali.');
        }
    } catch (Throwable $e) {
        if (is_file($targetPath)) {
            unlink($targetPath);
        }
        throw $e;
    }

    if ($order['payment_proof']) {
        $previousName = basename($order['payment_proof']);
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $previousName)) {
            $previousPath = $uploadDir . $previousName;
            if (is_file($previousPath)) {
                unlink($previousPath);
            }
        }
    }

    respond(200, 'Bukti transfer berhasil dikirim. Admin akan memverifikasi pembayaran.');
} catch (Throwable $e) {
    error_log('Payment proof upload failed: ' . $e->getMessage());
    respond(500, 'Bukti transfer tidak dapat disimpan. Silakan coba lagi.');
}
