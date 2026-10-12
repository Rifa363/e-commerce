<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';

// Auto-refresh user data dari DB
if (isLoggedIn() && isset($_SESSION['user_id'])) {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['role'] = $user['role'];
    }
}
?>