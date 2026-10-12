<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/mailer.php';
require_once 'whatsapp_notify.php';

function passwordResetResponse($success, $message, $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function passwordResetIdentifierHash($email) {
    return hash('sha256', mb_strtolower(trim($email), 'UTF-8'));
}

function passwordResetIpHash() {
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'channels' => [
            'email' => isEmailDeliveryConfigured(),
            'whatsapp' => isWhatsAppOtpDeliveryConfigured()
        ]
    ]);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    passwordResetResponse(false, 'Metode request tidak didukung.', 405);
}

$data = json_decode(file_get_contents('php://input'));
$action = $data->action ?? '';

if ($action === 'request') {
    $email = filter_var($data->email ?? '', FILTER_VALIDATE_EMAIL);
    $channel = $data->channel ?? '';
    if (!$email || !in_array($channel, ['email', 'whatsapp'], true)) {
        passwordResetResponse(false, 'Email atau metode verifikasi tidak valid.', 400);
    }

    $identifierHash = passwordResetIdentifierHash($email);
    $ipHash = passwordResetIpHash();
    $rateStmt = $db->prepare(
        'SELECT
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND identifier_hash = :identifier_hash) AS identifier_requests,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND request_ip_hash = :ip_hash) AS ip_requests
         FROM password_reset_tokens
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND (identifier_hash = :identifier_filter OR request_ip_hash = :ip_filter)'
    );
    $rateStmt->execute([
        ':identifier_hash' => $identifierHash,
        ':ip_hash' => $ipHash,
        ':identifier_filter' => $identifierHash,
        ':ip_filter' => $ipHash
    ]);
    $rate = $rateStmt->fetch();
    $limited = (int)($rate['identifier_requests'] ?? 0) >= 3 || (int)($rate['ip_requests'] ?? 0) >= 10;

    $userStmt = $db->prepare('SELECT id, name, email, phone FROM users WHERE email = :email LIMIT 1');
    $userStmt->execute([':email' => $email]);
    $user = $userStmt->fetch();

    if (!$limited) {
        $code = (string)random_int(100000, 999999);
        $canDeliver = $user && (
            ($channel === 'email' && isEmailDeliveryConfigured()) ||
            ($channel === 'whatsapp' && !empty($user['phone']) && isWhatsAppOtpDeliveryConfigured())
        );
        $tokenHash = password_hash($canDeliver ? $code : (string)random_int(100000, 999999), PASSWORD_DEFAULT);

        $db->beginTransaction();
        try {
            $invalidate = $db->prepare(
                'UPDATE password_reset_tokens SET used_at = NOW()
                 WHERE identifier_hash = :identifier_hash AND used_at IS NULL'
            );
            $invalidate->execute([':identifier_hash' => $identifierHash]);

            $insert = $db->prepare(
                'INSERT INTO password_reset_tokens
                 (user_id, identifier_hash, channel, token_hash, request_ip_hash, expires_at)
                 VALUES (:user_id, :identifier_hash, :channel, :token_hash, :ip_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
            );
            $insert->execute([
                ':user_id' => $canDeliver ? $user['id'] : null,
                ':identifier_hash' => $identifierHash,
                ':channel' => $channel,
                ':token_hash' => $tokenHash,
                ':ip_hash' => $ipHash
            ]);
            $tokenId = $db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($canDeliver) {
            $message = "Kode reset password RiRa Cakes Anda: {$code}. Kode berlaku 10 menit dan hanya dapat digunakan sekali. Jangan berikan kode ini kepada siapa pun.";
            $sent = $channel === 'email'
                ? sendConfiguredEmail(
                    $user['email'],
                    $user['name'],
                    'Kode reset password RiRa Cakes',
                    '<h2>Reset password RiRa Cakes</h2><p>Kode verifikasi Anda:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px;">' .
                        emailEscape($code) . '</p><p>Kode berlaku 10 menit dan hanya dapat digunakan sekali. Jika Anda tidak meminta reset password, abaikan email ini.</p>',
                    $message . ' Jika Anda tidak meminta reset password, abaikan pesan ini.'
                )
                : sendWhatsAppOtp($user['phone'], $message);

            if (!$sent) {
                $failedDelivery = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
                $failedDelivery->execute([':id' => $tokenId]);
            }
        }
    }

    passwordResetResponse(
        true,
        'Jika akun dan metode verifikasi cocok, kode akan dikirim. Periksa pesan Anda dan gunakan kode dalam 10 menit.'
    );
}

if ($action === 'reset') {
    $email = filter_var($data->email ?? '', FILTER_VALIDATE_EMAIL);
    $channel = $data->channel ?? '';
    $code = is_string($data->code ?? null) ? trim($data->code) : '';
    $password = is_string($data->password ?? null) ? $data->password : '';
    if (!$email || !in_array($channel, ['email', 'whatsapp'], true)
        || !preg_match('/^\d{6}$/', $code) || strlen($password) < 8 || strlen($password) > 200) {
        passwordResetResponse(false, 'Data reset tidak valid. Password minimal 8 karakter.', 400);
    }

    $identifierHash = passwordResetIdentifierHash($email);
    $db->beginTransaction();
    try {
        $tokenStmt = $db->prepare(
            'SELECT id, user_id, token_hash, attempts FROM password_reset_tokens
             WHERE identifier_hash = :identifier_hash AND channel = :channel
               AND used_at IS NULL AND expires_at > NOW() AND attempts < 5
             ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $tokenStmt->execute([':identifier_hash' => $identifierHash, ':channel' => $channel]);
        $token = $tokenStmt->fetch();
        if (!$token || !$token['user_id'] || !password_verify($code, $token['token_hash'])) {
            if ($token) {
                $attemptStmt = $db->prepare(
                    'UPDATE password_reset_tokens SET attempts = attempts + 1 WHERE id = :id'
                );
                $attemptStmt->execute([':id' => $token['id']]);
            }
            $db->commit();
            passwordResetResponse(false, 'Kode tidak valid atau sudah kedaluwarsa. Minta kode baru dan coba lagi.', 400);
        }

        $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
        $updateUser = $db->prepare('UPDATE users SET password = :password WHERE id = :user_id');
        $updateUser->execute([':password' => $newPasswordHash, ':user_id' => $token['user_id']]);
        if ($updateUser->rowCount() !== 1) {
            throw new RuntimeException('Akun untuk reset password tidak ditemukan.');
        }

        $consumeTokens = $db->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL'
        );
        $consumeTokens->execute([':user_id' => $token['user_id']]);
        $db->commit();
        passwordResetResponse(true, 'Password berhasil diubah. Silakan masuk menggunakan password baru.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Password reset failed: ' . $e->getMessage());
        passwordResetResponse(false, 'Password tidak dapat diubah saat ini. Silakan coba lagi.', 500);
    }
}

passwordResetResponse(false, 'Aksi tidak dikenal.', 400);
?>
