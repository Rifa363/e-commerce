<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once '../config/database.php';
require_once '../config/session.php';

$data = json_decode(file_get_contents("php://input"));
$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

// ============================================
// REGISTER
// ============================================
if ($action === 'register') {
    $name = trim(htmlspecialchars($data->name ?? ''));
    $email = filter_var($data->email ?? '', FILTER_VALIDATE_EMAIL);
    $password = $data->password ?? '';
    $phone = trim(htmlspecialchars($data->phone ?? ''));

    if (!$name || !$email || strlen($password) < 6) {
        echo json_encode(["success" => false, "message" => "Data tidak valid. Password min 6 karakter."]);
        exit;
    }

    // Cek email sudah ada
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :e");
    $stmt->execute([':e' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Email sudah terdaftar"]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $db->prepare("INSERT INTO users (name, email, password, phone) VALUES (:n, :e, :p, :ph)");
        $stmt->execute([':n' => $name, ':e' => $email, ':p' => $hashed, ':ph' => $phone]);
        
        // Auto login setelah register
        $user_id = $db->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = 'customer';
        
        echo json_encode([
            "success" => true, 
            "message" => "Registrasi berhasil",
            "user" => ["id" => $user_id, "name" => $name, "role" => "customer"]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Gagal registrasi"]);
    }
}

// ============================================
// LOGIN
// ============================================
elseif ($action === 'login') {
    $email = $data->email ?? '';
    $password = $data->password ?? '';

    $stmt = $db->prepare("SELECT * FROM users WHERE email = :e");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID untuk keamanan
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode([
            "success" => true, 
            "message" => "Login berhasil",
            "user" => [
                "id" => $user['id'], 
                "name" => $user['name'], 
                "email" => $user['email'],
                "role" => $user['role']
            ],
            "redirect" => $user['role'] === 'admin' ? 'admin/index.html' : 'index.html'
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Email atau password salah"]);
    }
}

// ============================================
// LOGOUT
// ============================================
elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(["success" => true, "message" => "Logout berhasil"]);
}

// ============================================
// CHECK SESSION
// ============================================
elseif ($action === 'check') {
    if (isLoggedIn()) {
        echo json_encode([
            "success" => true, 
            "logged_in" => true, 
            "user" => getCurrentUser()
        ]);
    } else {
        echo json_encode(["success" => true, "logged_in" => false]);
    }
}
?>