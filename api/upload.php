<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';
require_once '../config/session.php';
requireAdmin();

$upload_dir = __DIR__ . '/../uploads/products/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$max_size = 2 * 1024 * 1024; // 2MB

// Validasi
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Format tidak didukung. Gunakan JPG/PNG/WEBP.']);
    exit;
}
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 2MB.']);
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error upload: ' . $file['error']]);
    exit;
}

// Generate nama unik
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_name = 'product_' . time() . '_' . uniqid() . '.' . $ext;
$target_path = $upload_dir . $new_name;

if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // Compress image (optional, jika ada GD library)
    if (function_exists('imagecreatefromjpeg')) {
        compressImage($target_path, $target_path, 80);
    }
    
    echo json_encode([
        'success' => true,
        'url' => 'uploads/products/' . $new_name,
        'filename' => $new_name
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
}

function compressImage($source, $destination, $quality) {
    $info = getimagesize($source);
    if ($info['mime'] === 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] === 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, floor($quality / 10));
    }
}
?>