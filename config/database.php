<?php
// Konfigurasi koneksi database menggunakan PDO (aman dari SQL Injection)
// Nilai dibaca dari environment (.env di root project) dengan fallback lokal.
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host     = getenv('DB_HOST') ?: 'localhost';
        $this->db_name  = getenv('DB_NAME') ?: 'manis_legit_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Koneksi database gagal: " . $e->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
?>
