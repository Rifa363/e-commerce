<?php
/**
 * ============================================
 * SETUP PASSWORDS - Jalankan SEKALI saja!
 * ============================================
 * File ini akan mengupdate password admin & customer
 * ke hash yang benar, lalu Anda bisa HAPUS file ini.
 * 
 * CARA PAKAI:
 * 1. Letakkan file ini di root folder (sejajar index.html)
 * 2. Buka browser: http://localhost/rira-cakes/setup_passwords.php
 * 3. Setelah sukses, HAPUS file ini untuk keamanan!
 * ============================================
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Password yang akan di-set
$accounts = [
    [
        'email' => 'admin@riracakes.id',
        'password' => 'admin123',
        'role' => 'admin',
        'name' => 'Admin RiRa'
    ],
    [
        'email' => 'customer@test.com',
        'password' => 'customer123',
        'role' => 'customer',
        'name' => 'John Customer'
    ]
];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Passwords - RiRa Cakes</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 40px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { color: #C87F6B; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9f9f9; font-weight: 600; }
        code { background: #f4f4f4; padding: 2px 8px; border-radius: 4px; font-family: 'Courier New', monospace; }
        .btn { display: inline-block; padding: 10px 20px; background: #C87F6B; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔐 Setup Passwords - RiRa Cakes</h1>";

try {
    echo "<div class='info'>Mengeksekusi update password...</div>";
    
    foreach ($accounts as $acc) {
        // Generate hash password yang benar
        $hashedPassword = password_hash($acc['password'], PASSWORD_DEFAULT);
        
        // Cek apakah user sudah ada
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $acc['email']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update password
            $stmt = $db->prepare("UPDATE users SET password = :pass, role = :role WHERE email = :email");
            $stmt->execute([
                ':pass' => $hashedPassword,
                ':role' => $acc['role'],
                ':email' => $acc['email']
            ]);
            echo "<div class='success'>✅ <strong>{$acc['email']}</strong> - Password berhasil diupdate</div>";
        } else {
            // Insert user baru
            $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (:name, :email, :pass, '081234567890', :role)");
            $stmt->execute([
                ':name' => $acc['name'],
                ':email' => $acc['email'],
                ':pass' => $hashedPassword,
                ':role' => $acc['role']
            ]);
            echo "<div class='success'>✅ <strong>{$acc['email']}</strong> - User berhasil dibuat</div>";
        }
    }
    
    echo "<h2 style='margin-top:30px;color:#2D2424;'>📋 Kredensial Login Baru</h2>";
    echo "<table>
            <tr><th>Role</th><th>Email</th><th>Password</th></tr>
            <tr><td><strong>Admin</strong></td><td>admin@riracakes.id</td><td><code>admin123</code></td></tr>
            <tr><td><strong>Customer</strong></td><td>customer@test.com</td><td><code>customer123</code></td></tr>
          </table>";
    
    echo "<div class='warning'>
            <strong>⚠️ PENTING - KEAMANAN:</strong><br>
            <ol style='margin-top:10px;'>
                <li><strong>HAPUS file <code>setup_passwords.php</code> sekarang juga!</strong> File ini tidak boleh ada di server production.</li>
                <li>Segera ganti password default setelah login pertama kali.</li>
                <li>Untuk production, gunakan password yang kuat (min 12 karakter, kombinasi huruf besar/kecil/angka/simbol).</li>
            </ol>
          </div>";
    
    echo "<div class='info'>
            <strong>💡 Tips:</strong> Anda bisa login sekarang di <a href='login.html' style='color:#0c5460;font-weight:600;'>halaman login</a> atau langsung ke 
            <a href='admin/index.html' style='color:#0c5460;font-weight:600;'>Admin Dashboard</a>
          </div>";
    
    echo "<a href='login.html' class='btn'>🚀 Ke Halaman Login</a>";
    echo "<a href='admin/index.html' class='btn' style='background:#2D2424;margin-left:10px;'>📊 Ke Admin Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>