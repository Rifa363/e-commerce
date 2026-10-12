<?php
// Konfigurasi koneksi database menggunakan PDO (aman dari SQL Injection)
class Database {
    private $host = "localhost";
    private $db_name = "manis_legit_db";
    private $username = "root";        // Ganti dengan username MySQL Anda
    private $password = "";            // Ganti dengan password MySQL Anda
    private $conn;

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